<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Gsb\CrewIdentityVerificationService;
use App\Services\Gsb\IndividualPersonalInfoLookupService;
use App\Services\Gsb\StandardPersonalInfoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GsbStandardCrewIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('security.outbound_http.allowed_hosts', ['api-gateway.g2b.gsb.gov.jo']);
        config()->set('security.outbound_http.allowed_ports', [443, 9443]);
        config()->set('services.gsb.force_ip', '');
        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.client_id', 'standard-client');
        config()->set('services.gsb.client_secret', 'standard-secret');
        config()->set('services.gsb.bearer', 'unrelated-token');
        config()->set('services.gsb.basic_user', 'unrelated-basic-user');
        config()->set('services.gsb.basic_pass', 'unrelated-basic-password');
        foreach (['cspd_personal_info_standard', 'psd_personal_standard'] as $service) {
            config()->set("services.gsb.services.$service", [
                'enabled' => true,
                'base_url' => 'https://api-gateway.g2b.gsb.gov.jo:9443',
                'path' => $service === 'cspd_personal_info_standard' ? '/porg-gsb/g2b-catalog/cspd/web/api-v2/person-info' : '/porg-gsb/g2b-catalog/api-stander/psd',
                'payload_in' => $service === 'cspd_personal_info_standard' ? 'query' : 'body',
                'send_ibm_headers' => true,
                'send_modee_headers' => false,
                'send_basic_auth' => false,
                'bearer' => '',
            ]);
        }
    }

    public function test_cspd_requires_all_three_parameters_and_returns_a_bound_proof(): void
    {
        Http::fake(['*' => Http::response(['status' => 200, 'data' => [
            'NAT_NO' => '9000000001', 'ANAME1' => 'اختبار', 'ANAME2' => 'أحمد',
            'ANAME3' => 'محمد', 'ANAME4' => 'علي', 'BIRTHDT' => '1990-01-02', 'SEX' => 1,
        ]])]);
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('applications.crew.identity.lookup'), [
            'nationality_category' => 'jordanian', 'identifier' => '9000000001',
            'birth_date' => '1990-01-02', 'civil_number' => '600/084',
        ])->assertOk()->assertJsonPath('status', 'verified')
            ->assertJsonPath('data.full_name', 'اختبار أحمد محمد علي')
            ->assertJsonPath('data.birth_date', '1990-01-02')
            ->assertJsonPath('data.gender', 'male');
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'POST'
                && $query === ['NAT_NO' => '9000000001', 'BIRTHDT' => '1/2/1990', 'CIV_NO' => '600/084']
                && $request->hasHeader('X-IBM-Client-Id', 'standard-client')
                && $request->hasHeader('X-IBM-Client-Secret', 'standard-secret')
                && ! $request->hasHeader('Authorization')
                && ! $request->hasHeader('X-MODEE-Client-Id');
        });
        $proof = app(CrewIdentityVerificationService::class)->consumeProof($response->json('proof'), 'jordanian', '9000000001', $user->getKey());
        $this->assertSame('1990-01-02', $proof['identity']['birth_date']);
        $this->assertArrayNotHasKey('civil_number', $proof);
        $this->assertNull(app(CrewIdentityVerificationService::class)->consumeProof($response->json('proof'), 'jordanian', '9000000002', $user->getKey()));
    }

    #[DataProvider('invalidCivilNumbers')]
    public function test_civil_number_requires_exactly_six_digits_without_sending_invalid_values(string $civilNumber): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create())->postJson(route('applications.crew.identity.lookup'), [
            'nationality_category' => 'jordanian', 'identifier' => '9000000001',
            'birth_date' => '1990-01-02', 'civil_number' => $civilNumber,
        ])->assertUnprocessable()->assertJsonValidationErrors('civil_number');
        $result = app(StandardPersonalInfoService::class)->lookup('9000000001', 'jordanian', '1990-01-02', $civilNumber);
        $this->assertSame('INVALID_CIVIL_NUMBER', $result['error']);
        Http::assertNothingSent();
    }

    public static function invalidCivilNumbers(): array
    {
        return array_map(fn ($value) => [$value], ['600/08', '600/0847', '6000847', '60/0084', '600-084', '600//084', '600/08x']);
    }

    #[DataProvider('validCivilNumbers')]
    public function test_civil_number_preserves_leading_zeros_and_sends_the_canonical_slash(string $civilNumber): void
    {
        Http::fake(['*' => Http::response(['status' => 200, 'data' => [
            'NAT_NO' => '9000000001', 'ANAME1' => 'Test', 'BIRTHDT' => '1990-01-02',
        ]])]);
        $this->actingAs(User::factory()->create())->postJson(route('applications.crew.identity.lookup'), [
            'nationality_category' => 'jordanian', 'identifier' => '9000000001',
            'birth_date' => '1990-01-02', 'civil_number' => $civilNumber,
        ])->assertOk()->assertJsonPath('status', 'verified');
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['CIV_NO'] ?? null) === '006/084';
        });
    }

    public static function validCivilNumbers(): array
    {
        return [['006/084'], ['006084']];
    }

    public function test_missing_or_invalid_verification_fields_never_reach_gsb(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('applications.crew.identity.lookup'), [
            'nationality_category' => 'jordanian', 'identifier' => '9000000001',
        ])->assertUnprocessable()->assertJsonValidationErrors(['birth_date', 'civil_number']);
        $this->postJson(route('applications.crew.identity.lookup'), [
            'nationality_category' => 'foreign', 'identifier' => '1000000001',
            'birth_date' => '1990-02-30',
        ])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
        Http::assertNothingSent();
    }

    public function test_psd_boolean_success_maps_english_names_birth_date_gender_and_country(): void
    {
        Http::fake(['*' => Http::response(['status' => true, 'data' => [[
            'e_FULL_NAME' => 'Test Foreign Person', 'FULL_NAME' => 'اسم عربي للاختبار',
            'FIRST_NAME' => 'اختبار', 'FATHER_NAME' => 'أجنبي', 'FAMILY_NAME' => 'شخص',
            'E_FIRST_NAME' => 'Test', 'E_FATHER_NAME' => 'Foreign', 'E_FAMILY_NAME' => 'Person',
            'BIRTH_DATE' => '1990-01-02', 'GENDER' => '2',
            'country' => [['A_DESCRIPTION' => 'امريكا', 'E_DESCRIPTION' => 'USA']],
        ]]])]);
        $result = app(IndividualPersonalInfoLookupService::class)->lookup('1000000001', 'foreign', '1990-01-02');
        $this->assertTrue($result['ok']);
        $this->assertSame('Test Foreign Person', $result['data']['full_name']);
        $this->assertSame('Test', $result['data']['first_name']);
        $this->assertSame('Foreign', $result['data']['father_name']);
        $this->assertSame('Person', $result['data']['family_name']);
        $this->assertSame('1990-01-02', $result['data']['birth_date']);
        $this->assertSame('female', $result['data']['gender']);
        $this->assertSame('امريكا', $result['data']['nationality']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->data() === ['nationalNo' => '1000000001', 'BIRTH_DATE' => '1990-01-02']
            && $request->hasHeader('X-IBM-Client-Id', 'standard-client')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_psd_prefers_the_full_year_birth_date_over_the_ambiguous_short_year(): void
    {
        Http::fake(['*' => Http::response(['status' => true, 'data' => [[
            'e_FULL_NAME' => 'Test Foreign Person',
            'BIRTH_DATE' => '19-JUN-67', 'BIRTH_DATEY' => '1967', 'BIRTH_DATE22' => '1967-06-19',
        ]]])]);
        $result = app(IndividualPersonalInfoLookupService::class)->lookup('1000000001', 'foreign', '1967-06-19');

        $this->assertTrue($result['ok']);
        $this->assertSame('1967-06-19', $result['data']['birth_date']);
    }

    public function test_cspd_rejects_another_persons_data(): void
    {
        Http::fake(['*' => Http::response(['status' => 200, 'data' => [
            'NAT_NO' => '9000000002', 'ANAME1' => 'Test', 'BIRTHDT' => '1990-01-02',
        ]])]);
        $result = app(CrewIdentityVerificationService::class)->lookup('9000000001', 'jordanian', 1, '1990-01-02', '600/084');
        $this->assertFalse($result['ok']);
        $this->assertSame('IDENTITY_NOT_CONFIRMED', $result['error']);
        $this->assertArrayNotHasKey('proof', $result);
    }

    #[DataProvider('failedResponses')]
    public function test_failed_standard_lookup_never_creates_pending_or_verified_proof(int $http, array $body): void
    {
        Http::fake(['*' => Http::response($body, $http)]);
        $result = app(CrewIdentityVerificationService::class)->lookup('1000000001', 'foreign', 1, '1990-01-02');
        $this->assertFalse($result['ok']);
        $this->assertSame('unverified', $result['status']);
        $this->assertArrayNotHasKey('proof', $result);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api-stander/psd'));
    }

    public static function failedResponses(): array
    {
        return [
            'not found' => [404, ['status' => '404', 'message' => 'Data Not Found.']],
            'invalid fields' => [200, ['status' => 400, 'data' => null]],
            'unauthorized' => [401, ['status' => '401']],
            'backend failure' => [500, ['status' => 'error']],
            'logical false' => [200, ['status' => false, 'data' => [['FULL_NAME' => 'Must not verify']]]],
            'empty data' => [200, ['status' => true, 'data' => []]],
        ];
    }
}
