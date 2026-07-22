<?php

namespace Tests\Unit;

use App\Services\Gsb\IndividualPersonalInfoLookupService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IndividualPersonalInfoLookupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.base_url', 'https://api-gateway.stg.gsb.gov.jo:9443');
        config()->set('services.gsb.client_id', 'client-id');
        config()->set('services.gsb.client_secret', 'client-secret');
        config()->set('services.gsb.send_modee_headers', true);
        config()->set('services.gsb.send_ibm_headers', false);
        config()->set('services.gsb.bearer', '');
        config()->set('services.gsb.services.cspd_personal_info_masked', [
            'enabled' => true,
            'base_url' => 'https://api-gateway.stg.gsb.gov.jo:9443',
            'path' => '/porg-g2g/g2g/masked/api/person-info',
            'method' => 'GET',
            'psd_path' => '/porg-g2g/g2g/masked/api/psd',
        ]);
        config()->set('services.gsb.services.psd_basic_info_token', [
            'enabled' => false,
            'base_url' => 'https://api-gateway.stg.gsb.gov.jo:9443',
            'path' => '/porg-g2g/g2g/token/psd',
            'method' => 'POST',
            'bearer' => '',
        ]);
    }

    public function test_jordanian_lookup_uses_cspd_masked_and_maps_available_personal_fields(): void
    {
        Http::fake([
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/masked/api/person-info*' => Http::response([
                'code' => 200,
                'data' => [[
                    'ANAME1' => 'أحمد',
                    'ANAME2' => 'بسام',
                    'ANAME3' => 'أحمد',
                    'ANAME4' => 'حمد',
                    'BIRTH_DATE' => '03-OCT-98',
                    'BIRTH_PLACE' => 'عمان',
                    'GENDER' => '1',
                    'NATIONALITY_NAME' => 'أردني',
                    'MOBILE_NO' => '0790000000',
                    'EMAIL_ADDRESS' => 'person@example.test',
                    'ADDRESS' => 'عمان - الأردن',
                    'MOTHER_NAME' => 'اختبار أم أحمد',
                    'MARITAL_STATUS_DESC' => 'متزوج',
                ]],
            ]),
        ]);

        $result = app(IndividualPersonalInfoLookupService::class)->lookup('9981051142', 'jordanian');

        $this->assertTrue($result['ok']);
        $this->assertSame('أحمد بسام أحمد حمد', data_get($result, 'data.full_name'));
        $this->assertSame('أحمد', data_get($result, 'data.first_name'));
        $this->assertSame('1998-10-03', data_get($result, 'data.birth_date'));
        $this->assertSame('عمان', data_get($result, 'data.birth_place'));
        $this->assertSame('male', data_get($result, 'data.gender'));
        $this->assertSame('أردني', data_get($result, 'data.nationality'));
        $this->assertSame('0790000000', data_get($result, 'data.phone'));
        $this->assertSame('person@example.test', data_get($result, 'data.email'));
        $this->assertSame('عمان - الأردن', data_get($result, 'data.address'));
        $this->assertSame('married', data_get($result, 'data.marital_status'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/masked/api/person-info?id=9981051142'
            && $request->hasHeader('X-MODEE-Client-Id', 'client-id')
            && $request->hasHeader('X-MODEE-Client-Secret', 'client-secret'));
    }

    public function test_non_jordanian_lookup_prefers_psd_token_and_maps_available_personal_fields(): void
    {
        config()->set('services.gsb.services.psd_basic_info_token.enabled', true);
        config()->set('services.gsb.services.psd_basic_info_token.bearer', 'psd-access-token');

        Http::fake([
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/token/psd' => Http::response([
                'code' => 200,
                'data' => [
                    'FULL_NAME' => 'Test Foreign Person',
                    'DATE_OF_BIRTH' => '1990-06-15',
                    'PLACE_OF_BIRTH' => 'London',
                    'SEX_DESC' => 'Female',
                    'NATIONALITY_DESC' => 'British',
                    'PHONE_NO' => '00962790000000',
                    'EMAIL' => 'foreign@example.test',
                    'RESIDENCE_ADDRESS' => 'Amman',
                    'PASSPORT_NO' => 'P1234567',
                    'COUNTRY_OF_RESIDENCE' => 'Jordan',
                ],
            ]),
        ]);

        $result = app(IndividualPersonalInfoLookupService::class)->lookup('1234567', 'foreign');

        $this->assertTrue($result['ok']);
        $this->assertSame('Test Foreign Person', data_get($result, 'data.full_name'));
        $this->assertSame('1990-06-15', data_get($result, 'data.birth_date'));
        $this->assertSame('female', data_get($result, 'data.gender'));
        $this->assertSame('British', data_get($result, 'data.nationality'));
        $this->assertSame('P1234567', data_get($result, 'data.passport_number'));
        $this->assertSame('Jordan', data_get($result, 'data.country_of_residence'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/token/psd'
            && $request['nationalNo'] === '1234567'
            && $request->hasHeader('Authorization', 'Bearer psd-access-token'));
    }

    public function test_non_jordanian_lookup_falls_back_to_masked_psd_when_token_is_unavailable(): void
    {
        Http::fake([
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/masked/api/psd' => Http::response([
                'status' => 200,
                'data' => [
                    'NAME1' => 'محمد',
                    'NAME2' => 'علي',
                    'NAME3' => 'حسن',
                    'NAME4' => 'اختبار',
                    'BRTH_DATE' => '20/05/1985',
                    'SEX_CODE' => '1',
                    'NATIONALITY_DESCRIPTION' => 'سوري',
                ],
            ]),
        ]);

        $result = app(IndividualPersonalInfoLookupService::class)->lookup('7654321', 'arab');

        $this->assertTrue($result['ok']);
        $this->assertSame('محمد علي حسن اختبار', data_get($result, 'data.full_name'));
        $this->assertSame('1985-05-20', data_get($result, 'data.birth_date'));
        $this->assertSame('male', data_get($result, 'data.gender'));
        $this->assertSame('سوري', data_get($result, 'data.nationality'));
        $this->assertSame('gsb_cspd_personal_info_masked', data_get($result, 'meta.source'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/masked/api/psd'
            && $request['nationalNo'] === '7654321');
    }
}
