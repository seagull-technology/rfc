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
            ->assertSeeText('Government Service Bus')
            ->assertSeeText('MOHE-SANAD')
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

    public function test_super_admin_can_run_mohe_sanad_diagnostic(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        Cache::forget('gsb:mohe_sanad:current:9876543210:2001-01-02');

        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.client_id', 'client-id');
        config()->set('services.gsb.client_secret', 'client-secret');
        config()->set('services.gsb.services.mohe_sanad.enabled', true);
        config()->set('services.gsb.services.mohe_sanad.base_url', 'https://api-gateway.stg.gsb.gov.jo:9443');
        config()->set('services.gsb.services.mohe_sanad.path', '/porg-g2g/g2g/newstandard/api/MoheStandard');

        Http::fake([
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/newstandard/api/MoheStandard' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => [[
                    'STUDENT_NAME' => 'RFC Student',
                    'BIRTH_DATE' => '2001-01-02',
                    'gender_desc' => 'Male',
                    'NATIONALITY' => 'Jordanian',
                    'INSTITUTE_NAME' => 'University of Jordan',
                    'major' => 'Film Studies',
                    'degree' => 'Bachelor',
                    'student_status' => 'currently studying',
                ]],
            ], 200),
        ]);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.integrations.mohe-student-test'), [
            'national_id' => '9876543210',
            'birth_date' => '2001-01-02',
        ]);

        $response
            ->assertRedirect(route('admin.integrations.index'))
            ->assertSessionHas('diagnostics.mohe_sanad', fn (array $result): bool => ($result['ok'] ?? false)
                && data_get($result, 'data.university_name') === 'University of Jordan');

        Http::assertSent(fn ($request): bool => $request->hasHeader('X-MODEE-Client-Id', 'client-id')
            && $request->hasHeader('X-MODEE-Client-Secret', 'client-secret')
            && $request['nationalNo'] === '9876543210'
            && $request['birthDate'] === '2001-01-02');
    }
}
