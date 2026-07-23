<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Gsb\CrewIdentityVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ApplicationCrewIdentityLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_verify_a_crew_identity(): void
    {
        $user = User::factory()->create();
        $service = Mockery::mock(CrewIdentityVerificationService::class);
        $service->shouldReceive('lookup')
            ->once()
            ->with('9981051142', 'jordanian', $user->getKey())
            ->andReturn([
                'ok' => true,
                'status' => CrewIdentityVerificationService::STATUS_VERIFIED,
                'data' => ['full_name' => 'أحمد بسام أحمد حمد'],
                'source' => 'gsb_cspd_personal_info_masked',
                'verified_at' => '2026-07-22T12:00:00+03:00',
                'proof' => 'signed-proof',
            ]);
        $this->app->instance(CrewIdentityVerificationService::class, $service);

        $this->actingAs($user)
            ->postJson(route('applications.crew.identity.lookup'), [
                'nationality_category' => 'jordanian',
                'identifier' => '9981051142',
            ])
            ->assertOk()
            ->assertJsonPath('status', CrewIdentityVerificationService::STATUS_VERIFIED)
            ->assertJsonPath('data.full_name', 'أحمد بسام أحمد حمد')
            ->assertJsonPath('proof', 'signed-proof');
    }

    public function test_temporary_outage_returns_an_accepted_pending_response(): void
    {
        $user = User::factory()->create();
        $service = Mockery::mock(CrewIdentityVerificationService::class);
        $service->shouldReceive('lookup')->once()->andReturn([
            'ok' => true,
            'status' => CrewIdentityVerificationService::STATUS_PENDING,
            'source' => 'gsb_psd',
            'verified_at' => '2026-07-22T12:00:00+03:00',
            'proof' => 'pending-proof',
        ]);
        $this->app->instance(CrewIdentityVerificationService::class, $service);

        $this->actingAs($user)
            ->postJson(route('applications.crew.identity.lookup'), [
                'nationality_category' => 'foreign',
                'identifier' => '7654321',
            ])
            ->assertStatus(202)
            ->assertJsonPath('status', CrewIdentityVerificationService::STATUS_PENDING)
            ->assertJsonPath('proof', 'pending-proof');
    }

    public function test_definitive_invalid_number_returns_a_validation_response_without_proof(): void
    {
        $user = User::factory()->create();
        $service = Mockery::mock(CrewIdentityVerificationService::class);
        $service->shouldReceive('lookup')->once()->andReturn([
            'ok' => false,
            'status' => CrewIdentityVerificationService::STATUS_UNVERIFIED,
            'error' => 'INVALID_NATIONAL_ID',
        ]);
        $this->app->instance(CrewIdentityVerificationService::class, $service);

        $this->actingAs($user)
            ->postJson(route('applications.crew.identity.lookup'), [
                'nationality_category' => 'jordanian',
                'identifier' => '123',
            ])
            ->assertUnprocessable()
            ->assertJsonMissingPath('proof')
            ->assertJsonPath('status', CrewIdentityVerificationService::STATUS_UNVERIFIED);
    }
}
