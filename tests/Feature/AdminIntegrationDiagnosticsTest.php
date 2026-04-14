<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminIntegrationDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_integration_diagnostics_page(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.integrations.index'));

        $response
            ->assertOk()
            ->assertSeeText('Integration Diagnostics')
            ->assertSeeText('OTP SMS Gateway')
            ->assertSeeText('Government Company Registry');
    }

    public function test_super_admin_can_run_sms_diagnostic(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        Cache::forget('gov_sms_token');

        config()->set('services.gov_sms.base', 'https://bulk-sms.gov.jo');
        config()->set('services.gov_sms.username', 'user');
        config()->set('services.gov_sms.password', 'pass');
        config()->set('services.gov_sms.header', 'RFC');

        Http::fake([
            'https://bulk-sms.gov.jo/authenticate' => Http::response(['token' => 'test-token'], 200),
            'https://bulk-sms.gov.jo/sendSmsNotifications' => Http::response(['ok' => true], 200),
        ]);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.integrations.sms-test'), [
            'phone' => '0791234567',
            'message' => 'RFC integration test message.',
        ]);

        $response
            ->assertRedirect(route('admin.integrations.index'))
            ->assertSessionHas('diagnostics.sms');
    }

    public function test_super_admin_can_run_company_registry_diagnostic(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        config()->set('services.gov_company_registry.enabled', true);
        config()->set('services.gov_company_registry.host', 'registry.test');
        config()->set('services.gov_company_registry.port', 9443);
        config()->set('services.gov_company_registry.path', '/company');

        Http::fake([
            'https://registry.test:9443/company*' => Http::response([
                'CompanyInfo' => [
                    'Company' => 'Jordan Studio Productions',
                    'Mobile' => '0791234567',
                    'Email' => 'info@jordanstudio.test',
                ],
                'Files' => [
                    [
                        'Table1Values' => [
                            'Registration Number' => 'REG-4455',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.integrations.company-registry-test'), [
            'organization_national_id' => 'ORG-1001',
            'organization_registration_no' => 'REG-4455',
        ]);

        $response
            ->assertRedirect(route('admin.integrations.index'))
            ->assertSessionHas('diagnostics.company_registry');
    }
}
