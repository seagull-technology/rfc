<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        ];

        foreach ($required as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Missing route {$routeName}");
            $this->assertContains($middleware, $route->gatherMiddleware(), "Missing {$middleware} on {$routeName}");
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

    public function test_production_check_accepts_a_hardened_runtime(): void
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
        $this->assertNotEmpty($evidence['sbom']['composer']);
        $this->assertNotEmpty($evidence['sbom']['npm']);
    }
}
