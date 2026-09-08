<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SessionNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('requestForms')]
    public function test_logo_requests_preserve_the_form_redirect_and_validation_errors(
        string $workflow,
        string $locale,
        bool $fetchMetadata,
    ): void {
        $this->refreshApplicationWithLocale($locale);
        $this->seed(AccessControlSeeder::class);
        Storage::set('local', Storage::fake('session-navigation-'.getmypid()));
        [$user, $entity] = $this->createApplicantWithLogo();

        $form = route($workflow.'.create');
        $logo = route('entities.logo', $entity).'?v=security-retest';
        $imageHeaders = $fetchMetadata ? ['Sec-Fetch-Dest' => 'image'] : [];

        $this->actingAs($user)->get($form)
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->get($logo, $imageHeaders)->assertOk()->assertHeader('Content-Type', 'image/png');

        // There is deliberately no ->from() or Referer header: the real browser
        // omits it under the application's no-referrer policy.
        $this->post(route($workflow.'.store'), ['producer_name' => 'Retain my input'])
            ->assertRedirect($form)
            ->assertSessionHasErrors('project_name');

        $message = session('errors')->first('project_name');
        $oldInput = session('_old_input');

        // A concurrent resource request before the redirected form must not
        // consume its pending errors or old input.
        $this->get($logo, $imageHeaders)
            ->assertOk()
            ->assertSessionHasErrors('project_name')
            ->assertSessionHas('_old_input', $oldInput);

        $this->get($form)->assertOk()->assertSeeText($message);
        $this->get($form)->assertOk()->assertDontSeeText($message);
    }

    public static function requestForms(): array
    {
        $cases = [];

        foreach (['applications', 'scouting-requests'] as $workflow) {
            foreach (['ar', 'en'] as $locale) {
                foreach ([true, false] as $fetchMetadata) {
                    $cases[$workflow.' '.$locale.' '.($fetchMetadata ? 'modern' : 'legacy')] = [
                        $workflow, $locale, $fetchMetadata,
                    ];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('backgroundResponses')]
    public function test_background_responses_do_not_replace_navigation_or_consume_flash(
        string $contentType,
        array $requestHeaders,
        array $responseHeaders,
        int $status,
    ): void {
        Route::middleware('web')->get('/session-navigation-resource', fn () => response(
            'resource', $status, ['Content-Type' => $contentType, ...$responseHeaders],
        ))->name('session-navigation-resource');

        $this->withSession([
            '_previous' => ['url' => url('/form')],
            'status' => 'Pending confirmation',
            '_flash' => ['old' => ['status'], 'new' => []],
        ])->get('/session-navigation-resource', $requestHeaders)
            ->assertStatus($status)
            ->assertSessionHas('_previous.url', url('/form'))
            ->assertSessionHas('status', 'Pending confirmation');
    }

    public static function backgroundResponses(): array
    {
        return [
            'json polling' => ['application/json', [], [], 200],
            'fetch html fragment' => ['text/html', ['Sec-Fetch-Dest' => 'empty'], [], 200],
            'ajax html fragment' => ['text/html', ['X-Requested-With' => 'XMLHttpRequest'], [], 200],
            'failed image returning html' => ['text/html', ['Sec-Fetch-Dest' => 'image'], [], 404],
            'download with html content' => ['text/html', [], ['Content-Disposition' => 'attachment; filename="document.html"'], 200],
            'redirect hop' => ['text/html', [], ['Location' => '/form'], 302],
        ];
    }

    public function test_real_navigation_updates_history_expires_flash_and_retains_session_locking(): void
    {
        Route::middleware('web')->get('/session-navigation-page', fn () => response('Page'))
            ->block(1, 1)
            ->name('session-navigation-page');

        $this->withSession([
            '_previous' => ['url' => url('/older-page')],
            'status' => 'Consumed confirmation',
            '_flash' => ['old' => ['status'], 'new' => []],
        ])->get('/session-navigation-page', ['Sec-Fetch-Dest' => 'document'])
            ->assertOk()
            ->assertSessionHas('_previous.url', url('/session-navigation-page'))
            ->assertSessionMissing('status');
    }

    /** @return array{User, Entity} */
    private function createApplicantWithLogo(): array
    {
        $group = Group::query()->where('code', 'organizations')->firstOrFail();
        $logoPath = 'registration/logos/session-test.png';
        Storage::disk('local')->put($logoPath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII=',
        ));

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Navigation Test Studio',
            'name_ar' => 'Navigation Test Studio',
            'registration_no' => 'ORG-NAVIGATION',
            'registration_type' => 'company',
            'status' => 'active',
            'metadata' => ['logo_path' => $logoPath, 'logo_mime' => 'image/png'],
        ]);
        $user = User::factory()->create(['registration_type' => 'company', 'status' => 'active']);
        $user->entities()->attach($entity, ['is_primary' => true, 'status' => 'active', 'joined_at' => now()]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            $user->assignRole('applicant_owner');
        } finally {
            $registrar->setPermissionsTeamId(null);
        }

        return [$user, $entity];
    }
}
