<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApprovalRoutingRule;
use App\Models\Entity;
use App\Models\Group;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Notifications\InboxMessageNotification;
use App\Notifications\RegistrationApprovedNotification;
use App\Notifications\RegistrationCompletionRequestedNotification;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_super_admin_is_redirected_to_admin_dashboard(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_seeded_super_admin_can_open_admin_dashboard(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSeeText('Super Admin Dashboard')
            ->assertSeeText('Platform Administration')
            ->assertSee('admin-dashboard-table-scroll', false)
            ->assertSee('workflow-queue-table', false)
            ->assertSee('admin-recent-requests-table', false)
            ->assertSee('.sidebar[data-sidebar="responsive"].sidebar-mobile-open', false)
            ->assertSee('js/sidebar.js?v=5.4.5', false)
            ->assertSee('data-toggle="data-table"', false);
    }

    public function test_admin_dashboard_profile_dropdown_contains_shared_profile_route(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertDontSee('href="'.route('profile.show').'"', false)
            ->assertDontSee('href="'.route('profile.show', ['variant' => 'foreign_producer']).'"', false);
    }

    public function test_organization_dashboard_profile_dropdown_only_shows_organization_profile(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'code' => 'dropdown-org',
            'name_en' => 'Dropdown Org',
            'name_ar' => 'جهة القائمة',
            'registration_no' => 'DROP-100',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'dropdown-org@example.com',
            'phone' => '065551010',
        ]);

        $user = User::query()->create([
            'name' => 'Dropdown Org User',
            'username' => 'dropdown_org_user',
            'email' => 'dropdown-org-user@example.com',
            'national_id' => '7711223344',
            'phone' => '0791010101',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this
            ->withSession(['current_entity_id' => $entity->getKey()])
            ->actingAs($user)
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('profile.show').'"', false)
            ->assertDontSee('href="'.route('profile.show', ['variant' => 'foreign_producer']).'"', false)
            ->assertDontSee('href="'.route('admin.dashboard').'"', false);
    }

    public function test_seeded_super_admin_can_export_admin_dashboard_summary(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.reports.export'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('metrics', $content);
        $this->assertStringContainsString('Configured groups', $content);
    }

    public function test_rfc_reviewer_is_redirected_to_internal_dashboard_and_sees_only_allowed_menu_items(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $entity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();

        $user = User::query()->create([
            'name' => 'RFC Reviewer',
            'username' => 'rfc_reviewer_panel',
            'email' => 'rfc.reviewer.panel@example.com',
            'national_id' => '7755443322',
            'phone' => '0791116655',
            'status' => 'active',
            'registration_type' => 'staff',
            'password' => Hash::make('password123'),
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('rfc_reviewer');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $dashboardRedirect = $this
            ->withSession(['current_entity_id' => $entity->getKey()])
            ->actingAs($user)
            ->get(route('dashboard'));

        $dashboardRedirect->assertRedirect(route('admin.dashboard'));

        $response = $this
            ->withSession(['current_entity_id' => $entity->getKey()])
            ->actingAs($user)
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSeeText(__('app.admin.navigation.applications'))
            ->assertSeeText(__('app.admin.navigation.scouting_requests'))
            ->assertSeeText(__('app.admin.navigation.contact_center'))
            ->assertSeeText(__('app.admin.navigation.permits'))
            ->assertSeeText(__('app.admin.applications.title'))
            ->assertSeeText(__('app.admin.applications.intro'))
            ->assertSeeText(__('app.admin.dashboard.workflow_context_title'))
            ->assertSeeText(__('app.roles.rfc_reviewer'))
            ->assertSeeText($entity->displayName())
            ->assertSee('admin-dashboard-table-scroll', false)
            ->assertSee('workflow-queue-table', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee('href="'.route('admin.entities.index').'"', false)
            ->assertDontSee('href="'.route('admin.groups.index').'"', false)
            ->assertDontSee('href="'.route('admin.integrations.index').'"', false)
            ->assertDontSee(route('admin.reports.export'), false);
    }

    public function test_admin_sidebar_shows_live_response_and_inbox_counters(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'code' => 'counter-studio',
            'name_en' => 'Counter Studio',
            'name_ar' => 'Counter Studio',
            'registration_no' => 'CNT-100',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'counter@example.com',
            'phone' => '065555000',
        ]);

        $user = User::query()->create([
            'name' => 'Counter Applicant',
            'username' => 'counter-applicant',
            'email' => 'counter-applicant@example.com',
            'national_id' => '9090909090',
            'phone' => '0799090909',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-COUNTER-1',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Counter Application',
            'project_nationality' => 'jordanian',
            'work_category' => 'documentary',
            'release_method' => 'festival',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-02',
            'project_summary' => 'Counter application summary.',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'review_note' => 'Need revised file.',
            'reviewed_at' => now()->subHour(),
            'submitted_at' => now()->subHours(2),
        ]);

        $application->statusHistory()->create([
            'user_id' => $admin->getKey(),
            'status' => 'needs_clarification',
            'note' => 'Need revised file.',
            'happened_at' => now()->subHour(),
        ]);

        $application->documents()->create([
            'uploaded_by_user_id' => $user->getKey(),
            'document_type' => 'work_content_summary',
            'title' => 'Counter Revised Form',
            'file_path' => 'application-documents/counter/revised.pdf',
            'original_name' => 'revised.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'submitted',
        ]);

        $scoutingRequest = ScoutingRequest::query()->create([
            'code' => 'SCOUT-COUNTER-1',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Counter Scout',
            'project_nationality' => 'jordanian',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'review_note' => 'Need updated schedule.',
            'reviewed_at' => now()->subHour(),
            'submitted_at' => now()->subHours(2),
            'metadata' => [
                'producer' => ['producer_name' => 'Counter Producer'],
                'locations' => [],
                'crew' => [],
            ],
        ]);

        $scoutingRequest->statusHistory()->create([
            'user_id' => $admin->getKey(),
            'status' => 'needs_clarification',
            'note' => 'Need updated schedule.',
            'happened_at' => now()->subHour(),
        ]);

        $scoutingRequest->correspondences()->create([
            'created_by_user_id' => $user->getKey(),
            'sender_type' => 'applicant',
            'sender_name' => $entity->displayName(),
            'subject' => 'Updated schedule',
            'message' => 'Sharing the updated scouting schedule.',
        ]);

        $admin->notify(new InboxMessageNotification(
            typeKey: 'application_correspondence',
            title: 'Counter inbox message',
            body: 'A fresh applicant message arrived.',
            routeName: 'admin.applications.show',
            routeParameters: ['application' => $application->getKey()],
        ));

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-sidebar-counter="applications">1<', false)
            ->assertSee('data-sidebar-counter="scouting_requests">1<', false)
            ->assertSee('data-sidebar-counter="contact_center">1<', false);
    }

    public function test_assigned_rfc_reviewer_can_open_application_from_notification_and_submit_review(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $rfcEntity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();
        $orgGroup = Group::query()->where('code', 'organizations')->firstOrFail();
        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $reviewer = User::query()->create([
            'name' => 'Assigned Reviewer',
            'username' => 'assigned_reviewer',
            'email' => 'assigned-reviewer@example.com',
            'national_id' => '7766554433',
            'phone' => '0791112233',
            'status' => 'active',
            'registration_type' => 'staff',
            'password' => Hash::make('password123'),
        ]);

        $rfcEntity->users()->attach($reviewer->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($rfcEntity->getKey());
        $reviewer->assignRole('rfc_reviewer');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $applicantEntity = Entity::query()->create([
            'group_id' => $orgGroup->getKey(),
            'code' => 'reviewer-test-company',
            'name_en' => 'Reviewer Test Company',
            'name_ar' => 'شركة اختبار المراجع',
            'registration_no' => 'REV-100',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'reviewer-test@example.com',
            'phone' => '065555100',
        ]);

        $applicant = User::query()->create([
            'name' => 'Applicant User',
            'username' => 'applicant_user',
            'email' => 'applicant-user@example.com',
            'national_id' => '8080808080',
            'phone' => '0798080808',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $applicantEntity->users()->attach($applicant->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-REVIEWER-1',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Reviewer Notification Flow',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-05',
            'project_summary' => 'Test application for reviewer assignment flow.',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.applications.assign', $application), [
                'assigned_to_user_id' => $reviewer->getKey(),
            ])
            ->assertRedirect(route('admin.applications.show', $application));

        $reviewer->refresh();
        $notification = $reviewer->notifications()->latest()->first();

        $this->assertNotNull($notification);
        $this->assertSame('application_assignment', data_get($notification->data, 'type_key'));

        $this->withSession(['current_entity_id' => $rfcEntity->getKey()])
            ->actingAs($reviewer)
            ->get(route('notifications.redirect', $notification->getKey()))
            ->assertRedirect(route('admin.applications.show', $application));

        $this->withSession(['current_entity_id' => $rfcEntity->getKey()])
            ->actingAs($reviewer)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeText(__('app.admin.applications.review_title'));

        $this->withSession(['current_entity_id' => $rfcEntity->getKey()])
            ->actingAs($reviewer)
            ->post(route('admin.applications.review', $application), [
                'decision' => 'under_review',
                'note' => 'Reviewer accepted the assignment.',
            ])
            ->assertRedirect(route('admin.applications.show', $application));

        $application->refresh();

        $this->assertSame('under_review', $application->status);
        $this->assertSame($reviewer->getKey(), $application->reviewed_by_user_id);
    }

    public function test_user_with_rfc_reviewer_and_rfc_approver_roles_can_see_final_decision_when_request_is_ready(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $rfcEntity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();
        $orgGroup = Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create([
            'name' => 'RFC Combined Reviewer',
            'username' => 'rfc_combined_reviewer',
            'email' => 'rfc-combined@example.com',
            'national_id' => '7711223344',
            'phone' => '0791118899',
            'status' => 'active',
            'registration_type' => 'staff',
            'password' => Hash::make('password123'),
        ]);

        $rfcEntity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($rfcEntity->getKey());
        $user->assignRole('rfc_reviewer');
        $user->assignRole('rfc_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $applicantEntity = Entity::query()->create([
            'group_id' => $orgGroup->getKey(),
            'code' => 'combined-role-company',
            'name_en' => 'Combined Role Company',
            'name_ar' => 'شركة الدور المزدوج',
            'registration_no' => 'COMB-100',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'combined-role@example.com',
            'phone' => '065550900',
        ]);

        $applicant = User::query()->create([
            'name' => 'Combined Role Applicant',
            'username' => 'combined_role_applicant',
            'email' => 'combined-role-applicant@example.com',
            'national_id' => '7000000001',
            'phone' => '0797000001',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $applicantEntity->users()->attach($applicant->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-COMB-1',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Combined Role Final Decision',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-05',
            'project_summary' => 'Ready for final decision.',
            'status' => 'under_review',
            'current_stage' => 'rfc_review',
            'submitted_at' => now()->subDay(),
        ]);

        $response = $this->withSession(['current_entity_id' => $rfcEntity->getKey()])
            ->actingAs($user)
            ->get(route('admin.applications.show', $application));

        $response
            ->assertOk()
            ->assertSeeText(__('app.admin.applications.review_title'))
            ->assertSeeText(__('app.final_decision.title'))
            ->assertSeeText(__('app.final_decision.submit'));
    }

    public function test_super_admin_can_create_an_authority_user_with_scoped_role(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $entity = Entity::query()->where('code', 'ministry-of-interior')->firstOrFail();
        $organizationGroup = Group::query()->where('code', 'organizations')->firstOrFail();
        $applicantEntity = Entity::query()->create([
            'group_id' => $organizationGroup->getKey(),
            'code' => 'authority-backfill-studio',
            'name_en' => 'Authority Backfill Studio',
            'name_ar' => 'شركة اختبار إشعار الجهة',
            'registration_no' => 'AUTH-BACKFILL-1',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'authority-backfill-studio@example.com',
            'phone' => '065559900',
        ]);
        $applicant = User::query()->create([
            'name' => 'Authority Backfill Applicant',
            'username' => 'authority-backfill-applicant',
            'email' => 'authority-backfill-applicant@example.com',
            'national_id' => '1111222200',
            'phone' => '0797777022',
            'status' => 'active',
            'registration_type' => 'company',
            'password' => Hash::make('Password@123'),
        ]);
        $application = Application::query()->create([
            'code' => 'REQ-AUTH-BACKFILL',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Authority Backfill Request',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-09-01',
            'planned_end_date' => '2026-09-03',
            'project_summary' => 'Open approval should be sent to newly added authority users.',
            'status' => 'submitted',
            'current_stage' => 'authority_approvals',
            'submitted_at' => now()->subHour(),
        ]);
        $approval = ApplicationAuthorityApproval::query()->create([
            'application_id' => $application->getKey(),
            'authority_code' => 'airports',
            'entity_id' => $entity->getKey(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Authority Manager',
            'username' => 'authority_manager',
            'email' => 'authority.manager@example.com',
            'national_id' => '1111222233',
            'phone' => '0797777000',
            'password' => 'Authority@123',
            'password_confirmation' => 'Authority@123',
            'entity_id' => $entity->getKey(),
            'roles' => ['authority_approver'],
            'job_title' => 'Director',
            'is_primary' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'authority.manager@example.com')->firstOrFail();

        $this->assertSame('staff', $user->registration_type);
        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $entity->getKey(),
            'user_id' => $user->getKey(),
            'job_title' => 'Director',
            'is_primary' => 1,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $this->assertTrue($user->hasRole('authority_approver'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->assertTrue($user->fresh()->unreadNotifications->contains(
            fn ($notification): bool => data_get($notification->data, 'type_key') === 'authority_approval_requested'
                && (int) data_get($notification->data, 'authority_approval_id') === $approval->getKey()
        ));
    }

    public function test_super_admin_can_create_user_with_multiple_scoped_roles(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $entity = Entity::query()->where('code', 'ministry-of-interior')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Authority Team Lead',
            'username' => 'authority_team_lead',
            'email' => 'authority.teamlead@example.com',
            'national_id' => '1111222244',
            'phone' => '0797777011',
            'password' => 'Authority@123',
            'password_confirmation' => 'Authority@123',
            'entity_id' => $entity->getKey(),
            'roles' => ['authority_reviewer', 'authority_approver'],
            'job_title' => 'Team Lead',
            'is_primary' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'authority.teamlead@example.com')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $this->assertTrue($user->hasRole('authority_reviewer'));
        $this->assertTrue($user->hasRole('authority_approver'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->assertDatabaseHas('user_role_assignment_audits', [
            'user_id' => $user->getKey(),
            'entity_id' => $entity->getKey(),
            'role_name' => 'authority_reviewer',
            'action' => 'added',
            'changed_by_user_id' => $admin->getKey(),
        ]);

        $this->assertDatabaseHas('user_role_assignment_audits', [
            'user_id' => $user->getKey(),
            'entity_id' => $entity->getKey(),
            'role_name' => 'authority_approver',
            'action' => 'added',
            'changed_by_user_id' => $admin->getKey(),
        ]);
    }

    public function test_super_admin_can_add_multiple_roles_to_user_membership(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $entity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();
        $user = User::query()->create([
            'name' => 'RFC Dual Role User',
            'username' => 'rfc-dual-role-user',
            'email' => 'rfc-dual-role-user@example.com',
            'national_id' => '5555666677',
            'phone' => '0797333222',
            'status' => 'active',
            'registration_type' => 'staff',
            'password' => Hash::make('Password@123'),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.memberships.store', $user->getKey()), [
            'entity_id' => $entity->getKey(),
            'roles' => ['rfc_reviewer', 'rfc_approver'],
            'job_title' => 'Committee Member',
            'is_primary' => 1,
        ]);

        $response->assertRedirect(route('admin.users.show', $user->getKey()));

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $this->assertTrue($user->fresh()->hasRole('rfc_reviewer'));
        $this->assertTrue($user->fresh()->hasRole('rfc_approver'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $entity->getKey(),
            'user_id' => $user->getKey(),
            'job_title' => 'Committee Member',
            'is_primary' => 1,
        ]);
    }

    public function test_seeded_super_admin_can_open_producers_directory(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $companyEntity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Creative Production House',
            'name_ar' => 'Creative Production House',
            'registration_no' => 'COM-200',
            'registration_type' => 'company',
            'status' => 'active',
        ]);

        $studentEntity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Film Student',
            'name_ar' => 'Film Student',
            'registration_no' => 'STU-300',
            'registration_type' => 'student',
            'status' => 'active',
        ]);

        $companyUser = User::query()->create([
            'name' => 'Company Owner',
            'username' => 'company-owner',
            'email' => 'company-owner@example.com',
            'national_id' => '2000000001',
            'phone' => '0792000001',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $studentUser = User::query()->create([
            'name' => 'Student Producer',
            'username' => 'student-producer',
            'email' => 'student-producer@example.com',
            'national_id' => '2000000002',
            'phone' => '0792000002',
            'registration_type' => 'student',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $companyEntity->forceFill([
            'status' => 'pending_review',
            'email' => 'company-owner@example.com',
            'phone' => '0792000001',
            'metadata' => [
                'address' => 'Amman',
                'description' => 'Production company',
                'review' => [
                    'note' => 'Pending review',
                ],
            ],
        ])->save();
        $companyEntity->users()->attach($companyUser->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $studentEntity->forceFill([
            'national_id' => '2000000002',
            'email' => 'student-producer@example.com',
            'phone' => '0792000002',
        ])->save();
        $studentEntity->users()->attach($studentUser->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.producers.index'));

        $response
            ->assertOk()
            ->assertSeeText('Producers')
            ->assertSee('admin-producers-table-scroll', false)
            ->assertSee('admin-producers-table', false)
            ->assertSeeText('Creative Production House')
            ->assertSeeText('Film Student')
            ->assertSeeText('Company Owner')
            ->assertSeeText('Student Producer')
            ->assertSeeText(__('app.registration_types.company'))
            ->assertSeeText(__('app.registration_types.student'));
    }

    public function test_super_admin_can_open_entity_create_page(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.entities.create'));

        $response
            ->assertOk()
            ->assertSeeText('Create New Entity')
            ->assertSeeText('Create entity');
    }

    public function test_super_admin_can_filter_entities_index(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Filter Match Entity',
            'name_ar' => 'Filter Match Entity',
            'registration_no' => 'FILTER-100',
            'status' => 'pending_review',
            'registration_type' => 'company',
        ]);

        Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Other Entity',
            'name_ar' => 'Other Entity',
            'registration_no' => 'OTHER-200',
            'status' => 'active',
            'registration_type' => 'company',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.entities.index', [
            'q' => 'FILTER-100',
            'status' => 'pending_review',
        ]));

        $response
            ->assertOk()
            ->assertSee('admin-entities-index-layout', false)
            ->assertSee('admin-entities-directory-table-scroll', false)
            ->assertSee('admin-entities-directory-table', false)
            ->assertSee('<col style="width: 180px">', false)
            ->assertSeeText('Filter Match Entity')
            ->assertDontSeeText('Other Entity');
    }

    public function test_super_admin_can_see_authority_entities_in_entities_directory(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.entities.index'));

        $response
            ->assertOk()
            ->assertSee('admin-entities-internal-table-scroll', false)
            ->assertSee('admin-entities-internal-table', false)
            ->assertSee('<col style="width: 130px">', false)
            ->assertSeeText('Official and Internal Entities')
            ->assertSeeText('Authorities')
            ->assertSeeText('Public Security Directorate');
    }

    public function test_authority_entity_profile_shows_routing_and_workload_context(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $authorityEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();
        $organizationGroup = Group::query()->where('code', 'organizations')->firstOrFail();

        $applicant = User::query()->create([
            'name' => 'Authority Profile Applicant',
            'username' => 'authority_profile_applicant',
            'email' => 'authority-profile-applicant@example.com',
            'phone' => '0793666000',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $applicantEntity = Entity::query()->create([
            'group_id' => $organizationGroup->getKey(),
            'name_en' => 'Authority Profile Company',
            'name_ar' => 'شركة ملف الجهة',
            'registration_no' => 'AUTH-PROFILE-100',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'authority-profile-company@example.com',
            'phone' => '065551212',
        ]);

        $applicantEntity->users()->attach($applicant->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-AUTH-100',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Authority Routed Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-10',
            'project_summary' => 'A project routed to an authority profile.',
            'status' => 'under_review',
            'current_stage' => 'authority_review',
            'submitted_at' => now()->subDays(2),
        ]);

        $rule = ApprovalRoutingRule::query()->create([
            'name' => 'Municipal Routing Rule',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $authorityEntity->getKey(),
            'conditions' => [],
            'priority' => 10,
            'is_active' => true,
        ]);

        ApplicationAuthorityApproval::query()->create([
            'application_id' => $application->getKey(),
            'authority_code' => 'municipalities',
            'entity_id' => $authorityEntity->getKey(),
            'approval_routing_rule_id' => $rule->getKey(),
            'status' => 'in_review',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.entities.show', $authorityEntity));

        $response
            ->assertOk()
            ->assertSee('admin-entity-authority-routing-table', false)
            ->assertSee('admin-entity-authority-workload-table', false)
            ->assertSeeText('Authority Operations')
            ->assertSeeText('Municipal Routing Rule')
            ->assertSeeText('Authority Request Workload')
            ->assertSeeText('Authority Routed Project');
    }

    public function test_super_admin_can_create_default_routing_rule_from_authority_entity_profile(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $authorityEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.entities.authority-routing.store', $authorityEntity), [
            'name' => 'Direct Authority Rule',
            'approval_code' => 'drones',
            'priority' => 25,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.entities.show', $authorityEntity));

        $this->assertDatabaseHas('approval_routing_rules', [
            'name' => 'Direct Authority Rule',
            'request_type' => 'application',
            'approval_code' => 'drones',
            'target_entity_id' => $authorityEntity->getKey(),
            'priority' => 25,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_manage_member_roles_from_entity_profile(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $authorityEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $member = User::query()->create([
            'name' => 'Entity Role Member',
            'username' => 'entity_role_member',
            'email' => 'entity-role-member@example.com',
            'phone' => '0793888000',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $storeResponse = $this->actingAs($admin)->post(route('admin.entities.members.store', $authorityEntity), [
            'user_id' => $member->getKey(),
            'roles' => ['authority_reviewer', 'authority_approver'],
            'job_title' => 'Authority Member',
            'is_primary' => 1,
        ]);

        $storeResponse->assertRedirect(route('admin.entities.show', $authorityEntity));

        $this->assertTrue($member->fresh()->roleNamesForEntity($authorityEntity)->contains('authority_reviewer'));
        $this->assertTrue($member->fresh()->roleNamesForEntity($authorityEntity)->contains('authority_approver'));

        $deleteResponse = $this->actingAs($admin)->post(route('admin.entities.members.roles.delete', [
            'entity' => $authorityEntity->getKey(),
            'user' => $member->getKey(),
            'role' => 'authority_reviewer',
        ]));

        $deleteResponse->assertRedirect(route('admin.entities.show', $authorityEntity));

        $this->assertFalse($member->fresh()->roleNamesForEntity($authorityEntity)->contains('authority_reviewer'));
        $this->assertTrue($member->fresh()->roleNamesForEntity($authorityEntity)->contains('authority_approver'));
    }

    public function test_super_admin_can_manage_member_lifecycle_from_entity_profile(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $authorityEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $firstMember = User::query()->create([
            'name' => 'Entity Lifecycle Member One',
            'username' => 'entity_lifecycle_member_one',
            'email' => 'entity-lifecycle-member-one@example.com',
            'phone' => '0793888111',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $secondMember = User::query()->create([
            'name' => 'Entity Lifecycle Member Two',
            'username' => 'entity_lifecycle_member_two',
            'email' => 'entity-lifecycle-member-two@example.com',
            'phone' => '0793888222',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $this->actingAs($admin)->post(route('admin.entities.members.store', $authorityEntity), [
            'user_id' => $firstMember->getKey(),
            'roles' => ['authority_reviewer'],
            'job_title' => 'First Member',
            'is_primary' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.entities.members.store', $authorityEntity), [
            'user_id' => $secondMember->getKey(),
            'roles' => ['authority_approver'],
            'job_title' => 'Second Member',
            'is_primary' => 0,
        ]);

        $primaryResponse = $this->actingAs($admin)->post(route('admin.entities.members.primary', [
            'entity' => $authorityEntity->getKey(),
            'user' => $secondMember->getKey(),
        ]));

        $primaryResponse->assertRedirect(route('admin.entities.show', $authorityEntity));

        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $authorityEntity->getKey(),
            'user_id' => $secondMember->getKey(),
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $authorityEntity->getKey(),
            'user_id' => $firstMember->getKey(),
            'is_primary' => false,
        ]);

        $statusResponse = $this->actingAs($admin)->post(route('admin.entities.members.status', [
            'entity' => $authorityEntity->getKey(),
            'user' => $firstMember->getKey(),
        ]), [
            'status' => 'inactive',
        ]);

        $statusResponse->assertRedirect(route('admin.entities.show', $authorityEntity));

        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $authorityEntity->getKey(),
            'user_id' => $firstMember->getKey(),
            'status' => 'inactive',
        ]);

        $deleteResponse = $this->actingAs($admin)->post(route('admin.entities.members.delete', [
            'entity' => $authorityEntity->getKey(),
            'user' => $secondMember->getKey(),
        ]));

        $deleteResponse->assertRedirect(route('admin.entities.show', $authorityEntity));

        $this->assertDatabaseMissing('entity_user', [
            'entity_id' => $authorityEntity->getKey(),
            'user_id' => $secondMember->getKey(),
        ]);
        $this->assertFalse($secondMember->fresh()->roleNamesForEntity($authorityEntity)->contains('authority_approver'));
    }

    public function test_super_admin_can_manage_authority_default_delegation_from_entity_profile(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $authorityEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $delegate = User::query()->create([
            'name' => 'Authority Delegate',
            'username' => 'authority_delegate',
            'email' => 'authority-delegate@example.com',
            'phone' => '0793888333',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $this->actingAs($admin)->post(route('admin.entities.members.store', $authorityEntity), [
            'user_id' => $delegate->getKey(),
            'roles' => ['authority_approver'],
            'job_title' => 'Delegate',
            'is_primary' => 0,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.entities.authority-delegation.update', $authorityEntity), [
            'approval_code' => 'municipalities',
            'assigned_user_id' => $delegate->getKey(),
        ]);

        $response->assertRedirect(route('admin.entities.show', $authorityEntity));

        $this->assertSame(
            $delegate->getKey(),
            $authorityEntity->fresh()->authorityDelegatedUserIdFor('municipalities'),
        );

        $showResponse = $this->actingAs($admin)->get(route('admin.entities.show', $authorityEntity));

        $showResponse
            ->assertOk()
            ->assertSee('admin-entity-authority-delegation-table', false)
            ->assertSeeText('Authority Inbox Delegation')
            ->assertSeeText('Authority Delegate');
    }

    public function test_super_admin_can_manage_authority_response_times_from_dedicated_page(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $authorityEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();
        $rfcEntity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();

        $rfcAdmin = User::query()->create([
            'name' => 'RFC SLA Admin',
            'username' => 'rfc_sla_admin',
            'email' => 'rfc-sla-admin@example.com',
            'phone' => '0793222333',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $rfcAdmin->entities()->attach($rfcEntity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($rfcEntity->getKey());
        $rfcAdmin->assignRole('rfc_admin');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->actingAs($admin)->post(route('admin.authority-escalations.update', $authorityEntity), [
            'response_time_days' => '٢',
            'escalation_user_ids' => [$admin->getKey()],
            'escalation_role_names' => ['rfc_admin'],
        ]);

        $response->assertRedirect(route('admin.authority-escalations.index'));

        $settings = $authorityEntity->fresh()->authoritySlaSettings();

        $this->assertSame(2, $settings['response_time_days']);
        $this->assertSame([$admin->getKey()], $settings['escalation_user_ids']);
        $this->assertSame(['rfc_admin'], $settings['escalation_role_names']);

        $pageResponse = $this->actingAs($admin)->get(route('admin.authority-escalations.index'));

        $pageResponse
            ->assertOk()
            ->assertSeeText('Authority Response Time Control')
            ->assertSeeText('Greater Amman Municipality')
            ->assertSee('inputmode="numeric"', false)
            ->assertSeeText('RFC Admin');
    }

    public function test_super_admin_can_open_and_export_authority_escalation_report(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $authorityEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();
        $organizationGroup = Group::query()->where('code', 'organizations')->firstOrFail();
        $authorityHandler = User::query()->create([
            'name' => 'Authority Drilldown Owner',
            'username' => 'authority_drilldown_owner',
            'email' => 'authority-drilldown-owner@example.com',
            'national_id' => '8080808080',
            'phone' => '0795552200',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);
        $replacementHandler = User::query()->create([
            'name' => 'Authority Replacement Owner',
            'username' => 'authority_replacement_owner',
            'email' => 'authority-replacement-owner@example.com',
            'national_id' => '8080808081',
            'phone' => '0795552201',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $authorityEntity->users()->attach($authorityHandler->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $authorityEntity->users()->attach($replacementHandler->getKey(), [
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($authorityEntity->getKey());
        $authorityHandler->assignRole('authority_approver');
        $replacementHandler->assignRole('authority_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $authorityEntity->forceFill([
            'metadata' => [
                ...($authorityEntity->metadata ?? []),
                'authority_sla' => [
                    'response_time_days' => 2,
                    'escalation_user_ids' => [$admin->getKey()],
                    'escalation_role_names' => [],
                ],
            ],
        ])->save();

        $applicantEntity = Entity::query()->create([
            'group_id' => $organizationGroup->getKey(),
            'code' => 'sla-report-org',
            'name_en' => 'SLA Report Studio',
            'name_ar' => 'شركة تقرير الاستجابة',
            'registration_no' => 'SLA-200',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'sla-report-studio@example.com',
            'phone' => '065551200',
        ]);

        $applicant = User::query()->create([
            'name' => 'SLA Report Applicant',
            'username' => 'sla_report_applicant',
            'email' => 'sla-report-applicant@example.com',
            'national_id' => '9090909090',
            'phone' => '0795551200',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-SLA-200',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Escalation Report Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-11-01',
            'planned_end_date' => '2026-11-14',
            'estimated_crew_count' => 20,
            'estimated_budget' => 45000,
            'project_summary' => 'Used to verify the authority SLA report.',
            'status' => 'submitted',
            'current_stage' => 'authority_approvals',
            'submitted_at' => now()->subDays(5),
        ]);

        $approval = $application->authorityApprovals()->create([
            'authority_code' => 'municipality',
            'entity_id' => $authorityEntity->getKey(),
            'assigned_user_id' => $authorityHandler->getKey(),
            'assigned_at' => now()->subDays(2),
            'status' => 'in_review',
            'escalated_at' => now()->subDay(),
        ]);

        $approval->forceFill([
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        $application->statusHistory()->create([
            'user_id' => null,
            'status' => $application->status,
            'note' => __('app.workflow.history.authority_escalated', [
                'authority' => $approval->localizedAuthority(),
            ]),
            'metadata' => [
                'type' => 'authority_escalated',
                'approval_id' => $approval->getKey(),
                'authority_code' => $approval->authority_code,
            ],
            'happened_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.authority-escalations.report', [
            'window' => '30',
            'authority' => $authorityEntity->getKey(),
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Authority SLA Bottleneck Report')
            ->assertSeeText('Greater Amman Municipality')
            ->assertSeeText('Most delayed live approvals')
            ->assertSeeText('Escalation Report Project')
            ->assertSeeText('Open request')
            ->assertSeeText('Greater Amman Municipality queue and escalation trail')
            ->assertSeeText('Live authority queue')
            ->assertSeeText('Recent escalation activity')
            ->assertSeeText('Back to all authorities')
            ->assertSeeText('Open owner')
            ->assertSeeText('Open authority')
            ->assertSeeText('Bulk reassignment')
            ->assertSeeText('Quick reassignment')
            ->assertSee('action="'.route('admin.applications.approvals.assign', [$application, $approval]).'"', false)
            ->assertSee('action="'.route('admin.authority-escalations.bulk-assign', $authorityEntity).'"', false)
            ->assertSee('href="'.route('admin.users.show', $authorityHandler).'"', false)
            ->assertSee('href="'.route('admin.entities.show', $authorityEntity).'"', false);

        $bulkAssignResponse = $this->actingAs($admin)->post(route('admin.authority-escalations.bulk-assign', $authorityEntity), [
            'window' => '30',
            'approval_ids' => [$approval->getKey()],
            'assigned_user_id' => $replacementHandler->getKey(),
            'assignment_note' => 'Shift this overdue item to the backup approver.',
        ]);

        $bulkAssignResponse->assertRedirect(route('admin.authority-escalations.report', [
            'window' => '30',
            'authority' => $authorityEntity->getKey(),
        ]));

        $this->assertDatabaseHas('application_authority_approvals', [
            'id' => $approval->getKey(),
            'assigned_user_id' => $replacementHandler->getKey(),
        ]);

        $exportResponse = $this->actingAs($admin)->get(route('admin.authority-escalations.export', [
            'window' => '30',
            'authority' => $authorityEntity->getKey(),
        ]));

        $exportResponse->assertOk();
        $content = $exportResponse->streamedContent();

        $this->assertStringContainsString('Greater Amman Municipality', $content);
        $this->assertStringContainsString('Municipalities', $content);
    }

    public function test_super_admin_can_filter_users_index(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        User::query()->create([
            'name' => 'Pending NGO User',
            'username' => 'pending-ngo-user',
            'email' => 'pending-ngo@example.com',
            'phone' => '0797111000',
            'status' => 'pending_review',
            'registration_type' => 'ngo',
            'password' => Hash::make('password123'),
        ]);

        User::query()->create([
            'name' => 'Active Student User',
            'username' => 'active-student-user',
            'email' => 'active-student@example.com',
            'national_id' => '3003003003',
            'phone' => '0797222000',
            'status' => 'active',
            'registration_type' => 'student',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'status' => 'pending_review',
            'registration_type' => 'ngo',
        ]));

        $response
            ->assertOk()
            ->assertSee('admin-users-index-layout', false)
            ->assertSee('admin-users-directory-table-scroll', false)
            ->assertSee('admin-users-directory-table', false)
            ->assertSee('<col style="width: 270px">', false)
            ->assertSeeText('Pending NGO User')
            ->assertDontSeeText('Active Student User');
    }

    public function test_super_admin_can_remove_a_role_from_existing_user_membership(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $rfcEntity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Role Removal User',
            'username' => 'role-removal-user',
            'email' => 'role-removal-user@example.com',
            'national_id' => '4999555566',
            'phone' => '0797444000',
            'status' => 'active',
            'registration_type' => 'staff',
            'password' => Hash::make('password123'),
        ]);

        $user->entities()->attach($rfcEntity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($rfcEntity->getKey());
        $user->assignRole('rfc_reviewer');
        $user->assignRole('rfc_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->actingAs($admin)->post(route('admin.users.memberships.roles.delete', [
            'user' => $user->getKey(),
            'entity' => $rfcEntity->getKey(),
            'role' => 'rfc_reviewer',
        ]));

        $response->assertRedirect(route('admin.users.show', $user->getKey()));

        app(PermissionRegistrar::class)->setPermissionsTeamId($rfcEntity->getKey());
        $this->assertFalse($user->fresh()->hasRole('rfc_reviewer'));
        $this->assertTrue($user->fresh()->hasRole('rfc_approver'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->assertDatabaseHas('user_role_assignment_audits', [
            'user_id' => $user->getKey(),
            'entity_id' => $rfcEntity->getKey(),
            'role_name' => 'rfc_reviewer',
            'action' => 'removed',
            'changed_by_user_id' => $admin->getKey(),
        ]);

        $showResponse = $this->actingAs($admin)->get(route('admin.users.show', $user->getKey()));

        $showResponse
            ->assertOk()
            ->assertSee('admin-user-memberships-table', false)
            ->assertSee('admin-user-role-history-table', false)
            ->assertSeeText('Role Change History')
            ->assertSeeText(__('app.roles.rfc_reviewer'))
            ->assertSeeText('Removed');
    }

    public function test_users_index_displays_internal_users_without_explicit_registration_type_in_staff_tab(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $rfcEntity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();

        $user = User::query()->create([
            'name' => 'RFC Reviewer Hidden',
            'username' => 'rfc-reviewer-hidden',
            'email' => 'rfc-reviewer-hidden@example.com',
            'national_id' => '4444555566',
            'phone' => '0797333000',
            'status' => 'active',
            'password' => Hash::make('password123'),
        ]);

        $user->entities()->attach($rfcEntity->getKey(), [
            'job_title' => 'Reviewer',
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertSeeText('RFC Reviewer Hidden')
            ->assertSeeText(__('app.registration_types.staff'));
    }

    public function test_rfc_approver_sees_unresolved_authority_approvals_when_final_decision_is_blocked(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $rfcEntity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();
        $targetAuthority = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();
        $orgGroup = Group::query()->where('code', 'organizations')->firstOrFail();

        $approver = User::query()->create([
            'name' => 'RFC Approver Only',
            'username' => 'rfc_approver_only',
            'email' => 'rfc-approver-only@example.com',
            'national_id' => '7111222233',
            'phone' => '0797444999',
            'status' => 'active',
            'registration_type' => 'staff',
            'password' => Hash::make('password123'),
        ]);

        $rfcEntity->users()->attach($approver->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($rfcEntity->getKey());
        $approver->assignRole('rfc_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $applicantEntity = Entity::query()->create([
            'group_id' => $orgGroup->getKey(),
            'code' => 'blocked-decision-company',
            'name_en' => 'Blocked Decision Company',
            'name_ar' => 'شركة القرار المعلق',
            'registration_no' => 'BLK-100',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'blocked-decision@example.com',
            'phone' => '065550901',
        ]);

        $applicant = User::query()->create([
            'name' => 'Blocked Decision Applicant',
            'username' => 'blocked_decision_applicant',
            'email' => 'blocked-decision-applicant@example.com',
            'national_id' => '7000000002',
            'phone' => '0797000002',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $applicantEntity->users()->attach($applicant->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-BLOCK-1',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Blocked Final Decision',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-05',
            'project_summary' => 'Still waiting on authority.',
            'status' => 'under_review',
            'current_stage' => 'authority_review',
            'submitted_at' => now()->subDay(),
        ]);

        ApplicationAuthorityApproval::query()->create([
            'application_id' => $application->getKey(),
            'authority_code' => 'municipalities',
            'entity_id' => $targetAuthority->getKey(),
            'status' => 'pending',
            'note' => 'Waiting for authority review.',
        ]);

        $response = $this->withSession(['current_entity_id' => $rfcEntity->getKey()])
            ->actingAs($approver)
            ->get(route('admin.applications.show', $application));

        $response
            ->assertOk()
            ->assertSeeText(__('app.final_decision.approver_waiting_hint'))
            ->assertSeeText(__('app.final_decision.pending_approvals_detail_title'))
            ->assertSeeText($targetAuthority->displayName())
            ->assertSeeText(__('app.approvals.statuses.pending'));
    }

    public function test_super_admin_can_filter_groups_index(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.groups.index', [
            'role' => 'authority_approver',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Authorities')
            ->assertDontSeeText('Individuals');
    }

    public function test_super_admin_can_approve_pending_registration_entity(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Review Org',
            'username' => 'review-org',
            'email' => 'review-org@example.com',
            'phone' => '0794444000',
            'status' => 'pending_review',
            'registration_type' => 'company',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Review Org',
            'name_ar' => 'Review Org',
            'registration_no' => 'REV-001',
            'email' => 'review-org@example.com',
            'phone' => '0794444000',
            'status' => 'pending_review',
            'registration_type' => 'company',
            'metadata' => [
                'address' => 'Amman',
                'registration_document_path' => 'registration-documents/company/license.pdf',
            ],
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->actingAs($admin)->post(route('admin.entities.review', $entity), [
            'decision' => 'approve',
            'note' => 'Registration approved.',
        ]);

        $response->assertRedirect(route('admin.entities.show', $entity));

        $this->assertDatabaseHas('entities', [
            'id' => $entity->getKey(),
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'status' => 'active',
        ]);

        $entity->refresh();
        $this->assertSame('approve', data_get($entity->metadata, 'review.decision'));
        $this->assertCount(1, data_get($entity->metadata, 'review_history', []));
    }

    public function test_review_history_is_appended_on_multiple_review_actions(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create([
            'name' => 'History Org',
            'username' => 'history-org',
            'email' => 'history-org@example.com',
            'phone' => '0798888000',
            'status' => 'pending_review',
            'registration_type' => 'ngo',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'History Org',
            'name_ar' => 'History Org',
            'registration_no' => 'HIS-100',
            'email' => 'history-org@example.com',
            'phone' => '0798888000',
            'status' => 'pending_review',
            'registration_type' => 'ngo',
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), [
            'decision' => 'needs_completion',
            'note' => 'First note',
        ])->assertRedirect(route('admin.entities.show', $entity));

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), [
            'decision' => 'approve',
            'note' => 'Second note',
        ])->assertRedirect(route('admin.entities.show', $entity));

        $entity->refresh();

        $this->assertSame('approve', data_get($entity->metadata, 'review.decision'));
        $this->assertSame('Second note', data_get($entity->metadata, 'review.note'));
        $this->assertCount(2, data_get($entity->metadata, 'review_history', []));
        $this->assertSame('First note', data_get($entity->metadata, 'review_history.0.note'));
        $this->assertSame('Second note', data_get($entity->metadata, 'review_history.1.note'));
    }

    public function test_review_decision_sends_completion_notification_with_signed_link(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Notification::fake();

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $owner = User::query()->create([
            'name' => 'NGO Primary Owner',
            'username' => 'ngo-primary-owner',
            'email' => 'ngo-primary@example.com',
            'phone' => '0799555000',
            'status' => 'pending_review',
            'registration_type' => 'ngo',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Notification NGO',
            'name_ar' => 'Notification NGO',
            'registration_no' => 'NGO-NTF-1',
            'email' => 'ngo-primary@example.com',
            'phone' => '0799555000',
            'status' => 'pending_review',
            'registration_type' => 'ngo',
        ]);

        $entity->users()->attach($owner->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), [
            'decision' => 'needs_completion',
            'note' => 'Please update the attached NGO registration file.',
        ])->assertRedirect(route('admin.entities.show', $entity));

        Notification::assertSentTo(
            $owner,
            RegistrationCompletionRequestedNotification::class,
            function (RegistrationCompletionRequestedNotification $notification, array $channels) use ($owner, $entity): bool {
                $this->assertContains('database', $channels);
                $this->assertContains('mail', $channels);

                $payload = $notification->toArray($owner);

                $this->assertSame('registration_completion_requested', data_get($payload, 'type_key'));
                $this->assertStringContainsString('/en/registration/link/'.$entity->getKey().'/complete?', (string) data_get($payload, 'url'));

                return true;
            }
        );
    }

    public function test_review_approval_sends_registration_approved_notification(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Notification::fake();

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $owner = User::query()->create([
            'name' => 'Company Primary Owner',
            'username' => 'company-primary-owner',
            'email' => 'company-primary@example.com',
            'phone' => '0799444000',
            'status' => 'pending_review',
            'registration_type' => 'company',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Approved Company',
            'name_ar' => 'Approved Company',
            'registration_no' => 'CO-APR-1',
            'email' => 'company-primary@example.com',
            'phone' => '0799444000',
            'status' => 'pending_review',
            'registration_type' => 'company',
        ]);

        $entity->users()->attach($owner->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), [
            'decision' => 'approve',
            'note' => 'All registration details are valid.',
        ])->assertRedirect(route('admin.entities.show', $entity));

        Notification::assertSentTo(
            $owner,
            RegistrationApprovedNotification::class,
            function (RegistrationApprovedNotification $notification, array $channels) use ($owner): bool {
                $this->assertContains('database', $channels);
                $this->assertContains('mail', $channels);

                $payload = $notification->toArray($owner);

                $this->assertSame('registration_approved', data_get($payload, 'type_key'));
                $this->assertSame('dashboard', data_get($payload, 'route_name'));

                return true;
            }
        );
    }

    public function test_super_admin_can_see_saved_registration_details_on_entity_page(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $owner = User::query()->create([
            'name' => 'NGO Owner',
            'username' => 'ngo-owner',
            'email' => 'ngo-owner@example.com',
            'phone' => '0795555000',
            'status' => 'pending_review',
            'registration_type' => 'ngo',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Cultural NGO',
            'name_ar' => 'Cultural NGO',
            'registration_no' => 'NGO-100',
            'email' => 'ngo@example.com',
            'phone' => '0795555111',
            'status' => 'needs_completion',
            'registration_type' => 'ngo',
            'metadata' => [
                'address' => 'Amman, Jordan',
                'description' => 'Community cinema workshops.',
                'registration_document_path' => 'registration-documents/ngo/certificate.pdf',
                'registration_document_name' => 'certificate.pdf',
                'registration_document_mime' => 'application/pdf',
                'review' => [
                    'decision' => 'needs_completion',
                    'note' => 'Please upload a clearer certificate scan.',
                    'reviewed_at' => '2026-04-11 10:30:00',
                    'reviewed_by_user_id' => $admin->getKey(),
                ],
            ],
        ]);

        $entity->users()->attach($owner->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.entities.show', $entity));

        $response
            ->assertOk()
            ->assertSee('admin-entity-members-table', false)
            ->assertSee('admin-entity-review-history-table', false)
            ->assertSeeText('Registration Details')
            ->assertSeeText('Community cinema workshops.')
            ->assertSeeText('certificate.pdf')
            ->assertSeeText('application/pdf')
            ->assertSeeText('NGO Owner')
            ->assertSeeText('ngo-owner@example.com')
            ->assertSeeText('Please upload a clearer certificate scan.')
            ->assertSeeText('superadmin@rfc.local');
    }

    public function test_super_admin_can_see_saved_registration_details_on_user_page(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Company Owner',
            'username' => 'company-owner',
            'email' => 'company-owner@example.com',
            'phone' => '0796666000',
            'status' => 'pending_review',
            'registration_type' => 'company',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Studio One',
            'name_ar' => 'Studio One',
            'registration_no' => 'CO-700',
            'email' => 'studio@example.com',
            'phone' => '0796666111',
            'status' => 'needs_completion',
            'registration_type' => 'company',
            'metadata' => [
                'address' => 'Amman Media City',
                'description' => 'Feature film production.',
                'registration_document_path' => 'registration-documents/company/license.pdf',
                'registration_document_name' => 'license.pdf',
                'registration_document_mime' => 'application/pdf',
                'review' => [
                    'decision' => 'needs_completion',
                    'note' => 'Please update the trade license copy.',
                    'reviewed_at' => '2026-04-11 13:20:00',
                    'reviewed_by_user_id' => $admin->getKey(),
                ],
            ],
        ]);

        $user->entities()->attach($entity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $user));

        $response
            ->assertOk()
            ->assertSee('admin-user-show-layout', false)
            ->assertSee('admin-user-memberships-table', false)
            ->assertSeeText('Registration and Entity Details')
            ->assertSeeText('Studio One')
            ->assertSeeText('CO-700')
            ->assertSeeText('Amman Media City')
            ->assertSeeText('Feature film production.')
            ->assertSeeText('license.pdf')
            ->assertSeeText('application/pdf')
            ->assertSeeText('Please update the trade license copy.')
            ->assertSeeText('superadmin@rfc.local');
    }

    public function test_super_admin_can_update_and_soft_delete_user(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $user = User::query()->where('email', 'authority.manager@example.com')->first();

        if (! $user) {
            $entity = Entity::query()->where('code', 'ministry-of-interior')->firstOrFail();

            $createResponse = $this->actingAs($admin)->post(route('admin.users.store'), [
                'name' => 'Authority Manager',
                'username' => 'authority_manager',
                'email' => 'authority.manager@example.com',
                'national_id' => '1111222233',
                'phone' => '0797777000',
                'password' => 'Authority@123',
                'password_confirmation' => 'Authority@123',
                'entity_id' => $entity->getKey(),
                'roles' => ['authority_approver'],
                'job_title' => 'Director',
                'is_primary' => 1,
            ]);

            $createResponse->assertRedirect(route('admin.users.index'));
            $user = User::query()->where('email', 'authority.manager@example.com')->firstOrFail();
        }

        $updateResponse = $this->actingAs($admin)->post(route('admin.users.update', $user->getKey()), [
            'name' => 'Authority Manager Updated',
            'username' => 'authority_manager_updated',
            'email' => 'authority.manager.updated@example.com',
            'national_id' => '1111222233',
            'phone' => '0797777000',
            'status' => 'inactive',
        ]);

        $updateResponse->assertRedirect(route('admin.users.show', $user->getKey()));

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'Authority Manager Updated',
            'status' => 'inactive',
        ]);

        $deleteResponse = $this->actingAs($admin)->post(route('admin.users.delete', $user->getKey()));
        $deleteResponse->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', [
            'id' => $user->getKey(),
        ]);
    }

    public function test_super_admin_can_restore_soft_deleted_user(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $user = User::query()->create([
            'name' => 'Restore User',
            'username' => 'restore-user',
            'email' => 'restore-user@example.com',
            'national_id' => '1234500000',
            'phone' => '0791234500',
            'status' => 'inactive',
            'password' => Hash::make('password123'),
        ]);
        $user->delete();

        $response = $this->actingAs($admin)->post(route('admin.users.restore', $user->getKey()));
        $response->assertRedirect(route('admin.users.show', $user->getKey()));

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'status' => 'active',
            'deleted_at' => null,
        ]);
    }

    public function test_super_admin_can_soft_delete_and_restore_entity(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $entity = Entity::query()->where('code', 'ministry-of-interior')->firstOrFail();

        $deleteResponse = $this->actingAs($admin)->post(route('admin.entities.delete', $entity->getKey()));
        $deleteResponse->assertRedirect(route('admin.entities.index'));

        $this->assertSoftDeleted('entities', [
            'id' => $entity->getKey(),
        ]);

        $restoreResponse = $this->actingAs($admin)->post(route('admin.entities.restore', $entity->getKey()));
        $restoreResponse->assertRedirect(route('admin.entities.show', $entity->getKey()));

        $this->assertDatabaseHas('entities', [
            'id' => $entity->getKey(),
            'deleted_at' => null,
        ]);
    }
}
