<?php

namespace Tests\Feature;

use App\Services\CompanyRegistrationLookupService;
use App\Services\Gsb\CcdCompanyService;
use App\Services\Gsb\MitEstablishmentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GsbProductionCompanyLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.gsb.enabled' => true,
            'services.gsb.base_url' => 'https://api-gateway.g2b.gsb.gov.jo:9443',
            'services.gsb.client_id' => 'test-id',
            'services.gsb.client_secret' => 'test-secret',
            'services.gsb.force_ip' => '',
            'services.gsb.services.ccd_company.enabled' => true,
            'services.gsb.services.ccd_company.base_url' => 'https://api-gateway.g2b.gsb.gov.jo:9443',
            'services.gsb.services.ccd_company.path' => '/porg-gsb/g2b-catalog/api/companies/CompanybyNoAndRegDate',
            'services.gsb.services.ccd_company.method' => 'GET',
            'services.gsb.services.mit_services.enabled' => false,
            'security.outbound_http.allowed_hosts' => ['api-gateway.g2b.gsb.gov.jo'],
            'security.outbound_http.allowed_ports' => [443, 9443],
        ]);
        Http::preventStrayRequests();
    }

    public function test_production_lookup_sends_required_query_parameters_and_binds_date_to_session(): void
    {
        Http::fake(['*' => Http::response(['company_name' => 'Test Company', 'registration_date' => '2020-05-27'])]);
        $service = app(CompanyRegistrationLookupService::class);
        $result = $service->lookup('000000000', '2020-05-27');
        $this->assertTrue($result['ok']);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/CompanybyNoAndRegDate?')
            && $request['NationalNo'] === '000000000'
            && $request['RegDate'] === '5/27/2020');
        $state = $service->sessionStateFromLookup($result);
        $this->assertTrue($service->lookupStateMatches($state, '000000000', '2020-05-27'));
        $this->assertFalse($service->lookupStateMatches($state, '000000000', '2020-05-28'));
        $this->assertFalse($service->lookupStateMatches($state, '000000001', '2020-05-27'));
        $this->assertFalse($service->lookupStateMatches($state, '000000000'));
    }

    public function test_missing_or_impossible_date_does_not_contact_gateway(): void
    {
        Http::fake();
        foreach ([null, '', '2020-02-30', '05/27/2020'] as $date) {
            $result = app(CcdCompanyService::class)->lookup('000000000', $date);
            $this->assertSame('INVALID_REGISTRATION_DATE', $result['error']);
        }
        Http::assertNothingSent();
    }

    public function test_legacy_endpoint_still_accepts_number_only(): void
    {
        config(['services.gsb.services.ccd_company.path' => '/porg-g2g/g2g/api/companies/CompanybyNo/{nationalNo}']);
        Http::fake(['*' => Http::response(['company_name' => 'Test Company'])]);
        $this->assertTrue(app(CcdCompanyService::class)->lookup('000000000')['ok']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/CompanybyNo/000000000') && $request->data() === []);
    }

    public function test_authentication_failure_is_not_cached_and_date_changes_do_not_reuse_records(): void
    {
        Http::fake(['*' => Http::sequence()->push(['error' => 'Unauthorized'], 401)->push(['error' => 'Unauthorized'], 401)
            ->push(['company_name' => 'Test Company'])->push(['company_name' => 'Another Company'])]);
        $service = app(CcdCompanyService::class);
        $this->assertFalse($service->lookup('000000000', '2020-05-27')['ok']);
        $this->assertTrue($service->lookup('000000000', '2020-05-27')['ok']);
        $this->assertSame('Another Company', $service->lookup('000000000', '2020-05-28')['data']['entity_name']);
        Http::assertSentCount(4);
    }

    public function test_disabled_production_services_never_verify_mock_company_data(): void
    {
        $this->app->instance('env', 'production');
        config(['services.gsb.services.ccd_company.enabled' => false, 'services.gsb.enabled' => false]);
        $this->assertFalse(app(CompanyRegistrationLookupService::class)->lookup('000000000')['ok']);
    }

    public function test_lookup_endpoint_requires_date_and_discards_previous_verification(): void
    {
        Http::fake();
        $this->withSession([CompanyRegistrationLookupService::SESSION_KEY => ['ok' => true]])
            ->postJson(route('register.company.lookup'), ['registration_number' => '000000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('company_lookup_registration_date')
            ->assertSessionMissing(CompanyRegistrationLookupService::SESSION_KEY);
        Http::assertNothingSent();
    }

    public function test_company_fallback_passes_registration_date_to_replacement_mit_api(): void
    {
        $this->configureStandardMit();
        Http::fake([
            '*CompanybyNoAndRegDate*' => Http::response(['success' => 0, 'message' => 'No matching company', 'data' => null], 404),
            '*GetIndividualInfo*' => Http::response(['code' => 200, 'data' => [
                'strRegistryName' => 'Test Establishment', 'dtmRegistryDate' => '02/06/2020',
            ]]),
        ]);

        $this->postJson(route('register.company.lookup'), [
            'registration_number' => '000000000', 'company_lookup_registration_date' => '2020-06-02',
        ])->assertOk()->assertJsonPath('meta.source', 'gsb_mit_services')
            ->assertJsonPath('data.company_registration_date', '2020-06-02')
            ->assertSessionHas(CompanyRegistrationLookupService::SESSION_KEY.'.lookup_registration_date', '2020-06-02');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/IndividualInfo/GetIndividualInfo?')
            && $request['Establishment_ID'] === '000000000'
            && $request['REG_Date'] === '2020-06-02'
            && ! isset($request['nationalNo']));
    }

    public function test_standard_mit_requires_date_even_when_ccd_uses_legacy_endpoint(): void
    {
        $this->configureStandardMit();
        config(['services.gsb.services.ccd_company.path' => '/api/CompanybyNo/{nationalNo}']);
        Http::fake();
        $this->assertTrue(app(CompanyRegistrationLookupService::class)->requiresRegistrationDate());
        foreach ([null, '', '2020-02-30', '02/06/2020'] as $date) {
            $this->assertSame('INVALID_REGISTRATION_DATE', app(MitEstablishmentService::class)->lookup('000000000', $date)['error']);
        }
        $this->postJson(route('register.company.lookup'), ['registration_number' => '000000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('company_lookup_registration_date');
        Http::assertNothingSent();
    }

    public function test_mit_production_record_populates_company_fields_without_owner_identity(): void
    {
        $this->configureStandardMit();
        Http::fake([
            '*CompanybyNoAndRegDate*' => Http::response(['message' => 'No matching company', 'data' => null], 404),
            '*GetIndividualInfo*' => Http::response(['code' => 200, 'message' => 'Data retrieved successfully.', 'data' => [
                'indv_NATIONAL_ID' => 100000001,
                'comM_NAME' => 'Test Commercial Name', 'regcn' => 'Test Registry Name',
                'reG_DATE_A' => '02/06/2024', 'reG_DATE_E' => '2024-06-02T11:59:22',
                'reG_CAPITAL' => 5000, 'currenT_CAPITAL' => 7500,
                'governorate' => 'Amman', 'reG_NO' => '123456', 'status' => 'Active',
                'ownername' => 'Private Owner', 'owneR_NATIONALID' => 9999999999,
            ]]),
        ]);

        $this->postJson(route('register.company.lookup'), [
            'registration_number' => '100000001', 'company_lookup_registration_date' => '2024-06-02',
        ])->assertOk()->assertJsonPath('meta.source', 'gsb_mit_services')
            ->assertJsonPath('data.entity_name', 'Test Commercial Name')
            ->assertJsonPath('data.registration_number', '100000001')
            ->assertJsonPath('data.company_registration_date', '2024-06-02')
            ->assertJsonPath('data.company_capital', '7500')
            ->assertJsonPath('data.organization_type', 'مؤسسة فردية')
            ->assertJsonPath('data.governorate', 'Amman')
            ->assertJsonPath('data.commercial_registration_number', '123456')
            ->assertJsonPath('data.registry_status', 'Active')
            ->assertJsonMissingPath('data.ownername')->assertJsonMissingPath('data.owneR_NATIONALID')
            ->assertSessionHas(CompanyRegistrationLookupService::SESSION_KEY.'.lookup_registration_date', '2024-06-02');
    }

    public function test_mit_production_mapping_uses_registry_name_and_fallback_fields(): void
    {
        $this->configureStandardMit();
        Http::fake(['*' => Http::response(['data' => [
            'comM_NAME' => '-', 'regcn' => 'Test Registry Name',
            'reG_DATE_A' => null, 'reG_DATE_E' => '2024-06-02T11:59:22',
            'currenT_CAPITAL' => null, 'reG_CAPITAL' => 0,
        ]])]);

        $result = app(MitEstablishmentService::class)->lookup('100000001', '2024-06-02');
        $this->assertTrue($result['ok']);
        $this->assertSame('Test Registry Name', $result['data']['entity_name']);
        $this->assertSame('2024-06-02', $result['data']['company_registration_date']);
        $this->assertSame('0', $result['data']['company_capital']);
    }

    public function test_mit_does_not_use_owner_name_as_the_establishment_name(): void
    {
        $this->configureStandardMit();
        Http::fake(['*' => Http::response(['data' => [
            'comM_NAME' => null, 'regcn' => '', 'ownername' => 'Private Owner',
        ]])]);
        $this->assertSame('NOT_FOUND', app(MitEstablishmentService::class)->lookup('100000001', '2024-06-02')['error']);
    }

    public function test_mit_cache_cannot_reuse_verification_with_a_different_date_or_endpoint(): void
    {
        $this->configureStandardMit();
        Http::fake(['*' => Http::response(['data' => ['strRegistryName' => 'Test Establishment']])]);
        $service = app(MitEstablishmentService::class);
        $this->assertTrue($service->lookup('000000000', '2020-06-02')['ok']);
        $this->assertTrue($service->lookup('000000000', '2020-06-02')['ok']);
        Http::assertSentCount(1);
        $service->lookup('000000000', '2020-06-03');
        config(['services.gsb.services.mit_services.path' => '/different/GetIndividualInfo']);
        $service->lookup('000000000', '2020-06-02');
        Http::assertSentCount(3);
    }

    public function test_mit_gateway_route_failure_is_not_a_missing_record_or_cached_failure(): void
    {
        $this->configureStandardMit();
        Http::fake(['*' => Http::sequence()
            ->push(['httpCode' => '404', 'moreInformation' => 'The requested URL was not found on this server'], 404)
            ->push(['httpCode' => '404', 'moreInformation' => 'The requested URL was not found on this server'], 404)
            ->push(['code' => 200, 'data' => ['strRegistryName' => 'Test Establishment']])]);
        $service = app(MitEstablishmentService::class);
        $this->assertSame('LOOKUP_FAILED', $service->lookup('000000000', '2020-06-02')['error']);
        $this->assertTrue($service->lookup('000000000', '2020-06-02')['ok']);
    }

    public function test_mit_business_not_found_is_reported_as_a_missing_record(): void
    {
        $this->configureStandardMit();
        Http::fake(['*' => Http::response(['code' => 404, 'message' => 'No data found.', 'data' => null], 404)]);
        $this->assertSame('NOT_FOUND', app(MitEstablishmentService::class)->lookup('000000000', '2020-06-02')['error']);
    }

    private function configureStandardMit(): void
    {
        config([
            'services.gsb.services.mit_services.enabled' => true,
            'services.gsb.services.mit_services.base_url' => 'https://api-gateway.g2b.gsb.gov.jo:9443',
            'services.gsb.services.mit_services.path' => '/porg-gsb/g2b-catalog/api/IndividualInfo/GetIndividualInfo',
            'services.gsb.services.mit_services.method' => 'GET',
        ]);
    }
}
