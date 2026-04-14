<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use App\Models\Group;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\RegistrationApprovedNotification;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_create_page_uses_template_form_shell(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $response = $this->actingAs($user)->get(route('applications.create'));

        $response
            ->assertOk()
            ->assertSeeText(__('app.applications.create_title'))
            ->assertSeeText(__('app.applications.general_information'))
            ->assertSeeText(__('app.applications.requirements_list'))
            ->assertSee('id="form-wizard1"', false);
    }

    public function test_applicant_can_create_a_draft_and_submit_it_for_review(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $storeResponse = $this->actingAs($user)->post(route('applications.store'), $this->applicationPayload());

        $application = Application::query()->firstOrFail();

        $storeResponse->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Desert Dreams',
            'status' => 'draft',
        ]);

        $this->assertSame('Local Producer', data_get($application->metadata, 'producer.producer_name'));
        $this->assertSame(['public_security', 'environment'], data_get($application->metadata, 'requirements.required_approvals'));
        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'status' => 'draft',
        ]);

        $submitResponse = $this->actingAs($user)->post(route('applications.submit', $application));

        $submitResponse->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'status' => 'submitted',
            'current_stage' => 'intake',
        ]);
        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'public_security',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'environment',
            'status' => 'pending',
        ]);
        $this->assertTrue($admin->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'application_submitted'
        ));
        $this->assertTrue($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_requested'
        ));
    }

    public function test_admin_can_review_submitted_application_and_request_clarification(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext([
            'name' => 'Applicant Reviewer',
            'username' => 'applicant-reviewer',
            'email' => 'applicant-reviewer@example.com',
        ], [
            'name_en' => 'Review Studio',
            'name_ar' => 'Review Studio',
            'registration_no' => 'ORG-900',
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-00001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Wadi Lights',
            'project_nationality' => 'international',
            'work_category' => 'documentary',
            'release_method' => 'festival',
            'planned_start_date' => '2026-06-02',
            'planned_end_date' => '2026-06-12',
            'estimated_crew_count' => 18,
            'estimated_budget' => 35000,
            'project_summary' => 'An international documentary production.',
            'status' => 'submitted',
            'submitted_at' => now(),
            'metadata' => [
                'producer' => [
                    'producer_name' => 'Review Producer',
                    'production_company_name' => 'Review Studio',
                    'contact_address' => 'Amman',
                    'contact_phone' => '065555111',
                    'contact_email' => 'review-producer@example.com',
                    'liaison_name' => 'Liaison',
                    'liaison_position' => 'Coordinator',
                    'liaison_email' => 'liaison@example.com',
                    'liaison_mobile' => '0792222111',
                ],
                'director' => [
                    'director_name' => 'Director Review',
                    'director_nationality' => 'Jordanian',
                ],
                'international' => [
                    'international_producer_name' => 'Global Partner',
                    'international_producer_company' => 'Global Docs',
                ],
                'requirements' => [
                    'required_approvals' => ['airports'],
                    'supporting_notes' => 'Airport access needed.',
                ],
            ],
        ]);

        $application->statusHistory()->create([
            'user_id' => $user->getKey(),
            'status' => 'submitted',
            'note' => 'Submitted by applicant.',
            'happened_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.applications.review', $application), [
            'decision' => 'needs_clarification',
            'note' => 'Please clarify the airport filming dates.',
        ]);

        $response->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'status' => 'needs_clarification',
            'current_stage' => 'clarification',
            'review_note' => 'Please clarify the airport filming dates.',
            'reviewed_by_user_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'status' => 'needs_clarification',
            'note' => 'Please clarify the airport filming dates.',
            'user_id' => $admin->getKey(),
        ]);
        $this->assertTrue($user->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'application_status_changed'
        ));
        $statusNotification = $user->fresh()->unreadNotifications->firstWhere('data.type_key', 'application_status_changed');
        $this->assertSame('Waiting on applicant', data_get($statusNotification?->data, 'workflow_checkpoint_label'));

        $showResponse = $this->actingAs($user)->get(route('applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Clarification required')
            ->assertSeeText('Please clarify the airport filming dates.')
            ->assertSeeText('Open correspondence')
            ->assertSee('streamit-wraper-table', false);

        $adminShowResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $adminShowResponse
            ->assertOk()
            ->assertSeeText('Waiting for applicant clarification')
            ->assertSeeText('The applicant needs to provide clarification on this production request.')
            ->assertSeeText('Open review');
    }

    public function test_admin_can_assign_reviewer_and_update_authority_approval(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($user)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($user)->post(route('applications.submit', $application));

        $assignResponse = $this->actingAs($admin)->post(route('admin.applications.assign', $application), [
            'assigned_to_user_id' => $admin->getKey(),
        ]);

        $assignResponse->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'assigned_to_user_id' => $admin->getKey(),
            'current_stage' => 'rfc_review',
        ]);

        $approvalId = Application::query()->firstOrFail()->authorityApprovals()->value('id');

        $updateResponse = $this->actingAs($admin)->post(route('admin.applications.approvals.update', [$application, $approvalId]), [
            'status' => 'approved',
            'note' => 'Airport approval issued.',
        ]);

        $updateResponse->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('application_authority_approvals', [
            'id' => $approvalId,
            'status' => 'approved',
            'note' => 'Airport approval issued.',
            'reviewed_by_user_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'current_stage' => 'final_decision',
        ]);
        $this->assertTrue($user->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_updated'
        ));
        $this->assertTrue($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_updated'
        ));
    }

    public function test_applicant_can_upload_document_and_admin_can_review_and_send_correspondence(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user] = $this->createApplicantContext();

        $this->actingAs($user)->post(route('applications.store'), $this->applicationPayload());
        $application = Application::query()->firstOrFail();
        $this->actingAs($user)->post(route('applications.submit', $application));

        $uploadResponse = $this->actingAs($user)->post(route('applications.documents.store', $application), [
            'document_type' => 'work_content_summary',
            'title' => 'Work Content Summary Form',
            'note' => 'Latest signed version.',
            'file' => UploadedFile::fake()->create('summary.pdf', 120, 'application/pdf'),
        ]);

        $uploadResponse->assertRedirect(route('applications.show', $application));

        $documentPath = \App\Models\ApplicationDocument::query()->value('file_path');

        Storage::disk('local')->assertExists($documentPath);
        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->getKey(),
            'document_type' => 'work_content_summary',
            'title' => 'Work Content Summary Form',
            'status' => 'submitted',
        ]);

        $documentId = \App\Models\ApplicationDocument::query()->value('id');

        $reviewResponse = $this->actingAs($admin)->post(route('admin.applications.documents.review', [$application, $documentId]), [
            'status' => 'needs_revision',
            'note' => 'Please add the missing signature page.',
        ]);

        $reviewResponse->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('application_documents', [
            'id' => $documentId,
            'status' => 'needs_revision',
            'note' => 'Please add the missing signature page.',
            'reviewed_by_user_id' => $admin->getKey(),
        ]);

        $messageResponse = $this->actingAs($admin)->post(route('admin.applications.correspondence.store', $application), [
            'subject' => 'Official RFC note',
            'message' => 'Please upload the revised signed form before we continue.',
            'attachment' => UploadedFile::fake()->create('rfc-note.pdf', 60, 'application/pdf'),
        ]);

        $messageResponse->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('application_correspondences', [
            'application_id' => $application->getKey(),
            'sender_type' => 'admin',
            'subject' => 'Official RFC note',
        ]);
        $correspondenceNotification = $user->fresh()->unreadNotifications->firstWhere('data.type_key', 'application_correspondence');
        $this->assertSame('Waiting on applicant', data_get($correspondenceNotification?->data, 'workflow_checkpoint_label'));

        $showResponse = $this->actingAs($user)->get(route('applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Latest correspondence')
            ->assertSeeText('Official RFC note')
            ->assertSeeText('Please upload the revised signed form before we continue.');

        $adminShowResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $adminShowResponse
            ->assertOk()
            ->assertSeeText('Latest correspondence')
            ->assertSeeText('Official RFC note')
            ->assertSeeText('Please upload the revised signed form before we continue.');
    }

    public function test_applicant_document_upload_requeues_clarification_back_to_admin_queue(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        $application = Application::query()->create([
            'code' => 'REQ-00421',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'assigned_to_user_id' => $admin->getKey(),
            'assigned_at' => now()->subDay(),
            'project_name' => 'Clarification Upload Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'documentary',
            'release_method' => 'festival',
            'planned_start_date' => '2026-07-10',
            'planned_end_date' => '2026-07-15',
            'project_summary' => 'Needs revised supporting files.',
            'status' => 'needs_clarification',
            'current_stage' => 'clarification',
            'review_note' => 'Upload the revised supporting package.',
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => now()->subDay(),
            'reviewed_by_user_id' => $admin->getKey(),
        ]);

        $response = $this->actingAs($user)->post(route('applications.documents.store', $application), [
            'document_type' => 'work_content_summary',
            'title' => 'Revised Work Content Summary',
            'note' => 'Updated after RFC clarification.',
            'file' => UploadedFile::fake()->create('revised-summary.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'status' => 'submitted',
            'current_stage' => 'intake',
            'assigned_to_user_id' => null,
        ]);

        $notification = $admin->fresh()->unreadNotifications->firstWhere('data.type_key', 'application_submitted');

        $this->assertNotNull($notification);
        $this->assertSame('Assign reviewer', data_get($notification?->data, 'workflow_checkpoint_label'));
        $this->assertTrue((bool) data_get($notification?->data, 'applicant_response_active'));
        $this->assertSame('Applicant response received', data_get($notification?->data, 'applicant_response_title'));
        $this->assertSame('Revised document uploaded: Attached Forms', data_get($notification?->data, 'applicant_response_summary'));

        $indexResponse = $this->actingAs($admin)->get(route('admin.applications.index'));

        $indexResponse
            ->assertOk()
            ->assertSeeText('Applicant response received')
            ->assertSeeText('Revised document uploaded: Attached Forms');

        $showResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Applicant response received')
            ->assertSeeText('Revised document uploaded: Revised Work Content Summary');

        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));

        $dashboardResponse
            ->assertOk()
            ->assertSeeText('Applicant response received');
    }

    public function test_authority_user_can_view_scoped_inbox_and_approve_own_assignment(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        [$authorityUser, $authorityEntity] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security', 'environment'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $inboxResponse = $this->actingAs($authorityUser)->get(route('authority.applications.index'));

        $inboxResponse
            ->assertOk()
            ->assertSeeText('Authority Inbox')
            ->assertSeeText('Authority action required')
            ->assertSeeText('Awaiting your decision')
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText('Desert Dreams')
            ->assertSeeText('Public Security Directorate');

        $adminCorrespondenceResponse = $this->actingAs($admin)->post(route('admin.applications.correspondence.store', $application), [
            'subject' => 'RFC Update',
            'message' => 'Please review the latest RFC note.',
        ]);

        $adminCorrespondenceResponse->assertRedirect(route('admin.applications.show', $application));

        $authorityAdminUpdate = $authorityUser->fresh()->unreadNotifications
            ->where('data.type_key', 'application_correspondence')
            ->first(fn ($notification) => data_get($notification->data, 'notification_highlight_summary') === 'New correspondence: RFC Update');

        $this->assertNotNull($authorityAdminUpdate);

        $authorityUpdatedInboxResponse = $this->actingAs($authorityUser)->get(route('authority.applications.index'));

        $authorityUpdatedInboxResponse
            ->assertOk()
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText('Request update received')
            ->assertSeeText('New correspondence: Official Correspondence');

        $applicantCorrespondenceResponse = $this->actingAs($applicant)->post(route('applications.correspondence.store', $application), [
            'subject' => 'Applicant Reply',
            'message' => 'We have attached the requested clarification details.',
        ]);

        $applicantCorrespondenceResponse->assertRedirect(route('applications.show', $application));

        $authorityApplicantUpdate = $authorityUser->fresh()->unreadNotifications
            ->where('data.type_key', 'application_correspondence')
            ->first(fn ($notification) => data_get($notification->data, 'notification_highlight_summary') === 'New correspondence: Applicant Reply');

        $this->assertNotNull($authorityApplicantUpdate);

        $showResponse = $this->actingAs($authorityUser)->get(route('authority.applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Authority Decision')
            ->assertSeeText('Request update received')
            ->assertSeeText('New correspondence: Applicant Reply')
            ->assertSee('streamit-wraper-table', false)
            ->assertSeeText($authorityEntity->displayName('en'));

        $updateResponse = $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
            'status' => 'approved',
            'note' => 'Security approval granted.',
        ]);

        $updateResponse->assertRedirect(route('authority.applications.show', $application));

        $this->assertDatabaseHas('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'public_security',
            'status' => 'approved',
            'note' => 'Security approval granted.',
            'reviewed_by_user_id' => $authorityUser->getKey(),
        ]);
        $this->assertDatabaseHas('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'environment',
            'status' => 'pending',
        ]);
        $this->assertTrue($admin->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_updated'
        ));
        $this->assertTrue($applicant->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_updated'
        ));

        $correspondenceResponse = $this->actingAs($authorityUser)->post(route('authority.applications.correspondence.store', $application), [
            'subject' => 'Authority Note',
            'message' => 'Security authority has approved the request.',
            'attachment' => UploadedFile::fake()->create('authority-letter.pdf', 50, 'application/pdf'),
        ]);

        $correspondenceResponse->assertRedirect(route('authority.applications.show', $application));

        $this->assertDatabaseHas('application_correspondences', [
            'application_id' => $application->getKey(),
            'sender_type' => 'authority',
            'sender_name' => $authorityEntity->displayName('en'),
            'subject' => 'Authority Note',
        ]);
        $this->assertTrue($admin->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'application_correspondence'
        ));
        $this->assertTrue($applicant->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'application_correspondence'
        ));
    }

    public function test_admin_can_filter_applications_directory(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        Application::query()->create([
            'code' => 'REQ-00011',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Filter Match Production',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-12',
            'project_summary' => 'Test project',
            'status' => 'submitted',
        ]);

        Application::query()->create([
            'code' => 'REQ-00012',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Other Production',
            'project_nationality' => 'jordanian',
            'work_category' => 'series',
            'release_method' => 'television',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-12',
            'project_summary' => 'Other test project',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.applications.index', [
            'q' => 'Filter Match',
            'status' => 'submitted',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Filter Match Production')
            ->assertDontSeeText('Other Production');
    }

    public function test_admin_application_directory_and_dashboard_show_workflow_checkpoints(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        Application::query()->create([
            'code' => 'REQ-50001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Assign Reviewer Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-12',
            'project_summary' => 'Needs reviewer assignment.',
            'status' => 'submitted',
        ])->authorityApprovals()->create([
            'authority_code' => 'public_security',
            'status' => 'pending',
        ]);

        Application::query()->create([
            'code' => 'REQ-50002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Applicant Clarification Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'series',
            'release_method' => 'television',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-12',
            'project_summary' => 'Waiting on applicant.',
            'status' => 'needs_clarification',
            'current_stage' => 'clarification',
        ]);

        Application::query()->create([
            'code' => 'REQ-50003',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'assigned_to_user_id' => $admin->getKey(),
            'assigned_at' => now(),
            'project_name' => 'Final Decision Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'documentary',
            'release_method' => 'festival',
            'planned_start_date' => '2026-09-01',
            'planned_end_date' => '2026-09-05',
            'project_summary' => 'Ready for final decision.',
            'status' => 'submitted',
            'submitted_at' => now()->subDay(),
        ]);

        $indexResponse = $this->actingAs($admin)->get(route('admin.applications.index'));

        $indexResponse
            ->assertOk()
            ->assertSeeText('Assign reviewer')
            ->assertSeeText('Waiting on applicant')
            ->assertSeeText('Ready for final decision')
            ->assertSeeText('Assign Reviewer Project')
            ->assertSeeText('Applicant Clarification Project')
            ->assertSeeText('Final Decision Project');

        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));

        $dashboardResponse
            ->assertOk()
            ->assertSeeText('Workflow Queue')
            ->assertSeeText('Assign Reviewer Project')
            ->assertSeeText('Ready for final decision');
    }

    public function test_applicant_request_detail_page_displays_saved_metadata_fields(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload());

        $application = Application::query()->firstOrFail();

        $response = $this->actingAs($applicant)->get(route('applications.show', $application));

        $response
            ->assertOk()
            ->assertSeeText('0791111111')
            ->assertSeeText('065555556')
            ->assertSeeText('liaison@example.com')
            ->assertSeeText('0792222222')
            ->assertSeeText('Authority progress')
            ->assertSeeText('No additional authority approvals are required for this request.')
            ->assertSeeText('Final decision readiness')
            ->assertSeeText('Submit the request to start the official review workflow.')
            ->assertSeeText('Public Security Directorate')
            ->assertSeeText('Ministry of Environment')
            ->assertSeeText('120,000.00');
    }

    public function test_applicant_request_detail_surfaces_live_authority_progress_and_latest_official_step(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security', 'environment'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $approval = $application->fresh()->authorityApprovals()->where('authority_code', 'public_security')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.applications.approvals.update', [$application, $approval]), [
            'status' => 'approved',
            'note' => 'Security approval granted.',
        ]);

        $this->actingAs($admin)->post(route('admin.applications.correspondence.store', $application), [
            'subject' => 'Review Update',
            'message' => 'RFC review is continuing with the remaining authority.',
        ]);

        $response = $this->actingAs($applicant)->get(route('applications.show', $application));

        $response
            ->assertOk()
            ->assertSeeText('Authority progress')
            ->assertSeeText('1 of 2 authority reviews are resolved.')
            ->assertSeeText('There are 1 authority review responses still pending.')
            ->assertSeeText('Latest official step')
            ->assertSeeText('RFC correspondence: Review Update')
            ->assertSeeText('Final decision readiness')
            ->assertSeeText('There are 1 authority review responses still pending before the RFC can issue the final decision.');
    }

    public function test_company_dashboard_uses_template_sections_and_live_request_rows(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user, $entity] = $this->createApplicantContext();

        Application::query()->create([
            'code' => 'REQ-44001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Clarification Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-12',
            'estimated_crew_count' => 10,
            'estimated_budget' => 25000,
            'project_summary' => 'Needs applicant clarification.',
            'status' => 'needs_clarification',
            'current_stage' => 'clarification',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSeeText('Production requests')
            ->assertSeeText('Scouting requests')
            ->assertSeeText('Clarification Project')
            ->assertSeeText('Needs clarification')
            ->assertSeeText('Applicant Studio');
    }

    public function test_student_dashboard_uses_template_sections_and_live_request_rows(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user, $entity] = $this->createApplicantContext([
            'registration_type' => 'student',
            'name' => 'Student Owner',
            'username' => 'student-owner',
            'email' => 'student-owner@example.com',
            'national_id' => '9988776655',
        ], [
            'registration_type' => 'student',
            'name_en' => 'Student Profile',
            'name_ar' => 'Student Profile',
            'national_id' => '9988776655',
            'registration_no' => null,
        ]);

        Application::query()->create([
            'code' => 'REQ-55001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Student Documentary',
            'project_nationality' => 'jordanian',
            'work_category' => 'documentary',
            'release_method' => 'festival',
            'planned_start_date' => '2026-09-01',
            'planned_end_date' => '2026-09-10',
            'estimated_crew_count' => 6,
            'estimated_budget' => 8000,
            'project_summary' => 'Student dashboard row.',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSeeText('Production requests')
            ->assertSeeText('Scouting requests')
            ->assertSeeText('Student Documentary')
            ->assertSeeText('Student Profile');
    }

    public function test_applicant_can_open_live_profile_page(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Mail::fake();

        [$user, $entity] = $this->createApplicantContext([], [
            'name_en' => 'Profile Studio',
            'name_ar' => 'Profile Studio',
            'registration_no' => 'ORG-777',
            'metadata' => [
                'address' => 'Amman, Jordan',
                'description' => 'Cinema production company',
                'review_history' => [
                    [
                        'decision' => 'approve',
                        'note' => 'Registration approved successfully.',
                        'reviewed_at' => '2026-04-12 09:30:00',
                    ],
                ],
            ],
        ]);

        Application::query()->create([
            'code' => 'REQ-20001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Profile Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-12',
            'estimated_crew_count' => 22,
            'estimated_budget' => 45000,
            'project_summary' => 'Profile summary',
            'status' => 'approved',
            'current_stage' => 'approved',
            'submitted_at' => now(),
        ]);
        $user->notify(new RegistrationApprovedNotification(
            entity: $entity,
            note: 'Registration approved successfully.',
        ));

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response
            ->assertOk()
            ->assertSeeText('Profile Studio')
            ->assertSeeText('Cinema production company')
            ->assertSeeText('Member since')
            ->assertSeeText('Profile Project')
            ->assertSeeText('Production requests')
            ->assertSeeText('Scouting requests')
            ->assertSeeText('Previous projects')
            ->assertSeeText('Approval average');
    }

    public function test_profile_page_lists_only_previous_projects_in_projects_table(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user, $entity] = $this->createApplicantContext([], [
            'name_en' => 'Archive Studio',
            'name_ar' => 'Archive Studio',
            'registration_no' => 'ORG-778',
            'metadata' => [
                'address' => 'Amman, Jordan',
                'description' => 'Archive-ready production house',
            ],
        ]);

        Application::query()->create([
            'code' => 'REQ-30001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Released Feature',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-12',
            'estimated_crew_count' => 18,
            'estimated_budget' => 30000,
            'project_summary' => 'Released feature summary',
            'status' => 'approved',
            'current_stage' => 'approved',
            'submitted_at' => now()->subDays(10),
            'reviewed_at' => now()->subDays(2),
        ]);

        Application::query()->create([
            'code' => 'REQ-30002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Open Review Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'series',
            'release_method' => 'television',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-12',
            'estimated_crew_count' => 24,
            'estimated_budget' => 42000,
            'project_summary' => 'Open review summary',
            'status' => 'under_review',
            'current_stage' => 'review',
            'submitted_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk();
        $previousProjects = collect($response->viewData('previousProjects'));
        $activeWorkflowRequests = collect($response->viewData('activeWorkflowRequests'));

        $this->assertTrue($previousProjects->contains(fn ($project) => $project->project_name === 'Released Feature'));
        $this->assertFalse($previousProjects->contains(fn ($project) => $project->project_name === 'Open Review Project'));
        $this->assertTrue($activeWorkflowRequests->contains(fn ($item) => $item['project_name'] === 'Open Review Project'));
        $this->assertFalse($activeWorkflowRequests->contains(fn ($item) => $item['project_name'] === 'Released Feature'));
    }

    public function test_portal_profile_dropdown_links_point_to_dashboard_and_profile_pages(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('profile.show', ['variant' => 'foreign_producer']).'"', false);
    }

    public function test_authority_dashboard_hides_export_button_and_contains_shared_profile_route(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$applicant] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $response = $this->actingAs($authorityUser)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('profile.show', ['variant' => 'foreign_producer']).'"', false)
            ->assertDontSeeText(__('app.reports.export_current'));
    }

    public function test_foreign_producer_profile_variant_uses_role_specific_request_tables(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user, $entity] = $this->createApplicantContext([], [
            'name_en' => 'Foreign Profile Studio',
            'name_ar' => 'Foreign Profile Studio',
            'registration_no' => 'ORG-990',
        ]);

        Application::query()->create([
            'code' => 'REQ-88001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Foreign Feature',
            'project_nationality' => 'international',
            'work_category' => 'feature_film',
            'release_method' => 'festival',
            'planned_start_date' => '2026-09-01',
            'planned_end_date' => '2026-09-12',
            'estimated_crew_count' => 14,
            'estimated_budget' => 64000,
            'project_summary' => 'Foreign producer view request.',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
        ]);

        \App\Models\ScoutingRequest::query()->create([
            'code' => 'SCOUT-88001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Foreign Scout',
            'project_nationality' => 'international',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
            'metadata' => [
                'producer' => ['producer_name' => 'Foreign Producer'],
                'locations' => [],
                'crew' => [],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('profile.show', ['variant' => 'foreign_producer']));

        $response
            ->assertOk()
            ->assertSeeText('Foreign Profile Studio')
            ->assertSeeText('Foreign Producer')
            ->assertSeeText('Production Requests')
            ->assertSeeText('Foreign Feature')
            ->assertSeeText('Scouting requests')
            ->assertSeeText('Foreign Scout');
    }

    public function test_admin_can_export_filtered_applications_directory(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();

        Application::query()->create([
            'code' => 'REQ-10001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Export Match Production',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-12',
            'project_summary' => 'Match project',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
        ]);

        Application::query()->create([
            'code' => 'REQ-10002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Archive Production',
            'project_nationality' => 'jordanian',
            'work_category' => 'series',
            'release_method' => 'television',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-12',
            'project_summary' => 'Archive project',
            'status' => 'approved',
            'current_stage' => 'approved',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.applications.export', [
            'q' => 'Export Match',
            'status' => 'submitted',
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Export Match Production', $content);
        $this->assertStringNotContainsString('Archive Production', $content);
    }

    public function test_admin_can_issue_final_approval_with_letter_after_authority_reviews_resolve(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $approval = $application->authorityApprovals()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.applications.approvals.update', [$application, $approval]), [
            'status' => 'approved',
            'note' => 'Authority approval is complete.',
        ]);

        $finalizeResponse = $this->actingAs($admin)->post(route('admin.applications.finalize', $application), [
            'decision' => 'approved',
            'note' => 'All approvals are complete and the permit is issued.',
            'permit_number' => 'RFC-PERMIT-2026-001',
            'final_letter' => UploadedFile::fake()->create('final-letter.pdf', 90, 'application/pdf'),
        ]);

        $finalizeResponse->assertRedirect(route('admin.applications.show', $application));

        $application->refresh();

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'status' => 'approved',
            'current_stage' => 'approved',
            'final_decision_status' => 'approved',
            'final_permit_number' => 'RFC-PERMIT-2026-001',
            'final_decision_issued_by_user_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('permits', [
            'application_id' => $application->getKey(),
            'entity_id' => $application->entity_id,
            'permit_number' => 'RFC-PERMIT-2026-001',
            'status' => 'active',
            'issued_by_user_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('permit_audits', [
            'application_id' => $application->getKey(),
            'action' => 'issued',
            'channel' => 'system',
            'status' => 'logged',
        ]);
        $this->assertDatabaseHas('permit_audits', [
            'application_id' => $application->getKey(),
            'action' => 'delivered',
            'channel' => 'sms',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('permit_audits', [
            'application_id' => $application->getKey(),
            'action' => 'delivered',
            'channel' => 'email',
        ]);

        Storage::disk('local')->assertExists($application->final_letter_path);

        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'status' => 'approved',
            'user_id' => $admin->getKey(),
        ]);

        $showResponse = $this->actingAs($applicant)->get(route('applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('RFC-PERMIT-2026-001')
            ->assertSeeText('Approved');

        $downloadResponse = $this->actingAs($applicant)->get(route('applications.final-letter.download', $application));
        $downloadResponse->assertOk();

        $printResponse = $this->actingAs($applicant)->get(route('applications.final-letter.print', $application));
        $printResponse
            ->assertOk()
            ->assertSeeText('RFC-PERMIT-2026-001')
            ->assertSeeText('Official RFC Final Decision Letter');

        $verificationLookup = $this->get(route('permits.verify', [
            'permit_number' => 'RFC-PERMIT-2026-001',
        ]));
        $verificationLookup
            ->assertOk()
            ->assertSeeText('Permit Verification')
            ->assertSeeText('RFC-PERMIT-2026-001')
            ->assertSeeText('Desert Dreams');

        $signedVerification = $this->get(URL::signedRoute('permits.verify.signed', [
            'permit' => $application->permit,
        ]));
        $signedVerification
            ->assertOk()
            ->assertSeeText('RFC-PERMIT-2026-001');

        $this->assertTrue($applicant->fresh()->notifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'final_decision_issued'
        ));
    }

    public function test_admin_can_export_permit_registry(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $approval = ApplicationAuthorityApproval::query()->where('application_id', $application->getKey())->firstOrFail();
        $this->actingAs($admin)->post(route('admin.applications.approvals.update', [$application, $approval]), [
            'status' => 'approved',
            'note' => 'Authority approval complete.',
        ]);

        $this->actingAs($admin)->post(route('admin.applications.finalize', $application), [
            'decision' => 'approved',
            'note' => 'Permit registered.',
            'permit_number' => 'RFC-PERMIT-2026-099',
            'final_letter' => UploadedFile::fake()->create('registry-letter.pdf', 90, 'application/pdf'),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.permits.export', [
            'q' => 'RFC-PERMIT-2026-099',
            'status' => 'active',
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('RFC-PERMIT-2026-099', $content);
        $this->assertStringContainsString('Desert Dreams', $content);
    }

    public function test_admin_cannot_issue_final_decision_while_authority_approvals_are_pending(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $response = $this->actingAs($admin)->post(route('admin.applications.finalize', $application), [
            'decision' => 'approved',
            'permit_number' => 'RFC-PERMIT-2026-002',
            'note' => 'Attempting early issuance.',
        ]);

        $response
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHasErrors('decision');

        $this->assertDatabaseMissing('applications', [
            'id' => $application->getKey(),
            'final_decision_status' => 'approved',
            'final_permit_number' => 'RFC-PERMIT-2026-002',
        ]);
    }

    public function test_admin_can_open_permit_registry_after_issuing_final_approval(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $approval = ApplicationAuthorityApproval::query()->where('application_id', $application->getKey())->firstOrFail();
        $this->actingAs($admin)->post(route('admin.applications.approvals.update', [$application, $approval]), [
            'status' => 'approved',
            'note' => 'Authority approval complete.',
        ]);

        $this->actingAs($admin)->post(route('admin.applications.finalize', $application), [
            'decision' => 'approved',
            'note' => 'Permit registered.',
            'permit_number' => 'RFC-PERMIT-2026-010',
            'final_letter' => UploadedFile::fake()->create('registry-letter.pdf', 90, 'application/pdf'),
        ]);

        $permit = Permit::query()->firstOrFail();

        $registryResponse = $this->actingAs($admin)->get(route('admin.permits.index', [
            'q' => 'RFC-PERMIT-2026-010',
        ]));

        $registryResponse
            ->assertOk()
            ->assertSeeText('Permit Registry')
            ->assertSeeText('RFC-PERMIT-2026-010')
            ->assertSeeText('Desert Dreams');

        $printResponse = $this->actingAs($admin)->get(route('admin.applications.final-letter.print', $permit->application));

        $printResponse
            ->assertOk()
            ->assertSeeText('RFC-PERMIT-2026-010')
            ->assertSeeText('Applicant Studio');

        $permitShowResponse = $this->actingAs($admin)->get(route('admin.permits.show', $permit));
        $permitShowResponse
            ->assertOk()
            ->assertSeeText('Permit Audit Trail')
            ->assertSeeText('RFC-PERMIT-2026-010');
    }

    public function test_authority_user_can_export_only_scoped_inbox_requests(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$applicant] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_name' => 'Scoped Authority Request',
            'required_approvals' => ['public_security'],
        ]));
        $firstApplication = Application::query()->latest('id')->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $firstApplication));

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_name' => 'Airport Only Request',
            'required_approvals' => ['airports'],
        ]));
        $secondApplication = Application::query()->latest('id')->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $secondApplication));

        $response = $this->actingAs($authorityUser)->get(route('authority.applications.export'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Scoped Authority Request', $content);
        $this->assertStringNotContainsString('Airport Only Request', $content);
    }

    /**
     * @param  array<string, mixed>  $userOverrides
     * @param  array<string, mixed>  $entityOverrides
     * @return array{0: User, 1: Entity}
     */
    private function createApplicantContext(array $userOverrides = [], array $entityOverrides = []): array
    {
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create(array_merge([
            'name' => 'Applicant Owner',
            'username' => 'applicant-owner',
            'email' => 'applicant-owner@example.com',
            'phone' => '0793333000',
            'status' => 'active',
            'registration_type' => 'company',
            'password' => Hash::make('Applicant@123'),
        ], $userOverrides));

        $entity = Entity::query()->create(array_merge([
            'group_id' => $group->getKey(),
            'name_en' => 'Applicant Studio',
            'name_ar' => 'Applicant Studio',
            'registration_no' => 'ORG-100',
            'email' => 'studio@applicant.test',
            'phone' => '0793333111',
            'status' => 'active',
            'registration_type' => 'company',
            'metadata' => [
                'address' => 'Amman, Jordan',
            ],
        ], $entityOverrides));

        $user->entities()->attach($entity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return [$user, $entity];
    }

    /**
     * @param  array<string, mixed>  $userOverrides
     * @return array{0: User, 1: Entity}
     */
    private function createAuthorityContext(array $userOverrides = []): array
    {
        $entity = Entity::query()->where('code', 'public-security-directorate')->firstOrFail();

        $user = User::query()->create(array_merge([
            'name' => 'Authority Reviewer',
            'username' => 'authority-reviewer',
            'email' => 'authority-reviewer@example.com',
            'phone' => '0794444111',
            'status' => 'active',
            'password' => Hash::make('Authority@123'),
        ], $userOverrides));

        $user->entities()->attach($entity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('authority_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return [$user, $entity];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'project_name' => 'Desert Dreams',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-05-01',
            'planned_end_date' => '2026-05-10',
            'estimated_crew_count' => 35,
            'estimated_budget' => 120000,
            'project_summary' => 'A feature film production in Wadi Rum.',
            'producer_name' => 'Local Producer',
            'production_company_name' => 'Studio One',
            'contact_address' => 'Amman',
            'contact_phone' => '065555555',
            'contact_mobile' => '0791111111',
            'contact_fax' => '065555556',
            'contact_email' => 'producer@example.com',
            'liaison_name' => 'Liaison Person',
            'liaison_position' => 'Coordinator',
            'liaison_email' => 'liaison@example.com',
            'liaison_mobile' => '0792222222',
            'director_name' => 'Director Name',
            'director_nationality' => 'Jordanian',
            'director_profile_url' => 'https://example.com/director',
            'international_producer_name' => 'Global Partner',
            'international_producer_company' => 'Global Films',
            'required_approvals' => ['public_security', 'environment'],
            'supporting_notes' => 'Need desert location and crowd management support.',
        ], $overrides);
    }
}
