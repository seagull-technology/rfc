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

class ScoutingRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scouting_create_page_uses_template_form_shell(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $response = $this->actingAs($user)->get(route('scouting-requests.create'));

        $response
            ->assertOk()
            ->assertSeeText(__('app.scouting.create_title'))
            ->assertSeeText(__('app.scouting.producer_tab'))
            ->assertSeeText(__('app.scouting.locations_tab'));
    }

    public function test_applicant_can_create_and_submit_scouting_request(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        $response = $this->actingAs($user)->post(route('scouting-requests.store'), $this->scoutingPayload());

        $requestRecord = ScoutingRequest::query()->firstOrFail();

        $response->assertRedirect(route('scouting-requests.show', $requestRecord));

        $this->assertDatabaseHas('scouting_requests', [
            'id' => $requestRecord->getKey(),
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Border Light',
            'status' => 'draft',
        ]);
        $this->assertSame('065555555', data_get($requestRecord->metadata, 'producer.producer_phone'));
        $this->assertSame('065555556', data_get($requestRecord->metadata, 'producer.producer_fax'));
        $this->assertSame('Coordinator', data_get($requestRecord->metadata, 'producer.liaison_job_title'));

        Storage::disk('local')->assertExists($requestRecord->story_file_path);

        $submitResponse = $this->actingAs($user)->post(route('scouting-requests.submit', $requestRecord));

        $submitResponse->assertRedirect(route('scouting-requests.show', $requestRecord));

        $this->assertDatabaseHas('scouting_requests', [
            'id' => $requestRecord->getKey(),
            'status' => 'submitted',
        ]);
        $this->assertTrue($admin->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'scouting_submitted'
        ));
    }

    public function test_applicant_dashboard_displays_live_scouting_request_data(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user, $entity] = $this->createApplicantContext();

        ScoutingRequest::query()->create([
            'code' => 'SCOUT-00001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Scout Dashboard Request',
            'project_nationality' => 'jordanian',
            'status' => 'submitted',
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

        ScoutingRequest::query()->create([
            'code' => 'SCOUT-00002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Scout Draft Follow-up',
            'project_nationality' => 'jordanian',
            'status' => 'draft',
            'metadata' => [
                'producer' => [
                    'producer_name' => 'Local Producer',
                ],
                'locations' => [],
                'crew' => [],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSeeText('Production requests')
            ->assertSeeText('Scouting requests')
            ->assertSeeText('Scout Dashboard Request')
            ->assertSeeText('Scout Draft Follow-up')
            ->assertSeeText('Draft')
            ->assertSeeText('Submitted');
    }

    public function test_super_admin_can_export_filtered_scouting_directory(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        ScoutingRequest::query()->create([
            'code' => 'SCOUT-10001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Export Scout Match',
            'project_nationality' => 'jordanian',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
            'metadata' => [
                'producer' => ['producer_name' => 'Local Producer'],
                'locations' => [],
                'crew' => [],
            ],
        ]);

        ScoutingRequest::query()->create([
            'code' => 'SCOUT-10002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Closed Scout Request',
            'project_nationality' => 'jordanian',
            'status' => 'approved',
            'current_stage' => 'approved',
            'submitted_at' => now(),
            'metadata' => [
                'producer' => ['producer_name' => 'Local Producer'],
                'locations' => [],
                'crew' => [],
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.scouting-requests.export', [
            'q' => 'Export Scout',
            'status' => 'submitted',
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Export Scout Match', $content);
        $this->assertStringNotContainsString('Closed Scout Request', $content);
    }

    public function test_admin_scouting_directory_shows_workflow_checkpoints(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        ScoutingRequest::query()->create([
            'code' => 'SCOUT-20001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Needs Admin Review Scout',
            'project_nationality' => 'jordanian',
            'status' => 'submitted',
            'submitted_at' => now(),
            'metadata' => [
                'producer' => ['producer_name' => 'Local Producer'],
                'locations' => [],
                'crew' => [],
            ],
        ]);

        ScoutingRequest::query()->create([
            'code' => 'SCOUT-20002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Waiting Applicant Scout',
            'project_nationality' => 'jordanian',
            'status' => 'needs_clarification',
            'current_stage' => 'clarification',
            'submitted_at' => now(),
            'metadata' => [
                'producer' => ['producer_name' => 'Local Producer'],
                'locations' => [],
                'crew' => [],
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.scouting-requests.index'));

        $response
            ->assertOk()
            ->assertSeeText('Needs admin review')
            ->assertSeeText('Waiting on applicant')
            ->assertSeeText('Needs Admin Review Scout')
            ->assertSeeText('Waiting Applicant Scout');
    }

    public function test_super_admin_can_review_scouting_request_and_both_sides_can_exchange_correspondence(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user] = $this->createApplicantContext();

        $this->actingAs($user)->post(route('scouting-requests.store'), $this->scoutingPayload());

        $requestRecord = ScoutingRequest::query()->firstOrFail();

        $this->actingAs($user)->post(route('scouting-requests.submit', $requestRecord));

        $adminIndex = $this->actingAs($admin)->get(route('admin.scouting-requests.index'));

        $adminIndex
            ->assertOk()
            ->assertSeeText('Border Light');

        $reviewResponse = $this->actingAs($admin)->post(route('admin.scouting-requests.review', $requestRecord), [
            'decision' => 'needs_clarification',
            'note' => 'Please clarify the locations schedule.',
        ]);

        $reviewResponse->assertRedirect(route('admin.scouting-requests.show', $requestRecord));

        $this->assertDatabaseHas('scouting_requests', [
            'id' => $requestRecord->getKey(),
            'status' => 'needs_clarification',
            'current_stage' => 'clarification',
            'review_note' => 'Please clarify the locations schedule.',
            'reviewed_by_user_id' => $admin->getKey(),
        ]);

        $this->assertDatabaseHas('scouting_request_status_histories', [
            'scouting_request_id' => $requestRecord->getKey(),
            'status' => 'needs_clarification',
            'note' => 'Please clarify the locations schedule.',
        ]);
        $this->assertTrue($user->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'scouting_status_changed'
        ));
        $statusNotification = $user->fresh()->unreadNotifications->firstWhere('data.type_key', 'scouting_status_changed');
        $this->assertSame('Waiting on applicant', data_get($statusNotification?->data, 'workflow_checkpoint_label'));

        $adminMessageResponse = $this->actingAs($admin)->post(route('admin.scouting-requests.correspondence.store', $requestRecord), [
            'subject' => 'Clarification Required',
            'message' => 'Please attach the final site timing and any route changes.',
            'attachment' => UploadedFile::fake()->create('clarification.pdf', 120, 'application/pdf'),
        ]);

        $adminMessageResponse->assertRedirect(route('admin.scouting-requests.show', $requestRecord));

        $this->assertDatabaseHas('scouting_request_correspondences', [
            'scouting_request_id' => $requestRecord->getKey(),
            'sender_type' => 'admin',
            'subject' => 'Clarification Required',
        ]);
        $correspondenceNotification = $user->fresh()->unreadNotifications->firstWhere('data.type_key', 'scouting_correspondence');
        $this->assertSame('Waiting on applicant', data_get($correspondenceNotification?->data, 'workflow_checkpoint_label'));

        $showResponse = $this->actingAs($user)->get(route('scouting-requests.show', $requestRecord));

        $showResponse
            ->assertOk()
            ->assertSeeText('Clarification required')
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText('Review progress')
            ->assertSeeText('RFC is waiting for your clarification on this scouting request.')
            ->assertSeeText('Latest official step')
            ->assertSeeText('RFC correspondence: Clarification Required')
            ->assertSeeText('Next required step')
            ->assertSeeText('Review the note, update the request, and reply through official correspondence.')
            ->assertSeeText('Please clarify the locations schedule.')
            ->assertSeeText('Latest correspondence')
            ->assertSeeText('Clarification Required')
            ->assertSeeText('Please attach the final site timing and any route changes.');

        $adminShowResponse = $this->actingAs($admin)->get(route('admin.scouting-requests.show', $requestRecord));

        $adminShowResponse
            ->assertOk()
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText('Waiting for applicant clarification')
            ->assertSeeText('Latest correspondence')
            ->assertSeeText('Clarification Required')
            ->assertSeeText('Please attach the final site timing and any route changes.');

        $applicantReplyResponse = $this->actingAs($user)->post(route('scouting-requests.correspondence.store', $requestRecord), [
            'subject' => 'Updated Details',
            'message' => 'We updated the schedule and confirmed the locations.',
            'attachment' => UploadedFile::fake()->create('updated-schedule.pdf', 120, 'application/pdf'),
        ]);

        $applicantReplyResponse->assertRedirect(route('scouting-requests.show', $requestRecord));

        $this->assertDatabaseHas('scouting_request_correspondences', [
            'scouting_request_id' => $requestRecord->getKey(),
            'sender_type' => 'applicant',
            'subject' => 'Updated Details',
        ]);

        $this->assertDatabaseHas('scouting_requests', [
            'id' => $requestRecord->getKey(),
            'status' => 'submitted',
            'current_stage' => 'intake',
        ]);

        $adminReplyNotification = $admin->fresh()->unreadNotifications->firstWhere('data.type_key', 'scouting_correspondence');

        $this->assertNotNull($adminReplyNotification);
        $this->assertSame('Needs admin review', data_get($adminReplyNotification?->data, 'workflow_checkpoint_label'));
        $this->assertTrue((bool) data_get($adminReplyNotification?->data, 'applicant_response_active'));
        $this->assertSame('Applicant response received', data_get($adminReplyNotification?->data, 'applicant_response_title'));
        $this->assertSame('Applicant sent correspondence: Updated Details', data_get($adminReplyNotification?->data, 'applicant_response_summary'));

        $adminIndexAfterReply = $this->actingAs($admin)->get(route('admin.scouting-requests.index'));

        $adminIndexAfterReply
            ->assertOk()
            ->assertSeeText('Applicant response received')
            ->assertSeeText('Applicant sent correspondence: Official Correspondence');

        $adminShowAfterReply = $this->actingAs($admin)->get(route('admin.scouting-requests.show', $requestRecord));

        $adminShowAfterReply
            ->assertOk()
            ->assertSeeText('Applicant response received')
            ->assertSeeText('Applicant sent correspondence: Updated Details');

        $dashboardAfterReply = $this->actingAs($admin)->get(route('admin.dashboard'));

        $dashboardAfterReply
            ->assertOk()
            ->assertSeeText('Applicant response received');
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

    /**
     * @return array<string, mixed>
     */
    private function scoutingPayload(): array
    {
        return [
            'project_name' => 'Border Light',
            'project_nationality' => 'jordanian',
            'producer_name' => 'Local Producer',
            'producer_nationality' => 'jordanian',
            'production_company_name' => 'Applicant Studio',
            'producer_phone' => '065555555',
            'producer_mobile' => '0799999999',
            'producer_fax' => '065555556',
            'producer_email' => 'producer@example.com',
            'producer_profile_url' => 'https://example.com/producer',
            'contact_address' => 'Amman',
            'website_url' => 'https://example.com',
            'liaison_name' => 'Liaison Name',
            'liaison_job_title' => 'Coordinator',
            'liaison_email' => 'liaison@example.com',
            'liaison_mobile' => '0799999999',
            'responsible_person_name' => 'Scout Lead',
            'responsible_person_nationality' => 'jordanian',
            'production_types' => ['documentary'],
            'production_type_other' => null,
            'scout_start_date' => '2026-05-01',
            'scout_end_date' => '2026-05-05',
            'production_start_date' => '2026-06-01',
            'production_end_date' => '2026-06-10',
            'project_summary' => 'A scouting mission across multiple locations in Jordan.',
            'story_text' => 'A brief documentary concept.',
            'story_file' => UploadedFile::fake()->create('story.pdf', 120, 'application/pdf'),
            'locations' => [
                [
                    'governorate' => 'amman',
                    'location_name' => 'Amman Citadel',
                    'google_map_url' => 'https://maps.example.com/citadel',
                    'location_nature' => 'archaeological',
                    'start_date' => '2026-05-01',
                    'end_date' => '2026-05-02',
                ],
            ],
            'crew' => [
                [
                    'name' => 'Crew Member',
                    'job_title' => 'Researcher',
                    'nationality' => 'jordanian',
                    'national_id_passport' => '1234567890',
                ],
            ],
        ];
    }
}
