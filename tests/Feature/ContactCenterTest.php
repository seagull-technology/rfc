<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Group;
use App\Models\ScoutingRequest;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_contact_center_message_and_applicant_can_see_it_in_inbox(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        $response = $this->actingAs($admin)->post(route('admin.contact-center.messages.store'), [
            'title' => 'Updated filming guidance',
            'message_type' => 'general_notice',
            'message' => 'Please review the updated filming guidance before your next submission.',
            'recipient_scope' => 'specific',
            'entity_id' => $entity->getKey(),
            'attachment' => UploadedFile::fake()->create('guidance.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('admin.contact-center.index'));

        $user->refresh();
        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertSame('contact_center_message', data_get($user->unreadNotifications()->first()?->data, 'type_key'));

        $adminPage = $this->actingAs($admin)->get(route('admin.contact-center.index'));

        $adminPage
            ->assertOk()
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText('Updated filming guidance')
            ->assertSeeText('Applicant Studio');

        $applicantPage = $this->actingAs($user)->get(route('contact-center.index'));

        $applicantPage
            ->assertOk()
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText('Updated filming guidance')
            ->assertSeeText('General notice');

        $user->refresh();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_contact_center_lists_scouting_correspondence_for_both_admin_and_applicant(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        $requestRecord = ScoutingRequest::query()->create([
            'code' => 'SCOUT-00991',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Valley Scout',
            'project_nationality' => 'jordanian',
            'status' => 'needs_clarification',
            'current_stage' => 'clarification',
            'submitted_at' => now(),
            'metadata' => [
                'producer' => [
                    'producer_name' => 'Local Producer',
                    'producer_nationality' => 'jordanian',
                    'production_company_name' => 'Applicant Studio',
                    'producer_email' => 'producer@example.com',
                    'contact_address' => 'Amman',
                    'liaison_name' => 'Liaison',
                    'liaison_email' => 'liaison@example.com',
                    'liaison_mobile' => '0799999999',
                ],
                'responsible_person' => [
                    'name' => 'Scout Lead',
                    'nationality' => 'jordanian',
                ],
                'production' => [
                    'types' => ['documentary'],
                ],
                'locations' => [],
                'crew' => [],
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.scouting-requests.correspondence.store', $requestRecord), [
            'subject' => 'Clarification Required',
            'message' => 'Please confirm the final location access dates.',
        ]);

        $user->refresh();
        $notification = $user->unreadNotifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('scouting_correspondence', data_get($notification?->data, 'type_key'));

        $adminInbox = $this->actingAs($admin)->get(route('admin.contact-center.index'));

        $adminInbox
            ->assertOk()
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText('Clarification Required')
            ->assertSeeText('Scouting request correspondence')
            ->assertSeeText('Waiting on applicant');

        $applicantInbox = $this->actingAs($user)->get(route('contact-center.index'));

        $applicantInbox
            ->assertOk()
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText('Clarification Required')
            ->assertSeeText('Scouting request correspondence')
            ->assertSeeText('Waiting on applicant');

        $user->refresh();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_notification_redirect_marks_message_notification_as_read(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        $this->actingAs($admin)->post(route('admin.contact-center.messages.store'), [
            'title' => 'Targeted notice',
            'message_type' => 'follow_up',
            'message' => 'Please review your inbox.',
            'recipient_scope' => 'specific',
            'entity_id' => $entity->getKey(),
        ]);

        $notification = $user->fresh()->unreadNotifications()->first();

        $response = $this->actingAs($user)->get(route('notifications.redirect', $notification));

        $response->assertRedirect(route('contact-center.index'));

        $this->assertNotNull($user->fresh()->notifications()->find($notification->id)?->read_at);
    }

    /**
     * @return array{0: User, 1: Entity}
     */
    private function createApplicantContext(): array
    {
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'code' => 'applicant-studio',
            'name_en' => 'Applicant Studio',
            'name_ar' => 'Applicant Studio',
            'registration_no' => 'ORG-100',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'studio@example.com',
            'phone' => '065555555',
        ]);

        $user = User::query()->create([
            'name' => 'Applicant User',
            'username' => 'applicant-user',
            'email' => 'applicant-user@example.com',
            'national_id' => '1000000000',
            'phone' => '0790000000',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $user->entities()->attach($entity, [
            'job_title' => 'Producer',
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

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
