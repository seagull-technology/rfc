<?php

namespace Tests\Feature;

use App\Services\Gsb\CrewIdentityVerificationService;
use App\Services\Gsb\IndividualPersonalInfoLookupService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GsbCrewTokenIntegrationTest extends TestCase
{
    private const BASE = 'https://api-gateway.g2b.gsb.gov.jo:9443';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config()->set('security.outbound_http.allowed_hosts', ['api-gateway.g2b.gsb.gov.jo']);
        config()->set('security.outbound_http.allowed_ports', [443, 9443]);
        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.client_id', 'crew-client');
        config()->set('services.gsb.client_secret', 'crew-secret');
        config()->set('services.gsb.send_modee_headers', true);
        config()->set('services.gsb.send_ibm_headers', false);
        config()->set('services.gsb.force_ip', '');
        foreach (['cspd_personal_info_token' => ['person-info', 'GET'], 'psd_basic_info_token' => ['psd', 'POST']] as $service => [$path, $method]) {
            config()->set("services.gsb.services.$service", [
                'enabled' => true,
                'base_url' => self::BASE,
                'path' => '/porg-gsb/g2b-catalog/token/'.$path,
                'method' => $method,
                'bearer' => 'valid-test-token',
            ]);
        }
        // Even an enabled legacy route must never be called by a token integration.
        config()->set('services.gsb.services.cspd_personal_info_masked', [
            'enabled' => true, 'base_url' => self::BASE,
            'path' => '/porg-g2g/g2g/masked/api/person-info',
            'psd_path' => '/porg-g2g/g2g/masked/api/psd',
        ]);
    }

    public function test_cspd_contract_sends_no_identifier_and_requires_matching_returned_identity(): void
    {
        Http::fake(['*' => Http::response(['data' => ['NAT_NO' => '9000000001', 'ANAME1' => 'Test', 'ANAME4' => 'Person']])]);
        $result = app(IndividualPersonalInfoLookupService::class)->lookup('9000000001', 'jordanian');
        $this->assertTrue($result['ok']);
        $this->assertSame('gsb_cspd_personal_info_token', $result['meta']['source']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->url() === self::BASE.'/porg-gsb/g2b-catalog/token/person-info'
            && $r->data() === []
            && $r->hasHeader('Authorization', 'Bearer valid-test-token')
            && $r->hasHeader('X-MODEE-Client-Id', 'crew-client')
            && $r->hasHeader('X-MODEE-Client-Secret', 'crew-secret'));
    }

    public function test_cspd_cannot_verify_a_different_person_or_a_response_without_identity(): void
    {
        Http::fakeSequence()
            ->push(['data' => ['NAT_NO' => '9999999999', 'FULL_NAME' => 'Another Person']])
            ->push(['data' => ['FULL_NAME' => 'Unknown Person']]);
        foreach ([1, 2] as $attempt) {
            $result = app(CrewIdentityVerificationService::class)->lookup('9000000001', 'jordanian', 41);
            $this->assertFalse($result['ok']);
            $this->assertSame('IDENTITY_NOT_CONFIRMED', $result['error']);
            $this->assertArrayNotHasKey('data', $result);
            $this->assertArrayNotHasKey('proof', $result);
        }
    }

    public function test_missing_tokens_do_not_call_legacy_routes_or_create_pending_proofs(): void
    {
        Http::fake();
        foreach (['jordanian' => 'cspd_personal_info_token', 'foreign' => 'psd_basic_info_token'] as $category => $service) {
            config()->set("services.gsb.services.$service.bearer", '');
            $result = app(CrewIdentityVerificationService::class)->lookup('9000000001', $category, 41);
            $this->assertFalse($result['ok']);
            $this->assertSame('AUTHORIZATION_REQUIRED', $result['error']);
            $this->assertArrayNotHasKey('proof', $result);
        }
        Http::assertNothingSent();
    }

    public function test_psd_sends_the_documented_body_and_only_calls_current_endpoint(): void
    {
        Http::fake(['*' => Http::response(['data' => ['FULL_NAME' => 'Test Foreign Person', 'DATE_OF_BIRTH' => '1990-06-15']])]);
        $result = app(IndividualPersonalInfoLookupService::class)->lookup('1234567', 'foreign');
        $this->assertTrue($result['ok']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/porg-gsb/g2b-catalog/token/psd'
            && $r->data() === ['nationalNo' => '1234567']
            && $r->hasHeader('Authorization', 'Bearer valid-test-token'));
    }

    public function test_expired_token_does_not_reuse_previous_success_or_fall_back(): void
    {
        Http::fakeSequence()
            ->push(['data' => ['FULL_NAME' => 'Test Foreign Person']])
            ->push(['httpCode' => '401', 'httpMessage' => 'Unauthorized'], 401)
            ->push(['httpCode' => '401', 'httpMessage' => 'Unauthorized'], 401);
        $service = app(CrewIdentityVerificationService::class);
        $this->assertTrue($service->lookup('1234567', 'foreign', 41)['ok']);
        $result = $service->lookup('1234567', 'foreign', 41);
        $this->assertFalse($result['ok']);
        $this->assertSame('AUTHORIZATION_REQUIRED', $result['error']);
        $this->assertArrayNotHasKey('proof', $result);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/masked/'));
    }

    public function test_route_or_transport_failure_does_not_verify_or_mint_a_pending_proof(): void
    {
        Http::fake(['*' => Http::response(['httpMessage' => 'Not Found', 'moreInformation' => 'The requested URL was not found on this server'], 404)]);
        $result = app(CrewIdentityVerificationService::class)->lookup('9000000001', 'jordanian', 41);
        $this->assertFalse($result['ok']);
        $this->assertSame('TOKEN_LOOKUP_FAILED', $result['error']);
        $this->assertArrayNotHasKey('proof', $result);
    }
}
