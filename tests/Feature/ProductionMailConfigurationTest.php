<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
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
}
