<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Gsb\IndividualPersonalInfoLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ApplicationPersonalDetailsLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_personal_details_lookup(): void
    {
        $this->postJson(route('applications.personal-details.lookup'), [
            'nationality_category' => 'arab',
            'personal_number' => '9981051142',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_lookup_personal_details(): void
    {
        $user = User::factory()->create();
        $service = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $service->shouldReceive('lookup')
            ->once()
            ->with('9981051142', 'arab')
            ->andReturn([
                'ok' => true,
                'data' => [
                    'full_name' => 'أحمد بسام أحمد حمد',
                    'gender' => 'male',
                ],
            ]);
        $this->app->instance(IndividualPersonalInfoLookupService::class, $service);

        $this->actingAs($user)
            ->postJson(route('applications.personal-details.lookup'), [
                'nationality_category' => 'arab',
                'personal_number' => '9981051142',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.first_name', 'أحمد')
            ->assertJsonPath('data.father_name', 'بسام')
            ->assertJsonPath('data.grandfather_name', 'أحمد')
            ->assertJsonPath('data.family_name', 'حمد');
    }

    public function test_lookup_rejects_invalid_categories_and_non_ten_digit_numbers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('applications.personal-details.lookup'), [
                'nationality_category' => 'unsupported',
                'personal_number' => '123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nationality_category', 'personal_number']);
    }

    public function test_provider_failure_returns_a_generic_service_unavailable_response(): void
    {
        $user = User::factory()->create();
        $service = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $service->shouldReceive('lookup')->once()->andReturn([
            'ok' => false,
            'error' => 'UPSTREAM_FAILURE',
        ]);
        $this->app->instance(IndividualPersonalInfoLookupService::class, $service);

        $this->actingAs($user)
            ->postJson(route('applications.personal-details.lookup'), [
                'nationality_category' => 'travel_document',
                'personal_number' => '9981051142',
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('ok', false)
            ->assertJsonMissingPath('error');
    }

    public function test_lookup_rate_limit_does_not_reset_when_the_payload_changes(): void
    {
        $user = User::factory()->create();
        $service = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $service->shouldReceive('lookup')->times(10)->andReturn([
            'ok' => true,
            'data' => ['full_name' => 'Test Person'],
        ]);
        $this->app->instance(IndividualPersonalInfoLookupService::class, $service);

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($user)
                ->postJson(route('applications.personal-details.lookup'), [
                    'nationality_category' => $attempt % 2 === 0 ? 'arab' : 'travel_document',
                    'personal_number' => str_pad((string) $attempt, 10, '0', STR_PAD_LEFT),
                ])
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson(route('applications.personal-details.lookup'), [
                'nationality_category' => 'arab',
                'personal_number' => '9981051142',
            ])
            ->assertTooManyRequests();
    }
}
