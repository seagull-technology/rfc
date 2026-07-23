<?php

namespace Tests\Unit;

use App\Services\Gsb\CrewIdentityVerificationService;
use App\Services\Gsb\IndividualPersonalInfoLookupService;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class CrewIdentityVerificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-22 12:00:00');
        config()->set('services.gsb.crew_verification_minutes', 120);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_verified_jordanian_result_returns_a_user_bound_proof_with_whitelisted_identity_data(): void
    {
        $lookup = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $lookup->shouldReceive('lookup')
            ->once()
            ->with('9981051142', 'jordanian')
            ->andReturn([
                'ok' => true,
                'data' => [
                    'full_name' => 'أحمد بسام أحمد حمد',
                    'first_name' => 'أحمد',
                    'father_name' => 'بسام',
                    'grandfather_name' => 'أحمد',
                    'family_name' => 'حمد',
                    'birth_date' => '1998-10-03',
                    'gender' => 'male',
                    'nationality' => 'أردني',
                    'phone' => '0790000000',
                    'raw_response' => ['must_not_be_persisted' => true],
                ],
                'meta' => ['source' => 'gsb_cspd_personal_info_masked'],
            ]);

        $service = new CrewIdentityVerificationService($lookup);
        $result = $service->lookup('998-105-1142', 'jordanian', 41);

        $this->assertTrue($result['ok']);
        $this->assertSame(CrewIdentityVerificationService::STATUS_VERIFIED, $result['status']);
        $this->assertSame('gsb_cspd_personal_info_masked', $result['source']);
        $this->assertArrayNotHasKey('phone', $result['data']);
        $this->assertArrayNotHasKey('raw_response', $result['data']);

        $proof = $service->consumeProof($result['proof'], 'jordanian', '9981051142', 41);

        $this->assertNotNull($proof);
        $this->assertSame('أحمد بسام أحمد حمد', data_get($proof, 'identity.full_name'));
        $this->assertSame(CrewIdentityVerificationService::STATUS_VERIFIED, $proof['status']);
        $this->assertNull($service->consumeProof($result['proof'], 'jordanian', '9981051142', 42));
        $this->assertNull($service->consumeProof($result['proof'], 'jordanian', '9981051143', 41));
        $this->assertNull($service->consumeProof($result['proof'], 'foreign', '9981051142', 41));
    }

    public function test_verified_foreign_result_uses_psd_source_and_individual_number(): void
    {
        $lookup = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $lookup->shouldReceive('lookup')
            ->once()
            ->with('7654321', 'foreign')
            ->andReturn([
                'ok' => true,
                'data' => [
                    'full_name' => 'Test Foreign Person',
                    'birth_date' => '1990-06-15',
                    'gender' => 'female',
                    'nationality' => 'British',
                ],
                'meta' => ['source' => 'gsb_psd_basic_info_token'],
            ]);

        $service = new CrewIdentityVerificationService($lookup);
        $result = $service->lookup('7654321', 'foreign', 9);

        $this->assertTrue($result['ok']);
        $this->assertSame(CrewIdentityVerificationService::STATUS_VERIFIED, $result['status']);
        $this->assertSame('gsb_psd_basic_info_token', $result['source']);
        $this->assertNotNull($service->consumeProof($result['proof'], 'foreign', '7654321', 9));
    }

    public function test_temporary_service_failure_returns_a_signed_pending_recheck_proof(): void
    {
        $lookup = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $lookup->shouldReceive('lookup')
            ->once()
            ->andReturn(['ok' => false, 'error' => 'CONNECTION_FAILED']);

        $service = new CrewIdentityVerificationService($lookup);
        $result = $service->lookup('9981051142', 'jordanian', 17);

        $this->assertTrue($result['ok']);
        $this->assertSame(CrewIdentityVerificationService::STATUS_PENDING, $result['status']);
        $this->assertSame('gsb_cspd', $result['source']);
        $this->assertSame(
            CrewIdentityVerificationService::STATUS_PENDING,
            data_get($service->consumeProof($result['proof'], 'jordanian', '9981051142', 17), 'status'),
        );
    }

    public function test_definitive_not_found_result_cannot_create_a_verification_proof(): void
    {
        $lookup = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $lookup->shouldReceive('lookup')
            ->once()
            ->andReturn(['ok' => false, 'error' => 'NOT_FOUND']);

        $service = new CrewIdentityVerificationService($lookup);
        $result = $service->lookup('9981051142', 'jordanian', 17);

        $this->assertFalse($result['ok']);
        $this->assertSame(CrewIdentityVerificationService::STATUS_UNVERIFIED, $result['status']);
        $this->assertArrayNotHasKey('proof', $result);
    }

    public function test_expired_proof_is_rejected(): void
    {
        $lookup = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $lookup->shouldReceive('lookup')->once()->andReturn([
            'ok' => true,
            'data' => ['full_name' => 'Test Person'],
            'meta' => ['source' => 'gsb_cspd_personal_info_masked'],
        ]);

        $service = new CrewIdentityVerificationService($lookup);
        $result = $service->lookup('9981051142', 'jordanian', 5);

        Carbon::setTestNow('2026-07-22 14:01:00');

        $this->assertNull($service->consumeProof($result['proof'], 'jordanian', '9981051142', 5));
    }
}
