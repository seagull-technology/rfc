<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_guest_routes_have_named_rate_limiters(): void
    {
        $required = [
            'login.store' => 'throttle:login',
            'password.otp.send' => 'throttle:password-reset',
            'password.otp.store' => 'throttle:otp-verify',
            'password.otp.resend' => 'throttle:otp-resend',
            'password.store' => 'throttle:password-reset-complete',
            'otp.store' => 'throttle:otp-verify',
            'otp.resend' => 'throttle:otp-resend',
            'register.store' => 'throttle:registration',
            'register.company.lookup' => 'throttle:registration-lookup',
            'register.student.lookup' => 'throttle:registration-lookup',
            'register.organization.lookup' => 'throttle:registration-lookup',
        ];

        foreach ($required as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Missing route {$routeName}");
            $this->assertContains($middleware, $route->gatherMiddleware(), "Missing {$middleware} on {$routeName}");
        }
    }

    public function test_authenticated_routes_apply_the_write_rate_limiter(): void
    {
        foreach (['applications.store', 'scouting-requests.store', 'admin.contact-center.messages.store'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Missing route {$routeName}");
            $this->assertContains('throttle:authenticated-write', $route->gatherMiddleware());
        }
    }

    public function test_resource_intensive_routes_have_stricter_named_rate_limiters(): void
    {
        $required = [
            'applications.personal-details.lookup' => 'throttle:government-lookup',
            'applications.crew.identity.lookup' => 'throttle:government-lookup',
            'applications.documents.store' => 'throttle:content-submission',
            'applications.correspondence.store' => 'throttle:content-submission',
            'scouting-requests.correspondence.store' => 'throttle:content-submission',
            'authority.applications.correspondence.store' => 'throttle:content-submission',
            'admin.applications.correspondence.store' => 'throttle:content-submission',
            'admin.scouting-requests.correspondence.store' => 'throttle:content-submission',
            'admin.contact-center.messages.store' => 'throttle:content-submission',
            'admin.work-release-lookups.work-categories.store' => 'throttle:configuration-mutation',
            'admin.work-release-lookups.work-categories.update' => 'throttle:configuration-mutation',
            'admin.work-release-lookups.work-categories.status' => 'throttle:configuration-mutation',
            'admin.work-release-lookups.release-methods.store' => 'throttle:configuration-mutation',
            'admin.work-release-lookups.release-methods.update' => 'throttle:configuration-mutation',
            'admin.work-release-lookups.release-methods.status' => 'throttle:configuration-mutation',
        ];

        foreach ($required as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Missing route {$routeName}");
            $this->assertContains($middleware, $route->gatherMiddleware(), "Missing {$middleware} on {$routeName}");
        }
    }

    public function test_sensitive_submission_limiters_block_before_ten_rapid_attempts(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/security-rate-limit-check', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.64',
        ]);
        $request->setUserResolver(fn (): User => $user);

        foreach (['registration', 'registration-lookup', 'government-lookup', 'content-submission', 'configuration-mutation'] as $limiterName) {
            $limiter = RateLimiter::limiter($limiterName);

            $this->assertNotNull($limiter, "Missing limiter {$limiterName}");
            $limits = $limiter($request);
            $minuteLimits = collect(is_array($limits) ? $limits : [$limits])
                ->filter(fn ($limit): bool => $limit->decaySeconds === 60)
                ->pluck('maxAttempts')
                ->all();

            $this->assertNotEmpty($minuteLimits, "Missing per-minute ceiling for {$limiterName}");
            $this->assertLessThan(10, min($minuteLimits), "{$limiterName} permits ten rapid attempts");
        }
    }

    public function test_account_management_routes_require_explicit_user_permissions(): void
    {
        $readRoutes = [
            'admin.users.index',
            'admin.users.show',
        ];
        $writeRoutes = [
            'admin.users.create',
            'admin.users.store',
            'admin.users.update',
            'admin.users.password',
            'admin.users.status',
            'admin.users.delete',
            'admin.users.restore',
            'admin.users.memberships.store',
            'admin.users.memberships.roles.delete',
        ];

        foreach ($readRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Missing route {$routeName}");
            $this->assertContains('permission:users.view', $route->gatherMiddleware());
        }

        foreach ($writeRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Missing route {$routeName}");
            $this->assertContains('permission:users.manage', $route->gatherMiddleware());
        }
    }

    public function test_user_serialization_never_exposes_password_material(): void
    {
        $user = User::factory()->create([
            'password' => 'StrongPassword@123',
            'remember_token' => 'sensitive-remember-token',
        ]);

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
        $this->assertStringNotContainsString('StrongPassword@123', json_encode($serialized, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('sensitive-remember-token', json_encode($serialized, JSON_THROW_ON_ERROR));
    }

    public function test_public_swiper_bundle_is_not_the_vulnerable_legacy_release(): void
    {
        $bundle = file_get_contents(public_path('js/swiper-bundle.min.js'));

        $this->assertIsString($bundle);
        $this->assertStringContainsString('Swiper 12.1.2', $bundle);
        $this->assertStringNotContainsString('Swiper 6.8.4', $bundle);
    }

    public function test_login_is_limited_per_identifier(): void
    {
        $this->refreshApplicationWithLocale('en');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'identifier' => 'rate-limit@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'identifier' => 'rate-limit@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_login_route_ceiling_survives_payload_and_forwarded_ip_rotation(): void
    {
        $this->refreshApplicationWithLocale('en');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withHeader('X-Forwarded-For', "198.51.100.{$attempt}")
                ->post(route('login.store'), [
                    'identifier' => "rotated-{$attempt}@example.com",
                    'password' => 'wrong-password',
                ])
                ->assertRedirect();
        }

        $this->withHeader('X-Forwarded-For', '198.51.100.250')
            ->post(route('login.store'), [
                'identifier' => 'rotated-final@example.com',
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_shared_password_policy_requires_mixed_case_number_and_symbol(): void
    {
        $valid = Validator::make([
            'password' => 'SecurePassword@123',
        ], [
            'password' => ['required', PasswordPolicy::rule()],
        ]);
        $weak = Validator::make([
            'password' => 'password123',
        ], [
            'password' => ['required', PasswordPolicy::rule()],
        ]);

        $this->assertFalse($valid->fails());
        $this->assertTrue($weak->fails());
    }

    public function test_browser_logging_endpoint_is_not_registered(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('boost.browser-logs'));
    }

    public function test_production_check_rejects_the_current_development_runtime(): void
    {
        $this->artisan('security:production-check')
            ->expectsOutputToContain('Production security check failed.')
            ->expectsOutputToContain('APP_ENV must be production.')
            ->assertFailed();
    }

    #[DataProvider('sharedRegistrationQueueConnections')]
    public function test_production_check_accepts_a_hardened_runtime(?string $queueDatabaseConnection): void
    {
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
            'queue.connections.database.connection' => $queueDatabaseConnection,
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
        ]);

        $this->artisan('security:production-check')
            ->expectsOutputToContain('Production security checks passed.')
            ->assertSuccessful();
    }

    public static function sharedRegistrationQueueConnections(): array
    {
        return [[null], [''], ['sqlite']];
    }

    public function test_security_evidence_command_generates_private_sbom_and_control_manifest(): void
    {
        Storage::fake('local');

        $this->artisan('security:evidence', ['--label' => 'release-test'])
            ->expectsOutputToContain('Release fingerprint:')
            ->assertSuccessful();

        Storage::disk('local')->assertExists('security-evidence/latest.json');
        $evidence = json_decode(Storage::disk('local')->get('security-evidence/latest.json'), true);

        $this->assertSame('rfc-security-evidence-v1', $evidence['schema']);
        $this->assertSame('release-test', $evidence['release']['label']);
        $this->assertSame([], $evidence['template_asset_scan']['forbidden_runtime_urls']);
        $this->assertSame(0, $evidence['template_asset_scan']['unnonced_script_or_style_tags']);
        $this->assertContains('throttle:login', $evidence['route_controls']['login.store']);
        $this->assertContains('throttle:content-submission', $evidence['route_controls']['admin.contact-center.messages.store']);
        $this->assertArrayHasKey('session_cookie', $evidence['security_configuration']);
        $this->assertSame(hash_file('sha256', public_path('js/lodash.min.js')), $evidence['source_integrity']['public/js/lodash.min.js']);
        $this->assertTrue($evidence['verification_scope']['local_configuration_inventory_only']);
        $this->assertNotEmpty($evidence['sbom']['composer']);
        $this->assertNotEmpty($evidence['sbom']['npm']);
    }

    public function test_production_check_rejects_ineffective_rate_limit_storage_and_wildcard_proxies(): void
    {
        config(['cache.limiter' => 'array', 'security.trusted_proxies' => ['*']]);

        $this->artisan('security:production-check')
            ->expectsOutputToContain('The rate limiter must use a shared persistent cache store')
            ->expectsOutputToContain('TRUSTED_PROXIES must contain only explicit proxy IP addresses')
            ->assertFailed();
    }

    public function test_production_check_catches_a_missing_reported_message_rate_limiter(): void
    {
        $route = Route::getRoutes()->getByName('admin.contact-center.messages.store');
        $route->setAction(array_merge($route->getAction(), [
            'middleware' => array_values(array_filter(
                $route->getAction('middleware'),
                fn ($middleware) => $middleware !== 'throttle:content-submission',
            )),
        ]));

        $this->artisan('security:production-check')
            ->expectsOutputToContain('Route admin.contact-center.messages.store must use throttle:content-submission.')
            ->assertFailed();
    }

    public function test_production_check_rejects_synchronous_password_recovery_delivery(): void
    {
        config(['queue.default' => 'sync']);

        $this->artisan('security:production-check')
            ->expectsOutputToContain('QUEUE_CONNECTION must use a durable asynchronous queue')
            ->assertFailed();
    }

    public function test_production_check_rejects_a_different_registration_queue_connection_even_with_the_same_settings(): void
    {
        config([
            'database.connections.registration_queue_alias' => config('database.connections.sqlite'),
            'queue.connections.database.connection' => 'registration_queue_alias',
        ]);

        $this->artisan('security:production-check')
            ->expectsOutputToContain('DB_QUEUE_CONNECTION empty or exactly matching the default application database connection name')
            ->assertFailed();
    }

    public function test_production_check_rejects_a_database_queue_name_with_a_non_database_driver(): void
    {
        config(['queue.connections.database.driver' => 'sync']);

        $this->artisan('security:production-check')
            ->expectsOutputToContain('Registration delivery requires the database queue driver')
            ->assertFailed();
    }
}
