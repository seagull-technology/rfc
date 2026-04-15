<?php

namespace Tests\Feature;

use App\Models\Application;
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
            ->assertSeeText('Platform Administration');
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

    public function test_super_admin_can_create_an_authority_user_with_scoped_role(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $entity = Entity::query()->where('code', 'ministry-of-interior')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Authority Manager',
            'username' => 'authority_manager',
            'email' => 'authority.manager@example.com',
            'national_id' => '1111222233',
            'phone' => '0797777000',
            'password' => 'Authority@123',
            'password_confirmation' => 'Authority@123',
            'entity_id' => $entity->getKey(),
            'role' => 'authority_approver',
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
            ->assertSeeText('Filter Match Entity')
            ->assertDontSeeText('Other Entity');
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
            ->assertSeeText('Pending NGO User')
            ->assertDontSeeText('Active Student User');
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
                'role' => 'authority_approver',
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
