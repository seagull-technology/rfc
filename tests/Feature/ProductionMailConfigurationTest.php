<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProductionMailConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->detectEnvironment(fn (): string => 'production');
        config([
            'app.debug' => false,
            'app.url' => 'https://filmjordan.jo',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'services.otp_debug_fallback' => false,
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.domain' => null,
            'cache.limiter' => 'database',
            'queue.default' => 'database',
            'security.trusted_proxies' => ['10.0.40.81'],
            'filesystems.disks.local.serve' => false,
            'security.trusted_hosts.enforce' => true,
            'security.trusted_hosts.hosts' => ['filmjordan.jo'],
            'security.headers.enabled' => true,
            'security.headers.csp_report_only' => false,
            'security.headers.hsts' => true,
            'security.external_urls.professional_profile_hosts' => ['imdb.com'],
            'security.external_urls.business_website_hosts' => ['filmjordan.jo'],
            'security.outbound_http.allowed_hosts' => ['bulk-sms.gov.jo'],
            'services.gov_sms.base' => 'https://bulk-sms.gov.jo',
            'services.gsb.enabled' => false,
            'services.gov_company_registry.enabled' => false,
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.scheme' => null,
            'mail.mailers.smtp.host' => 'mail.example.invalid',
            'mail.mailers.smtp.username' => 'private-mail-username',
            'mail.mailers.smtp.password' => 'private-mail-password',
        ]);
    }

    public function test_undefined_uppercase_mailer_fails_without_printing_credentials(): void
    {
        config(['mail.default' => 'SMTP']);

        $this->assertSame(1, Artisan::call('security:production-check'));
        $output = Artisan::output();
        $this->assertStringContainsString('MAIL_MAILER must exactly match a configured mail.mailers key', $output);
        $this->assertStringContainsString('case-sensitive; use smtp', $output);
        $this->assertStringNotContainsString('private-mail-username', $output);
        $this->assertStringNotContainsString('private-mail-password', $output);
        $this->assertStringNotContainsString('mail.example.invalid', $output);
    }

    public function test_a_defined_custom_mailer_name_passes_without_smtp_settings(): void
    {
        config([
            'mail.default' => 'CustomReviewMailer',
            'mail.mailers.CustomReviewMailer' => ['transport' => 'array'],
            'mail.mailers.smtp.timeout' => null,
        ]);

        $this->artisan('security:production-check')
            ->expectsOutputToContain('Production security checks passed.')
            ->assertSuccessful();
    }

    #[DataProvider('invalidSmtpTimeouts')]
    public function test_smtp_requires_a_finite_positive_bounded_socket_timeout(mixed $timeout): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.timeout' => $timeout]);

        $this->artisan('security:production-check')
            ->expectsOutputToContain('finite positive socket timeout of at most 30 seconds')
            ->assertFailed();
    }

    public static function invalidSmtpTimeouts(): array
    {
        return [[null], [0], [-1], [31], ['not-a-timeout'], [INF], [NAN]];
    }

    public function test_bundled_smtp_timeout_is_applied_to_the_transport_without_connecting(): void
    {
        config(['mail.default' => 'smtp']);
        $timeout = config('mail.mailers.smtp.timeout');
        $this->assertSame(30, $timeout);
        $this->assertSame(30.0, Mail::mailer()->getSymfonyTransport()->getStream()->getTimeout());

        $this->artisan('security:production-check')->assertSuccessful();
    }

    public function test_a_custom_smtp_mailer_uses_its_configured_numeric_timeout(): void
    {
        config([
            'mail.default' => 'ReviewSmtp',
            'mail.mailers.ReviewSmtp' => [...config('mail.mailers.smtp'), 'timeout' => '12.5'],
        ]);

        $this->artisan('security:production-check')->assertSuccessful();
        $this->assertSame(12.5, Mail::mailer()->getSymfonyTransport()->getStream()->getTimeout());
    }

    public function test_mail_url_cannot_override_the_socket_timeout_with_zero(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.timeout' => 30,
            'mail.mailers.smtp.url' => 'smtp://private-url-user:private-url-password@mail.example.invalid:2525?timeout=0',
        ]);

        $this->assertSame(1, Artisan::call('security:production-check'));
        $output = Artisan::output();
        $this->assertStringContainsString('finite positive socket timeout of at most 30 seconds', $output);
        $this->assertStringNotContainsString('private-url-', $output);
        $this->assertStringNotContainsString('mail.example.invalid', $output);
    }

    public function test_a_bounded_mail_url_override_matches_the_real_transport(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.timeout' => null,
            'mail.mailers.smtp.url' => 'smtp://mail.example.invalid:2525?timeout=8',
        ]);

        $this->artisan('security:production-check')->assertSuccessful();
        $this->assertSame(8.0, Mail::mailer()->getSymfonyTransport()->getStream()->getTimeout());
    }

    #[DataProvider('normalizedSmtpSchemes')]
    public function test_bundled_protocol_case_is_normalized_before_transport_construction(string $scheme, string $expected): void
    {
        $settings = $this->loadBundledSmtpSettings($scheme);
        $this->assertSame($expected, $settings['scheme']);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => $settings['scheme']]);

        $this->artisan('security:production-check')->assertSuccessful();
        $this->assertSame($expected === 'smtps', Mail::mailer()->getSymfonyTransport()->getStream()->isTLS());
    }

    public static function normalizedSmtpSchemes(): array
    {
        return [['SMTP', 'smtp'], ['SMTPS', 'smtps'], ['sMtP', 'smtp'], ['smtp', 'smtp'], ['smtps', 'smtps']];
    }

    public function test_url_protocol_normalization_preserves_credentials_and_all_other_bytes(): void
    {
        $url = 'SMTP://PrivateUser:Mixed%40Case%2FPassword@mail.example.invalid:2525?timeout=8&local_domain=Review.Example';
        $settings = $this->loadBundledSmtpSettings('SMTP', $url);
        $this->assertSame('smtp'.substr($url, 4), $settings['url']);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => [...config('mail.mailers.smtp'), ...$settings]]);

        $this->artisan('security:production-check')->assertSuccessful();
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertSame('PrivateUser', $transport->getUsername());
        $this->assertSame('Mixed@Case/Password', $transport->getPassword());
        $this->assertSame(8.0, $transport->getStream()->getTimeout());
    }

    #[DataProvider('normalizedUrlSchemeQueries')]
    public function test_one_recognized_url_scheme_value_is_normalized_without_rebuilding_the_url(string $query, string $expectedQuery, bool $tls): void
    {
        $prefix = 'SMTP://Private%2BUser:Mixed%40Case%2FPassword@mail.example.invalid:2525?';
        $suffix = '&local_domain=Review.Example&note=Keep%2fMixed+Bytes#UnchangedFragment';
        $settings = $this->loadBundledSmtpSettings('SMTP', $prefix.$query.$suffix);
        $this->assertSame('smtp'.substr($prefix, 4).$expectedQuery.$suffix, $settings['url']);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => [...config('mail.mailers.smtp'), ...$settings]]);

        $this->artisan('security:production-check')->assertSuccessful();
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertSame('Private+User', $transport->getUsername());
        $this->assertSame('Mixed@Case/Password', $transport->getPassword());
        $this->assertSame($tls, $transport->getStream()->isTLS());
    }

    public static function normalizedUrlSchemeQueries(): array
    {
        return [
            ['scheme=SMTP', 'scheme=smtp', false],
            ['scheme=SMTPS', 'scheme=smtps', true],
            ['scheme=sMtP', 'scheme=smtp', false],
            ['scheme=%53MTP%53', 'scheme=smtps', true],
            ['scheme=%73mtp', 'scheme=%73mtp', false],
            ['%73cheme=SMTP', '%73cheme=smtp', false],
        ];
    }

    #[DataProvider('ambiguousUrlSchemeQueries')]
    public function test_ambiguous_scheme_query_is_not_rewritten_and_fails_before_transport_resolution(string $query): void
    {
        $url = 'smtp://private-user:private-password@mail.example.invalid:2525?'.$query;
        $settings = $this->loadBundledSmtpSettings('smtp', $url);
        $this->assertSame($url, $settings['url']);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => [...config('mail.mailers.smtp'), ...$settings]]);

        $this->assertSame(1, Artisan::call('security:production-check'));
        $this->assertStringContainsString('duplicate or array-valued scheme query options', Artisan::output());
        $this->assertStringNotContainsString('private-', Artisan::output());
        $this->assertStringNotContainsString('mail.example.invalid', Artisan::output());
    }

    public static function ambiguousUrlSchemeQueries(): array
    {
        return [
            ['scheme=SMTP&scheme=smtps'],
            ['scheme=smtp&%73cheme=SMTP'],
            ['scheme[]=SMTP'],
            ['scheme[protocol]=smtp&scheme=SMTP'],
            ['scheme=smtp&scheme[]=SMTPS'],
            ['scheme'],
            ['scheme%00extra=SMTP'],
        ];
    }

    public function test_unrecognized_query_scheme_and_fragment_only_scheme_are_preserved(): void
    {
        foreach ([
            'smtp://mail.example.invalid:2525?scheme=HTTPS&note=unchanged',
            'smtp://mail.example.invalid:2525#?scheme=SMTP',
        ] as $url) {
            $this->assertSame($url, $this->loadBundledSmtpSettings('smtp', $url)['url']);
        }
    }

    #[DataProvider('invalidEffectiveSmtpSchemes')]
    public function test_unsupported_effective_scheme_fails_without_printing_private_configuration(mixed $scheme, ?string $url): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => $scheme, 'mail.mailers.smtp.url' => $url]);

        $this->assertSame(1, Artisan::call('security:production-check'));
        $output = Artisan::output();
        $this->assertStringContainsString('effective SMTP scheme must be smtp or smtps', $output);
        $this->assertStringNotContainsString('private-', $output);
        $this->assertStringNotContainsString('mail.example.invalid', $output);
    }

    public static function invalidEffectiveSmtpSchemes(): array
    {
        return [
            ['SMTP', null], // Stale cached configuration must fail before any mail is queued.
            ['https', null],
            [false, null],
            [['smtp'], null],
            ['smtp', 'smtp://private-user:private-password@mail.example.invalid:2525?scheme=SMTP'],
            ['smtp', 'smtp://mail.example.invalid:2525?scheme=https'],
        ];
    }

    public function test_unsupported_mail_url_transport_cannot_bypass_smtp_checks(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.url' => 'smtps://private-user:private-password@mail.example.invalid:465']);

        $this->assertSame(1, Artisan::call('security:production-check'));
        $this->assertStringContainsString('SMTP mailer URL must use smtp://', Artisan::output());
        $this->assertStringNotContainsString('private-', Artisan::output());
    }

    public function test_valid_smtps_query_override_matches_the_real_transport(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.url' => 'smtp://mail.example.invalid:465?scheme=smtps']);

        $this->artisan('security:production-check')->assertSuccessful();
        $this->assertTrue(Mail::mailer()->getSymfonyTransport()->getStream()->isTLS());
    }

    public function test_invalid_transport_option_fails_during_local_construction_without_connecting(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.url' => 'smtp://private-user:private-password@mail.example.invalid:2525?local_domain[]=private-option']);

        $this->assertSame(1, Artisan::call('security:production-check'));
        $this->assertStringContainsString('SMTP transport could not be constructed', Artisan::output());
        $this->assertStringNotContainsString('private-', Artisan::output());
    }

    private function loadBundledSmtpSettings(string $scheme, ?string $url = null): array
    {
        // Evaluate the real config in a child process without booting .env or sending mail.
        $process = new Process([PHP_BINARY, '-r', <<<'PHP'
require 'vendor/autoload.php';
$config = require 'config/mail.php';
echo json_encode(array_intersect_key($config['mailers']['smtp'], array_flip(['scheme', 'url'])), JSON_THROW_ON_ERROR);
PHP], base_path(), ['MAIL_SCHEME' => $scheme, 'MAIL_URL' => $url ?? false]);

        return json_decode($process->mustRun()->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
