<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminSignedCompletionLinkTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('completionCases')]
    public function test_resolved_registration_has_a_valid_anonymous_link_without_stale_review_actions(string $type, string $status, string $locale): void
    {
        $admin = $this->prepareAdmin($locale);
        [$entity, $owner] = $this->createRegistration($type, $status);
        $entityBefore = $entity->fresh()->getRawOriginal();
        $ownerBefore = $owner->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->get(route('admin.entities.show', $entity))->assertOk();
        $page = $this->page($response->getContent());
        $links = $page->query('//a[contains(@href, "/registration/link/")]');
        $this->assertCount(1, $links);
        $url = $links->item(0)->getAttribute('href');
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        $this->assertSame('/'.$locale.'/registration/link/'.$entity->id.'/complete', parse_url($url, PHP_URL_PATH));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(now()->addDays(7)->getTimestamp(), (int) $query['expires']);
        $this->assertSame($url, $page->query('//input[@id="registration-completion-link" and @readonly]')->item(0)->getAttribute('value'));
        $this->assertCount(0, $page->query('//form[@action="'.route('admin.entities.review', $entity).'"]'));

        // The generated capability must work without the administrator's session.
        auth()->logout();
        $completion = $this->get($url)->assertOk();
        $form = $this->page($completion->getContent());
        $this->assertSame($url, $form->query('//form[@method="POST"]')->item(0)->getAttribute('action'));
        $this->assertSame($entity->registration_no, $form->query('//input[@name="registration_number" and @readonly]')->item(0)->getAttribute('value'));
        $this->assertSame($entityBefore, $entity->fresh()->getRawOriginal());
        $this->assertSame($ownerBefore, $owner->fresh()->getRawOriginal());
        Notification::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    #[DataProvider('reviewVisibilityCases')]
    public function test_pending_review_remains_available_and_active_entities_have_no_completion_link(string $type, string $status): void
    {
        $admin = $this->prepareAdmin('en');
        [$entity] = $this->createRegistration($type, $status);
        $response = $this->actingAs($admin)->get(route('admin.entities.show', $entity))->assertOk();
        $page = $this->page($response->getContent());

        $this->assertCount(0, $page->query('//a[contains(@href, "/registration/link/")]'));
        $this->assertCount($status === 'pending_review' ? 1 : 0, $page->query('//form[@action="'.route('admin.entities.review', $entity).'"]'));
        Notification::assertNothingSent();
    }

    public function test_read_only_entity_viewer_cannot_obtain_a_completion_capability(): void
    {
        $admin = $this->prepareAdmin('en');
        [$entity] = $this->createRegistration('school', 'rejected');
        $viewer = User::factory()->create(['status' => 'active', 'registration_type' => 'staff']);
        $adminEntity = $admin->primaryEntity();
        $viewer->entities()->attach($adminEntity, ['status' => 'active', 'is_primary' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($adminEntity->id);
        $viewer->givePermissionTo(['access.admin-panel', 'entities.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->actingAs($viewer)->get(route('admin.entities.show', $entity))->assertOk();
        $this->assertCount(0, $this->page($response->getContent())->query('//a[contains(@href, "/registration/link/")] | //input[@id="registration-completion-link"]'));
        Notification::assertNothingSent();
    }

    public function test_archived_registration_does_not_expose_a_completion_link(): void
    {
        $admin = $this->prepareAdmin('en');
        [$entity] = $this->createRegistration('ngo', 'rejected');
        $entity->delete();

        $response = $this->actingAs($admin)->get(route('admin.entities.show', $entity))->assertOk();
        $this->assertCount(0, $this->page($response->getContent())->query('//a[contains(@href, "/registration/link/")] | //input[@id="registration-completion-link"]'));
    }

    public static function completionCases(): array
    {
        $cases = [];
        foreach (['ngo', 'school'] as $type) {
            foreach (['rejected', 'needs_completion'] as $status) {
                foreach (['ar', 'en'] as $locale) {
                    $cases[$type.'-'.$status.'-'.$locale] = [$type, $status, $locale];
                }
            }
        }

        return $cases;
    }

    public static function reviewVisibilityCases(): array
    {
        return [
            ['ngo', 'pending_review'], ['school', 'pending_review'],
            ['ngo', 'active'], ['school', 'active'],
        ];
    }

    private function prepareAdmin(string $locale): User
    {
        $this->refreshApplicationWithLocale($locale);
        $this->seed(AccessControlSeeder::class);
        $this->freezeTime();
        Notification::fake();

        return User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
    }

    private function createRegistration(string $type, string $status): array
    {
        $name = 'Completion Verification '.$type.' '.$status;
        $owner = User::factory()->create([
            'name' => $name, 'email' => $type.'-'.$status.'@verification.invalid',
            'phone' => null, 'registration_type' => $type, 'status' => $status,
        ]);
        $entity = Entity::query()->create([
            'group_id' => Group::query()->where('code', 'organizations')->firstOrFail()->id,
            'name_en' => $name, 'name_ar' => $name, 'registration_no' => 'VERIFY-'.$type.'-'.$status,
            'registration_type' => $type, 'status' => $status, 'email' => $owner->email,
            'metadata' => ['address' => 'Verification address'],
        ]);
        $entity->users()->attach($owner, ['status' => 'active', 'is_primary' => true]);

        return [$entity, $owner];
    }

    private function page(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }
}
