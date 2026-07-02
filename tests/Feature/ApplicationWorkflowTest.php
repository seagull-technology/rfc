<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApprovalRoutingRule;
use App\Models\Entity;
use App\Models\Group;
use App\Models\Permit;
use App\Models\ReleaseMethod;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Models\WorkCategory;
use App\Notifications\RegistrationApprovedNotification;
use App\Services\ApplicationAuthorityApprovalSyncService;
use App\Services\AuthorityApprovalNotificationService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
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

        $response = $this
            ->withSession([
                '_old_input' => [
                    'cast_crew' => [
                        'lead' => [
                            'name' => 'Lead Actor',
                            'role' => 'Lead',
                            'nationality' => 'jordanian',
                            'identity_number' => '1234567890',
                        ],
                    ],
                    'filming_locations' => [
                        'main-location' => [
                            'governorate' => 'amman',
                            'location_name' => 'Downtown Amman',
                        ],
                    ],
                    'equipment_travelers' => [
                        'handler' => [
                            'traveler_name' => 'Equipment Handler',
                        ],
                    ],
                    'airport_people' => [
                        'airport-person' => [
                            'full_name' => 'Airport Crew Member',
                            'nationality' => 'jordanian',
                        ],
                    ],
                    'imported_equipment' => [
                        'shipping' => [
                            'item' => 'Camera package',
                            'serial_number' => 'CAM-001',
                            'quantity' => 1,
                        ],
                        'traveler_0' => [
                            'transport_group' => 'traveler',
                            'item' => 'Traveler lens kit',
                            'traveler_name' => 'Equipment Handler',
                        ],
                    ],
                ],
            ])
            ->actingAs($user)
            ->get(route('applications.create'));

        $response
            ->assertOk()
            ->assertSeeText(__('app.applications.create_title'))
            ->assertSeeText(__('app.applications.general_information'))
            ->assertSeeText(__('app.applications.requirements_list'))
            ->assertDontSee('data-confirm-submit', false)
            ->assertDontSeeText(__('app.applications.submit_confirm_body'))
            ->assertSee('id="form-wizard1"', false)
            ->assertSee('id="step1"', false)
            ->assertSee('id="step2"', false)
            ->assertSee('class="btn btn-danger request-wizard-next action-button float-end btn-lg"', false)
            ->assertSee('value="egyptian"', false)
            ->assertSeeText('Egyptian')
            ->assertSeeText(__('app.applications.approval_route_preview_title'))
            ->assertSeeText(__('app.applications.traveler_customs_instructions')[0])
            ->assertSee('data-equipment-traveler-select', false)
            ->assertSee('name="imported_equipment[traveler_0][traveler_name]"', false)
            ->assertSeeText('Equipment Handler')
            ->assertSee('name="airport_people[airport-person][nationality]"', false)
            ->assertSee('data-application-password-strength', false)
            ->assertSeeText(__('app.auth.password_rule_mixed'))
            ->assertSee('js/form-wizard.js', false);
    }

    public function test_international_account_password_validation_uses_localized_label(): void
    {
        $this->refreshApplicationWithLocale('ar');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'international_account_password' => 'lowercase1!',
                'international_account_password_confirmation' => 'lowercase1!',
            ]))
            ->assertSessionHasErrors(['international_account_password']);

        $errors = session('errors')->get('international_account_password');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('كلمة مرور حساب ضابط الارتباط الدولي', $errors[0]);
    }

    public function test_application_form_accepts_lookup_backed_nationalities(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $response = $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'project_nationality' => 'egyptian',
                'director_nationality' => 'egyptian',
                'international_producer_nationality' => 'american',
            ]));

        $application = Application::query()->firstOrFail();

        $response->assertRedirect(route('applications.show', $application));

        $this->assertSame('egyptian', $application->project_nationality);
        $this->assertSame('egyptian', data_get($application->metadata, 'director.director_nationality'));
        $this->assertSame('american', data_get($application->metadata, 'international.international_producer_nationality'));

        $this
            ->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSeeText('Egyptian');
    }

    public function test_application_form_accepts_lookup_backed_work_and_release_values(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        WorkCategory::query()->create([
            'code' => 'immersive_film',
            'name_en' => 'Immersive film',
            'name_ar' => 'فيلم تفاعلي',
            'sort_order' => 8,
            'is_active' => true,
        ]);

        ReleaseMethod::query()->create([
            'code' => 'community_screening',
            'name_en' => 'Community screening',
            'name_ar' => 'عرض مجتمعي',
            'sort_order' => 8,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('applications.create'))
            ->assertOk()
            ->assertSeeText('Immersive film')
            ->assertSeeText('Community screening');

        $response = $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'work_category' => 'immersive_film',
                'work_categories' => ['immersive_film'],
                'release_method' => 'community_screening',
                'release_methods' => ['community_screening'],
            ]));

        $application = Application::query()->firstOrFail();

        $response->assertRedirect(route('applications.show', $application));

        $this->assertSame('immersive_film', $application->work_category);
        $this->assertSame('community_screening', $application->release_method);
        $this->assertSame(['immersive_film'], data_get($application->metadata, 'project.work_categories'));
        $this->assertSame(['community_screening'], data_get($application->metadata, 'project.release_methods'));
    }

    public function test_application_form_validates_location_type_against_selected_governorate(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->from(route('applications.create'))
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'amman',
                    'location_name' => 'Petra Visitor Center',
                    'location_type' => 'petra',
                    'start_date' => '2026-05-02',
                    'end_date' => '2026-05-03',
                ]],
            ]))
            ->assertRedirect(route('applications.create'))
            ->assertSessionHasErrors('filming_locations.0.location_type');

        $this->assertDatabaseCount('applications', 0);

        $response = $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'maan',
                    'location_name' => 'Petra Visitor Center',
                    'location_type' => 'petra',
                    'start_date' => '2026-05-02',
                    'end_date' => '2026-05-03',
                ]],
            ]));

        $application = Application::query()->firstOrFail();

        $response->assertRedirect(route('applications.show', $application));
        $this->assertSame('maan', data_get($application->metadata, 'annex.filming_locations.0.governorate'));
        $this->assertSame('petra', data_get($application->metadata, 'annex.filming_locations.0.location_type'));
    }

    public function test_application_form_validates_schedule_phase_sequence(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->from(route('applications.create'))
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'planned_start_date' => '2026-05-01',
                'planned_end_date' => '2026-05-10',
                'schedule_phases' => [
                    'preparation' => ['start_date' => '2026-04-20', 'end_date' => '2026-05-02'],
                    'wrap' => ['start_date' => '2026-05-09', 'end_date' => '2026-05-12'],
                    'post_production' => ['start_date' => '2026-05-11', 'end_date' => '2026-06-01'],
                ],
            ]))
            ->assertRedirect(route('applications.create'))
            ->assertSessionHasErrors([
                'planned_start_date',
                'schedule_phases.wrap.start_date',
                'schedule_phases.post_production.start_date',
            ]);

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_wrap_report_is_locked_until_wrap_end_date(): void
    {
        try {
            $this->refreshApplicationWithLocale('en');
            Carbon::setTestNow('2026-05-11 10:00:00');
            $this->seed(AccessControlSeeder::class);

            [$user] = $this->createApplicantContext();

            $this
                ->actingAs($user)
                ->post(route('applications.store'), $this->applicationPayload());

            $application = Application::query()->firstOrFail();

            $this
                ->actingAs($user)
                ->get(route('applications.show', $application))
                ->assertOk()
                ->assertSeeText('Wrap report')
                ->assertSee('nav-link disabled', false);

            $this
                ->actingAs($user)
                ->post(route('applications.wrap-report.update', $application), $this->wrapReportPayload())
                ->assertForbidden();

            $this->assertDatabaseCount('application_wrap_reports', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_applicant_can_submit_wrap_report_after_wrap_end_date_and_admin_can_view_it(): void
    {
        try {
            $this->refreshApplicationWithLocale('en');
            Carbon::setTestNow('2026-05-13 10:00:00');
            $this->seed(AccessControlSeeder::class);

            $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
            [$user] = $this->createApplicantContext();

            $this
                ->actingAs($user)
                ->post(route('applications.store'), $this->applicationPayload());

            $application = Application::query()->firstOrFail();

            $this
                ->actingAs($user)
                ->get(route('applications.show', $application))
                ->assertOk()
                ->assertSeeText('Production Wrap Report')
                ->assertSeeText('Number of Local Crew');

            $this
                ->actingAs($user)
                ->post(route('applications.wrap-report.update', $application), $this->wrapReportPayload([
                    'local_crew_count' => 42,
                    'total_local_spending_jod' => 73500,
                ]))
                ->assertRedirect(route('applications.show', $application).'#profile-wrap-report');

            $wrapReport = $application->fresh()->wrapReport()->firstOrFail();

            $this->assertSame('submitted', $wrapReport->status);
            $this->assertSame(42, data_get($wrapReport->payload, 'local_crew_count'));
            $this->assertSame(73500, data_get($wrapReport->payload, 'total_local_spending_jod'));
            $this->assertSame(12, data_get($wrapReport->payload, 'rented_cars_total_days'));
            $this->assertSame(26, data_get($wrapReport->payload, 'total_production_days'));

            $this
                ->actingAs($admin)
                ->get(route('admin.applications.show', $application))
                ->assertOk()
                ->assertSeeText('Production Wrap Report')
                ->assertSeeText('Number of Local Crew')
                ->assertSeeText('73500');
        } finally {
            Carbon::setTestNow();
        }
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
        $this->assertSame([], data_get($application->metadata, 'requirements.required_approvals'));
        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('data-application-submit-confirm', false)
            ->assertSee('applicationSubmitConfirmationModal', false)
            ->assertDontSee('window.confirm', false)
            ->assertDontSee('new window.bootstrap.Modal', false)
            ->assertDontSee('data-bs-dismiss="modal"', false)
            ->assertDontSee("classList.add('modal-open')", false)
            ->assertSee('Are you sure?');

        $submitResponse = $this->actingAs($user)->post(route('applications.submit', $application));

        $submitResponse->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'status' => 'submitted',
            'current_stage' => 'intake',
        ]);
        $this->assertSame(
            [],
            data_get($application->fresh()->metadata, 'requirements.required_approvals')
        );
        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'status' => 'submitted',
        ]);
        $this->assertDatabaseMissing('application_authority_approvals', [
            'application_id' => $application->getKey(),
        ]);
        $this->assertTrue($admin->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'application_submitted'
        ));
        $this->assertFalse($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_requested'
        ));

        $acceptResponse = $this->actingAs($admin)->post(route('admin.applications.review', $application), [
            'decision' => 'accepted',
        ]);

        $acceptResponse->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'status' => 'under_review',
            'current_stage' => 'rfc_facilitation',
        ]);
        $this->assertSame('accepted', data_get($application->fresh()->metadata, 'rfc_decision.status'));
        $this->assertSame($admin->getKey(), data_get($application->fresh()->metadata, 'rfc_decision.decided_by_user_id'));
        $this->assertSame($admin->getKey(), $application->fresh()->reviewed_by_user_id);
        $this->assertDatabaseMissing('application_authority_approvals', [
            'application_id' => $application->getKey(),
        ]);

        $adminShowResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $adminShowResponse
            ->assertOk()
            ->assertSeeText('RFC decision recorded by')
            ->assertSeeText($admin->displayName())
            ->assertSeeText('Issue facilitation book')
            ->assertDontSeeText('Waiting on authority responses');

        $issueResponse = $this->actingAs($admin)->post(route('admin.applications.issue-facilitation-letter', $application));

        $issueResponse->assertRedirect(route('admin.applications.show', $application));

        $this->assertSame(
            ['public_security', 'environment'],
            data_get($application->fresh()->metadata, 'requirements.required_approvals')
        );
        $this->assertNotNull(data_get($application->fresh()->metadata, 'rfc_decision.facilitation_issued_at'));
        $this->assertSame($admin->getKey(), data_get($application->fresh()->metadata, 'rfc_decision.facilitation_issued_by_user_id'));
        $this->assertDatabaseMissing('application_authority_approvals', [
            'application_id' => $application->getKey(),
        ]);
        $this->assertDatabaseHas('application_official_letters', [
            'application_id' => $application->getKey(),
            'application_authority_approval_id' => null,
            'serial_number' => $application->code.'-BOOK-01',
            'status' => 'draft',
        ]);
        $this->assertSame(2, $application->fresh()->officialLetters()->count());
        $this->assertFalse($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_requested'
        ));

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Issue Official Books')
            ->assertSee('admin-official-letters-table', false)
            ->assertSee('officialLetterSend', false)
            ->assertSee('data-application-submit-confirm', false)
            ->assertSee('Print book', false)
            ->assertSee('Send book', false)
            ->assertSeeText('Review official books')
            ->assertDontSeeText('Waiting on authority responses');

        $publicSecurityEntity = Entity::query()->where('code', 'public-security-directorate')->firstOrFail();
        $publicSecurityLetter = $application->fresh()->officialLetters()
            ->where('target_entity_id', $publicSecurityEntity->getKey())
            ->firstOrFail();

        $directoryShowResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $directoryShowResponse
            ->assertOk()
            ->assertSee('data-bs-target="#officialLetterViewDirectory'.$publicSecurityLetter->getKey().'"', false)
            ->assertSee('id="officialLetterViewDirectory'.$publicSecurityLetter->getKey().'"', false)
            ->assertSee('data-bs-target="#officialLetterEditDirectory'.$publicSecurityLetter->getKey().'"', false)
            ->assertSee('id="officialLetterEditDirectory'.$publicSecurityLetter->getKey().'"', false)
            ->assertSee('form="officialLetterSendDirectory'.$publicSecurityLetter->getKey().'"', false)
            ->assertSee('id="officialLetterSendDirectory'.$publicSecurityLetter->getKey().'"', false);

        $printResponse = $this->actingAs($admin)->get(route('admin.applications.official-letters.print', [$application, $publicSecurityLetter]));

        $printResponse
            ->assertOk()
            ->assertSeeText('Official book')
            ->assertSeeText($application->code.'-BOOK-01')
            ->assertSeeText($publicSecurityLetter->subject)
            ->assertSeeText('Print book');

        $sendResponse = $this->actingAs($admin)->post(route('admin.applications.official-letters.send', [$application, $publicSecurityLetter]));

        $sendResponse->assertRedirect(route('admin.applications.show', $application));

        $publicSecurityLetter->refresh();

        $this->assertSame('issued', $publicSecurityLetter->status);
        $this->assertNotNull($publicSecurityLetter->issued_at);
        $this->assertNotNull($publicSecurityLetter->application_authority_approval_id);
        $this->assertSame(1, $application->fresh()->authorityApprovals()->count());
        $this->assertDatabaseHas('application_authority_approvals', [
            'id' => $publicSecurityLetter->application_authority_approval_id,
            'application_id' => $application->getKey(),
            'entity_id' => $publicSecurityEntity->getKey(),
            'status' => 'pending',
        ]);
        $this->assertTrue($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_requested'
        ));
        $this->assertTrue($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'official_letter_issued'
                && (int) data_get($notification->data, 'official_letter_id') === $publicSecurityLetter->getKey()
        ));
    }

    public function test_application_stores_and_displays_structured_annex_forms(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant, $applicantEntity] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'work_categories' => ['feature_film', 'documentary', 'other'],
            'work_category_other' => 'Hybrid docudrama',
            'release_methods' => ['cinema', 'web', 'other'],
            'release_method_other' => 'Community screenings',
	            'schedule_phases' => [
	                'preparation' => ['start_date' => '2026-04-20', 'end_date' => '2026-04-30'],
	                'wrap' => ['start_date' => '2026-05-10', 'end_date' => '2026-05-10'],
	                'post_production' => ['start_date' => '2026-05-11', 'end_date' => '2026-05-30'],
	            ],
            'local_spend_estimate' => 55000,
            'budget_items' => [
                'jordanian_actors' => ['units' => 3, 'total' => 5000],
                'equipment_costs' => ['units' => 2, 'total' => 12000],
            ],
            'international_producer_nationality' => 'non_jordanian',
            'international_producer_email' => 'global@example.com',
            'international_producer_profile_url' => 'https://example.com/global-producer',
            'international_producer_address' => 'Toronto',
            'international_producer_website' => 'https://globalfilms.example.com',
            'international_liaison_name' => 'Global Liaison',
            'international_liaison_email' => 'liaison.global@example.com',
            'international_liaison_mobile' => '+962799999999',
            'international_account_password' => 'International@12345',
            'international_account_password_confirmation' => 'International@12345',
            'work_content_summary_synopsis' => 'A road sequence with controlled public access.',
            'work_content_summary_sensitive_notes' => 'Contains a simulated checkpoint scene.',
            'work_content_summary_confirmed' => '1',
	            'cast_crew' => [
	                ['name' => 'Jordanian Lead', 'role' => 'Actor', 'nationality' => 'Jordanian', 'gender' => 'male', 'birth_date' => '1990-03-15', 'identity_number' => 'J12345'],
	            ],
            'filming_locations' => [
                ['governorate' => 'amman', 'location_name' => 'Downtown Amman', 'address' => 'GPS pin 31.9,35.9', 'nature' => 'Open public street', 'location_type' => 'public_locations', 'start_date' => '2026-05-02', 'end_date' => '2026-05-03', 'notes' => 'Traffic support needed'],
            ],
            'special_location_requirements' => [
                'road_closures' => ['locations' => ['Downtown Amman'], 'notes' => 'Road lockup from 6 AM'],
            ],
            'safety_guidelines_acknowledged' => '1',
            'safety_guidelines_notes' => 'No pyrotechnics. Traffic marshals requested.',
            'equipment_flights' => [
                ['flight_type' => 'arrival', 'flight_number' => 'RJ101', 'flight_date' => '2026-04-27', 'flight_time' => '10:30', 'departure_city' => 'Berlin', 'arrival_city' => 'Amman'],
            ],
            'equipment_travelers' => [
                ['traveler_name' => 'Equipment Handler', 'arrival_date' => '2026-04-28', 'arrival_flight_number' => 'RJ102', 'departure_date' => '2026-05-12', 'departure_flight_number' => 'RJ103'],
            ],
            'traveler_equipment_acknowledged' => '1',
            'imported_equipment' => [
                ['transport_group' => 'shipping', 'item' => 'Camera crane', 'serial_number' => 'CR-7788', 'flight_reference' => 'RJ101', 'quantity' => 1, 'unit_value' => 9000, 'total_value' => 9000, 'classification' => 'Grip', 'shipping_method' => 'Freight', 'origin_country' => 'Germany', 'entry_point' => 'Queen Alia Airport', 'arrival_date' => '2026-04-28'],
            ],
            'military_border_locations' => [
                ['governorate' => 'mafraq', 'location_name' => 'Border training area', 'address' => 'Northern range', 'nature' => 'Controlled zone', 'location_type' => 'border_area', 'start_date' => '2026-05-06', 'end_date' => '2026-05-07'],
            ],
            'military_border_equipment' => [
                ['location_name' => 'Border training area', 'equipment' => 'Long lens kit', 'security_need' => 'Escort required', 'notes' => 'No drone use', 'item' => 'Long lens kit', 'serial_number' => 'LL-4455', 'location_reference' => 'Border training area', 'quantity' => 2, 'unit_value' => 1500, 'total_value' => 3000, 'classification' => 'Camera', 'entry_method' => 'Vehicle', 'entry_point' => 'Border gate'],
            ],
            'military_border_equipment_acknowledged' => '1',
            'airport_filming_airport_name' => 'Queen Alia International Airport',
            'airport_filming_area' => 'Departures hall',
            'airport_filming_date' => '2026-05-04',
            'airport_filming_crew_count' => 12,
            'airport_filming_notes' => 'Small handheld crew.',
            'airport_people' => [
                ['full_name' => 'Airport Crew Member', 'nationality' => 'jordanian', 'mother_name' => 'Mariam', 'identity_number' => '9876543210', 'profession' => 'Camera operator', 'address_phone' => 'Amman 0790000000', 'entry_reason' => 'Filming', 'target_area' => 'Departures hall'],
            ],
            'governmental_scenes' => [
                ['site_name' => 'Municipal archive', 'authority' => 'Greater Amman Municipality', 'scene_description' => 'Exterior establishing shot', 'filming_date' => '2026-05-05'],
            ],
            'governmental_scenes_confirmed' => '1',
        ]));

        $application = Application::query()->firstOrFail();

        $this->assertSame('A road sequence with controlled public access.', data_get($application->metadata, 'annex.work_content_summary.synopsis'));
        $this->assertSame(['feature_film', 'documentary', 'other'], data_get($application->metadata, 'project.work_categories'));
        $this->assertSame('Hybrid docudrama', data_get($application->metadata, 'project.work_category_other'));
        $this->assertSame('2026-04-20', data_get($application->metadata, 'schedule.phases.preparation.start_date'));
        $this->assertSame(55000, data_get($application->metadata, 'budget.local_spend_estimate'));
        $this->assertSame(12000, data_get($application->metadata, 'budget.items.equipment_costs.total'));
        $this->assertSame('non_jordanian', data_get($application->metadata, 'international.international_producer_nationality'));
        $this->assertSame('liaison.global@example.com', data_get($application->metadata, 'international.account.email'));
        $this->assertTrue(data_get($application->metadata, 'international.account.read_only'));
	        $this->assertTrue(data_get($application->metadata, 'annex.work_content_summary.confirmed'));
	        $this->assertSame('Jordanian Lead', data_get($application->metadata, 'annex.cast_crew.0.name'));
	        $this->assertSame('male', data_get($application->metadata, 'annex.cast_crew.0.gender'));
	        $this->assertSame('1990-03-15', data_get($application->metadata, 'annex.cast_crew.0.birth_date'));
	        $this->assertSame('Downtown Amman', data_get($application->metadata, 'annex.filming_locations.0.location_name'));
        $this->assertSame('GPS pin 31.9,35.9', data_get($application->metadata, 'annex.filming_locations.0.address'));
        $this->assertSame('Road lockup from 6 AM', data_get($application->metadata, 'annex.special_location_requirements.road_closures.notes'));
        $this->assertSame('RJ101', data_get($application->metadata, 'annex.equipment_flights.0.flight_number'));
        $this->assertSame('Equipment Handler', data_get($application->metadata, 'annex.equipment_travelers.0.traveler_name'));
        $this->assertSame('Camera crane', data_get($application->metadata, 'annex.imported_equipment.0.item'));
        $this->assertSame('RJ101', data_get($application->metadata, 'annex.imported_equipment.0.flight_reference'));
        $this->assertSame('Northern range', data_get($application->metadata, 'annex.military_border_locations.0.address'));
        $this->assertSame('LL-4455', data_get($application->metadata, 'annex.military_border_equipment.0.serial_number'));
        $this->assertSame('Queen Alia International Airport', data_get($application->metadata, 'annex.airport_filming.airport_name'));
        $this->assertSame('Airport Crew Member', data_get($application->metadata, 'annex.airport_people.0.full_name'));
        $this->assertSame('jordanian', data_get($application->metadata, 'annex.airport_people.0.nationality'));
        $this->assertTrue(data_get($application->metadata, 'annex.governmental_scenes_confirmed'));

        $internationalUser = User::query()->where('email', 'liaison.global@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('International@12345', $internationalUser->password));
        $this->assertTrue($internationalUser->entities()->whereKey($applicantEntity->getKey())->exists());

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($applicantEntity->getKey());

        try {
            $this->assertTrue($internationalUser->can('applications.view.entity'));
            $this->assertFalse($internationalUser->can('applications.create'));
            $this->assertFalse($internationalUser->can('applications.update.entity'));
        } finally {
            $registrar->setPermissionsTeamId(null);
        }

        $this->actingAs($internationalUser)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSeeText('Desert Dreams');

        $this->actingAs($applicant)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('applicant-annex-table', false)
            ->assertSee('data-annex-add-button', false)
            ->assertSee('WorkContentSummary', false)
            ->assertSee('EquipmentMilitaryBorder', false)
            ->assertSeeText('Attached Annexes:')
            ->assertSeeText('Add annex')
            ->assertSee('WorkContentSummaryView', false)
            ->assertSee('EquipmentMilitaryBorderView', false)
            ->assertSeeText('View form')
            ->assertDontSeeText('Uploaded files')
            ->assertSeeText('Jordanian Lead')
            ->assertSeeText('Downtown Amman')
            ->assertSeeText('GPS pin 31.9,35.9')
            ->assertSeeText('Queen Alia International Airport');

        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Camera crane')
            ->assertSeeText('RJ101')
            ->assertSeeText('Airport Crew Member')
            ->assertSeeText('Municipal archive');

        $this->actingAs($authorityUser)
            ->get(route('authority.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Border training area')
            ->assertSeeText('Greater Amman Municipality');
    }

    public function test_draft_application_disables_and_blocks_applicant_annex_updates(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload());

        $application = Application::query()->firstOrFail();

        $showResponse = $this->actingAs($applicant)->get(route('applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSee('data-annex-add-button', false);

        $this->assertMatchesRegularExpression('/<button[^>]*data-annex-add-button[^>]*disabled/s', $showResponse->getContent());

        $this->actingAs($applicant)->post(route('applications.annex.update', $application), [
            'work_content_summary_synopsis' => 'Draft annex update should not be accepted.',
            'work_content_summary_confirmed' => '1',
        ])->assertForbidden();

        $this->assertNotSame(
            'Draft annex update should not be accepted.',
            data_get($application->fresh()->metadata, 'annex.work_content_summary.synopsis')
        );
    }

    public function test_applicant_can_update_structured_annex_forms_after_submission(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload());

        $application = Application::query()->firstOrFail();

        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $showResponse = $this->actingAs($applicant)->get(route('applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSee('data-annex-add-button', false);

        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-annex-add-button[^>]*disabled/s', $showResponse->getContent());

        $response = $this->actingAs($applicant)->post(route('applications.annex.update', $application), [
            'work_content_summary_synopsis' => 'Updated annex synopsis after submission.',
            'work_content_summary_confirmed' => '1',
	            'cast_crew' => [
	                ['name' => 'New Annex Actor', 'role' => 'Lead', 'nationality' => 'jordanian', 'gender' => 'female', 'birth_date' => '1995-08-20', 'identity_number' => 'J-900'],
	            ],
            'safety_guidelines_acknowledged' => '1',
            'airport_filming_airport_name' => 'Queen Alia International Airport',
            'airport_people' => [
                ['full_name' => 'Airport Access Lead', 'nationality' => 'jordanian', 'mother_name' => 'Mariam', 'identity_number' => '9876543210', 'profession' => 'Producer', 'address_phone' => 'Amman 0790000000', 'entry_reason' => 'Filming', 'target_area' => 'Departures hall'],
            ],
        ]);

        $response->assertRedirect(route('applications.show', $application));

        $application->refresh();

	        $this->assertSame('Updated annex synopsis after submission.', data_get($application->metadata, 'annex.work_content_summary.synopsis'));
	        $this->assertTrue(data_get($application->metadata, 'annex.work_content_summary.confirmed'));
	        $this->assertSame('New Annex Actor', data_get($application->metadata, 'annex.cast_crew.0.name'));
	        $this->assertSame('female', data_get($application->metadata, 'annex.cast_crew.0.gender'));
	        $this->assertSame('1995-08-20', data_get($application->metadata, 'annex.cast_crew.0.birth_date'));
	        $this->assertSame('Queen Alia International Airport', data_get($application->metadata, 'annex.airport_filming.airport_name'));
        $this->assertSame('Airport Access Lead', data_get($application->metadata, 'annex.airport_people.0.full_name'));

        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'status' => 'submitted',
            'note' => 'Attached annex forms were updated by the applicant.',
        ]);
        $this->assertNotNull(data_get($application->metadata, 'applicant_annex_submission.submitted_at'));
        $this->assertSame($applicant->getKey(), data_get($application->metadata, 'applicant_annex_submission.submitted_by_user_id'));

        $this->actingAs($applicant)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('applicant-annex-table', false)
            ->assertSee('WorkContentSummary', false)
            ->assertSeeText('Annex 1')
            ->assertSeeText('Updated annex synopsis after submission.')
            ->assertSeeText('New Annex Actor')
            ->assertSeeText('Airport Access Lead');

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('href="#profile-Annex"', false)
            ->assertSeeText('Attached Annexes:')
            ->assertSeeText('Updated annex synopsis after submission.')
            ->assertSeeText('New Annex Actor');
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
            'decision' => 'returned',
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
        $rfcOwner = User::query()->where('email', 'ref@ref.test')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($user)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($user)->post(route('applications.submit', $application));

        $assignResponse = $this->actingAs($admin)->post(route('admin.applications.assign', $application), [
            'assigned_to_user_id' => $rfcOwner->getKey(),
        ]);

        $assignResponse->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'assigned_to_user_id' => $rfcOwner->getKey(),
            'current_stage' => 'intake',
            'status' => 'submitted',
        ]);

        $this->routeApplicationToAuthorities($admin, $application);

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

    public function test_admin_cannot_assign_non_workflow_user_to_application(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$user] = $this->createApplicantContext();
        $adminEntity = Entity::query()->where('code', 'platform-administration')->firstOrFail();

        $reporter = User::query()->create([
            'name' => 'Workflow Reporter',
            'username' => 'workflow-reporter',
            'email' => 'workflow-reporter@example.com',
            'phone' => '0795555111',
            'status' => 'active',
            'password' => Hash::make('Reporter@123'),
        ]);

        $reporter->entities()->attach($adminEntity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($adminEntity->getKey());
        $reporter->assignRole('reporter');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->actingAs($user)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($user)->post(route('applications.submit', $application));

        $response = $this->actingAs($admin)->post(route('admin.applications.assign', $application), [
            'assigned_to_user_id' => $reporter->getKey(),
        ]);

        $response
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'assigned_to_user_id' => null,
            'current_stage' => 'intake',
            'status' => 'submitted',
        ]);
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

        $this->actingAs($user)->post(route('applications.documents.store', $application), [
            'document_type' => 'airport_filming',
            'title' => 'Airport annex',
            'note' => 'Airport access details.',
            'file' => UploadedFile::fake()->create('airport-annex.pdf', 80, 'application/pdf'),
        ])->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->getKey(),
            'document_type' => 'airport_filming',
            'title' => 'Airport annex',
            'status' => 'submitted',
        ]);

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertDontSeeText('Uploaded files');

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
            ->assertSee('admin-documents-table', false)
            ->assertSeeText('Latest correspondence')
            ->assertSeeText('Official RFC note')
            ->assertSeeText('Please upload the revised signed form before we continue.');
    }

    public function test_admin_can_create_and_update_official_book_for_application(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();
        $publicSecurityEntity = Entity::query()->where('code', 'public-security-directorate')->firstOrFail();
        $customsEntity = Entity::query()->where('code', 'jordan-customs')->firstOrFail();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security', 'environment'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $application->refresh();
        $publicSecurityApproval = $application->authorityApprovals()
            ->where('authority_code', 'public_security')
            ->firstOrFail();
        $environmentApproval = $application->authorityApprovals()
            ->where('authority_code', 'environment')
            ->firstOrFail();

        $this->assertDatabaseHas('application_official_letters', [
            'application_id' => $application->getKey(),
            'application_authority_approval_id' => null,
            'target_entity_id' => $publicSecurityEntity->getKey(),
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Facilitation book issued by')
            ->assertSeeText($admin->displayName());
        $this->assertDatabaseHas('application_official_letters', [
            'application_id' => $application->getKey(),
            'application_authority_approval_id' => null,
            'target_entity_id' => $environmentApproval->entity_id,
            'status' => 'draft',
        ]);
        $this->assertSame(2, $application->officialLetters()->count());

        $letter = $application->officialLetters()
            ->where('target_entity_id', $publicSecurityEntity->getKey())
            ->firstOrFail();

        $updateResponse = $this->actingAs($admin)->put(route('admin.applications.official-letters.update', [$application, $letter]), [
            'application_authority_approval_id' => $environmentApproval->getKey(),
            'target_entity_id' => $customsEntity->getKey(),
            'letter_date' => '2026-05-11',
            'recipient_prefix' => 'H.E.',
            'recipient_name' => 'Director of Military Media',
            'subject' => 'Updated facilitation book',
            'body' => 'Please approve and facilitate the filming team mission.',
            'attachments' => ['Filming locations list', 'Cast and crew list'],
        ]);

        $updateResponse->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('application_official_letters', [
            'id' => $letter->getKey(),
            'application_authority_approval_id' => null,
            'target_entity_id' => $publicSecurityEntity->getKey(),
            'serial_number' => $application->code.'-BOOK-01',
            'subject' => 'Updated facilitation book',
            'status' => 'draft',
        ]);
        $this->assertNull($letter->fresh()->issued_at);
        $this->assertFalse($applicant->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'official_letter_issued'
        ));
        $this->assertFalse($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'official_letter_issued'
        ));

        $sendResponse = $this->actingAs($admin)->post(route('admin.applications.official-letters.send', [$application, $letter]));

        $sendResponse->assertRedirect(route('admin.applications.show', $application));

        $letter->refresh();

        $this->assertSame('issued', $letter->status);
        $this->assertNotNull($letter->issued_at);
        $this->assertSame($publicSecurityApproval->getKey(), $letter->application_authority_approval_id);
        $this->assertTrue($applicant->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'official_letter_issued'
                && data_get($notification->data, 'notification_highlight_summary') === 'Official book: Updated facilitation book'
        ));
        $this->assertTrue($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'official_letter_issued'
                && (int) data_get($notification->data, 'authority_approval_id') === $publicSecurityApproval->getKey()
                && data_get($notification->data, 'notification_highlight_summary') === 'Official book: Updated facilitation book'
        ));

        $adminShow = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $adminShow
            ->assertOk()
            ->assertSeeText('Issue Official Books')
            ->assertSee('admin-official-letters-table', false)
            ->assertSeeText($application->code.'-BOOK-01')
            ->assertSeeText('Updated facilitation book')
            ->assertSee('Print book', false)
            ->assertDontSee('officialLetterEdit'.$letter->getKey(), false)
            ->assertSeeText('Automatic recipients')
            ->assertDontSee('name="serial_number"', false)
            ->assertDontSee('name="target_entity_id"', false)
            ->assertDontSee('name="application_authority_approval_id"', false);

        $authorityInbox = $this->actingAs($authorityUser)->get(route('authority.applications.index'));

        $authorityInbox
            ->assertOk()
            ->assertSee('authority-requests-table', false)
            ->assertSeeText('Official books issued')
            ->assertSeeText('Official book issued')
            ->assertSeeText('Official book: Updated facilitation book');

        $authorityDashboard = $this->actingAs($authorityUser)->get(route('dashboard'));

        $authorityDashboard
            ->assertOk()
            ->assertSee('authority-requests-table', false)
            ->assertSeeText('Official books issued')
            ->assertSeeText('Official book issued');

        $applicantShow = $this->actingAs($applicant)->get(route('applications.show', $application));

        $applicantShow
            ->assertOk()
            ->assertSeeText('Official Books')
            ->assertSeeText($application->code.'-BOOK-01')
            ->assertSeeText('Updated facilitation book')
            ->assertSeeText('View book')
            ->assertSeeText('Please approve and facilitate the filming team mission.')
            ->assertSee('applicantOfficialLetterView', false);
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
        $this->assertSame('Waiting for RFC decision', data_get($notification?->data, 'workflow_checkpoint_label'));
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
        $this->routeApplicationToAuthorities($admin, $application);

        $inboxResponse = $this->actingAs($authorityUser)->get(route('authority.applications.index'));

        $inboxResponse
            ->assertOk()
            ->assertSeeText('Authority Inbox')
            ->assertSeeText('Authority action required')
            ->assertSeeText('Awaiting your decision')
            ->assertSee('streamit-wraper-table', false)
            ->assertSee('authority-requests-table-scroll', false)
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

        $currentApproval = ApplicationAuthorityApproval::query()
            ->where('application_id', $application->getKey())
            ->where('authority_code', 'public_security')
            ->firstOrFail();
        $otherEntity = Entity::query()->where('code', 'jordan-customs')->firstOrFail();

        $application->officialLetters()->create([
            'application_authority_approval_id' => $currentApproval->getKey(),
            'target_entity_id' => $authorityEntity->getKey(),
            'created_by_user_id' => $admin->getKey(),
            'updated_by_user_id' => $admin->getKey(),
            'letter_date' => '2026-05-12',
            'serial_number' => 'AUTH-BOOK-100',
            'recipient_prefix' => 'H.E.',
            'recipient_name' => 'Public Security Director',
            'subject' => 'Authority coordination book',
            'body' => 'Please review the automatically routed filming request.',
            'attachments' => ['Filming locations list'],
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        $application->officialLetters()->create([
            'target_entity_id' => $authorityEntity->getKey(),
            'created_by_user_id' => $admin->getKey(),
            'updated_by_user_id' => $admin->getKey(),
            'letter_date' => '2026-05-12',
            'serial_number' => 'AUTH-DRAFT-100',
            'recipient_name' => 'Public Security Director',
            'subject' => 'Draft authority coordination book',
            'body' => 'This draft should stay internal.',
            'attachments' => [],
            'status' => 'draft',
        ]);
        $application->officialLetters()->create([
            'target_entity_id' => $otherEntity->getKey(),
            'created_by_user_id' => $admin->getKey(),
            'updated_by_user_id' => $admin->getKey(),
            'letter_date' => '2026-05-12',
            'serial_number' => 'CUSTOMS-BOOK-100',
            'recipient_name' => 'Jordan Customs',
            'subject' => 'Other authority coordination book',
            'body' => 'This letter belongs to a different authority.',
            'attachments' => [],
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $showResponse = $this->actingAs($authorityUser)->get(route('authority.applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Authority Decision')
            ->assertSeeText('Procedures')
            ->assertSeeText('Request Timeline')
            ->assertSeeText('RFC')
            ->assertSeeText('Request accepted')
            ->assertDontSeeText('The applicant sent a new correspondence message.')
            ->assertSeeText('Approvals and Official Updates')
            ->assertSeeText('Approval type')
            ->assertSeeText('Decision note')
            ->assertSee('authority-detail-table', false)
            ->assertDontSee('official-letters-table', false)
            ->assertSeeText('Attached Forms')
            ->assertSeeText('Filming locations list')
            ->assertSeeText('Special requirements for the added locations')
            ->assertSeeText('Road closures')
            ->assertSeeText('Temporary road closure near the filming site.')
            ->assertSeeText('Start or continue review')
            ->assertSeeText('Approve request')
            ->assertSeeText('Reject request')
            ->assertSeeText('Entity book')
            ->assertSeeText('Required when approving')
            ->assertDontSee('value="pending"', false)
            ->assertDontSeeText('Official Books')
            ->assertDontSeeText('AUTH-BOOK-100')
            ->assertDontSeeText('Authority coordination book')
            ->assertDontSeeText('View book')
            ->assertDontSeeText('Thank you for your cooperation.')
            ->assertDontSeeText('Please accept our highest regards.')
            ->assertSeeText('New correspondence')
            ->assertSeeText('Send new correspondence')
            ->assertSeeText('Message content')
            ->assertSeeText('Sender')
            ->assertSeeText('Sent at')
            ->assertDontSeeText('Draft authority coordination book')
            ->assertDontSeeText('Other authority coordination book')
            ->assertSeeText('Request update received')
            ->assertSeeText('New correspondence: Applicant Reply')
            ->assertSee('streamit-wraper-table', false)
            ->assertSee('authority-request-table-scroll', false)
            ->assertSee('authority-detail-table', false)
            ->assertDontSee('authority-documents-table', false)
            ->assertSeeText($authorityEntity->displayName('en'));

        $updateResponse = $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
            'status' => 'approved',
            'note' => 'Security approval granted.',
            'response_attachment' => UploadedFile::fake()->create('security-approval-book.pdf', 80, 'application/pdf'),
        ]);

        $updateResponse->assertRedirect(route('authority.applications.show', $application));

        $currentApproval->refresh();

        $this->assertDatabaseHas('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'public_security',
            'status' => 'approved',
            'note' => 'Security approval granted.',
            'reviewed_by_user_id' => $authorityUser->getKey(),
            'response_attachment_name' => 'security-approval-book.pdf',
        ]);
        Storage::disk('local')->assertExists($currentApproval->response_attachment_path);
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

        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'user_id' => $authorityUser->getKey(),
        ]);
        $this->assertSame(
            'authority_status_updated',
            data_get($application->fresh()->statusHistory()->latest('id')->first()?->metadata, 'type')
        );

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

    public function test_authority_page_shows_form_sections_that_drove_routing(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        [$customsUser, $customsEntity] = $this->createAuthorityContext([
            'name' => 'Customs Reviewer',
            'username' => 'customs-reviewer',
            'email' => 'customs-reviewer@example.com',
            'phone' => '0794444222',
        ], 'jordan-customs');

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_name' => 'Equipment Route Project',
            'required_approvals' => ['customs'],
            'equipment_flights' => [[
                'flight_type' => 'Arrival',
                'flight_number' => 'RJ100',
                'flight_date' => '2026-05-01',
                'flight_time' => '10:30',
                'departure_city' => 'Paris',
                'arrival_city' => 'Amman',
            ]],
            'equipment_travelers' => [[
                'traveler_name' => 'Traveler One',
                'arrival_date' => '2026-05-01',
                'arrival_flight_number' => 'RJ100',
                'departure_date' => '2026-05-10',
                'departure_flight_number' => 'RJ101',
            ]],
            'imported_equipment' => [[
                'transport_group' => 'traveler',
                'item' => 'Camera Package',
                'serial_number' => 'CAM-001',
                'flight_reference' => 'RJ100',
                'traveler_name' => 'Traveler One',
                'quantity' => 1,
                'unit_value' => 15000,
                'total_value' => 15000,
                'classification' => 'Camera',
                'shipping_method' => 'With traveler',
                'entry_point' => 'Queen Alia Airport',
            ]],
        ]));

        $application = Application::query()->latest('id')->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $this->assertDatabaseHas('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'customs',
            'entity_id' => $customsEntity->getKey(),
        ]);

        $this->actingAs($customsUser)
            ->get(route('authority.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Attached Forms')
            ->assertSeeText('View form')
            ->assertSee('data-bs-target="#EquipmentListView"', false)
            ->assertSeeText('Flight details (arrival and departure)')
            ->assertSeeText('Travelers list')
            ->assertSeeText('Equipment to be brought from abroad')
            ->assertSeeText('Camera Package')
            ->assertSeeText('Traveler One')
            ->assertSee('data-authority-annex-sections="equipment_flights,equipment_travelers,imported_equipment"', false)
            ->assertDontSee('authority-documents-table', false);
    }

    public function test_authority_approval_requires_response_book_and_rfc_can_download_it(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $approval = ApplicationAuthorityApproval::query()
            ->where('application_id', $application->getKey())
            ->where('authority_code', 'public_security')
            ->firstOrFail();

        $missingBookResponse = $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
            'status' => 'approved',
            'note' => 'Approved without book should fail.',
        ]);

        $missingBookResponse
            ->assertRedirect()
            ->assertSessionHasErrors('response_attachment');

        $this->assertDatabaseHas('application_authority_approvals', [
            'id' => $approval->getKey(),
            'status' => 'pending',
            'response_attachment_path' => null,
        ]);

        $approveResponse = $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
            'status' => 'approved',
            'note' => 'Security approval book attached.',
            'response_attachment' => UploadedFile::fake()->create('public-security-book.pdf', 120, 'application/pdf'),
        ]);

        $approveResponse->assertRedirect(route('authority.applications.show', $application));

        $approval->refresh();

        $this->assertSame('approved', $approval->status);
        $this->assertSame('public-security-book.pdf', $approval->response_attachment_name);
        $this->assertNotNull($approval->response_attachment_uploaded_at);
        Storage::disk('local')->assertExists($approval->response_attachment_path);

        $this->actingAs($authorityUser)
            ->get(route('authority.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Download entity book')
            ->assertSeeText('public-security-book.pdf');

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Entity book')
            ->assertSeeText('Download entity book')
            ->assertSeeText('public-security-book.pdf');

        $this->actingAs($admin)
            ->get(route('admin.applications.approvals.attachment.download', [$application, $approval]))
            ->assertOk();

        $this->actingAs($authorityUser)
            ->get(route('authority.applications.approvals.attachment.download', [$application, $approval]))
            ->assertOk();
    }

    public function test_authority_reviewer_cannot_resolve_approval_without_approver_permission(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        $authorityEntity = Entity::query()->where('code', 'public-security-directorate')->firstOrFail();

        $reviewer = User::query()->create([
            'name' => 'Authority Reviewer Only',
            'username' => 'authority-reviewer-only',
            'email' => 'authority-reviewer-only@example.com',
            'phone' => '0794444222',
            'status' => 'active',
            'password' => Hash::make('Authority@123'),
        ]);

        $reviewer->entities()->attach($authorityEntity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($authorityEntity->getKey());
        $reviewer->assignRole('authority_reviewer');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $showResponse = $this->actingAs($reviewer)->get(route('authority.applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Start or continue review')
            ->assertSeeText('Final approval or rejection is available only to authority approvers.')
            ->assertDontSee('value="pending"', false)
            ->assertDontSee('value="approved"', false)
            ->assertDontSee('value="rejected"', false)
            ->assertDontSeeText('Approve request')
            ->assertDontSeeText('Reject request');

        $updateResponse = $this->actingAs($reviewer)->post(route('authority.applications.approval.update', $application), [
            'status' => 'approved',
            'note' => 'Trying to approve without approver access.',
        ]);

        $updateResponse->assertForbidden();

        $this->assertDatabaseHas('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'public_security',
            'status' => 'pending',
            'reviewed_by_user_id' => null,
        ]);
    }

    public function test_authority_default_delegate_receives_private_inbox_assignment(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        $authorityEntity = Entity::query()->where('code', 'public-security-directorate')->firstOrFail();

        [$delegate] = $this->createAuthorityContext([
            'name' => 'Assigned Authority Approver',
            'username' => 'assigned-authority-approver',
            'email' => 'assigned-authority-approver@example.com',
        ]);

        $peer = User::query()->create([
            'name' => 'Peer Authority Approver',
            'username' => 'peer-authority-approver',
            'email' => 'peer-authority-approver@example.com',
            'phone' => '0794111222',
            'status' => 'active',
            'password' => Hash::make('Authority@123'),
        ]);

        $peer->entities()->attach($authorityEntity->getKey(), [
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($authorityEntity->getKey());
        $peer->assignRole('authority_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $metadata = $authorityEntity->metadata ?? [];
        data_set($metadata, 'authority_delegation.approval_user_map.public_security', $delegate->getKey());
        $authorityEntity->forceFill([
            'metadata' => $metadata,
        ])->save();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_name' => 'Delegated Security Request',
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->latest('id')->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $approval = ApplicationAuthorityApproval::query()
            ->where('application_id', $application->getKey())
            ->where('authority_code', 'public_security')
            ->firstOrFail();

        $this->assertSame($delegate->getKey(), $approval->assigned_user_id);
        $this->assertNotNull($approval->assigned_at);

        $delegateInbox = $this->actingAs($delegate)->get(route('authority.applications.index'));
        $delegateInbox
            ->assertOk()
            ->assertSeeText('Delegated Security Request');

        $peerInbox = $this->actingAs($peer)->get(route('authority.applications.index'));
        $peerInbox
            ->assertOk()
            ->assertDontSeeText('Delegated Security Request');

        $peerShow = $this->actingAs($peer)->get(route('authority.applications.show', $application));
        $peerShow->assertNotFound();

        $this->assertTrue($delegate->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_requested'
                && data_get($notification->data, 'title') === 'Delegated Security Request'
        ));
        $this->assertFalse($peer->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_requested'
                && data_get($notification->data, 'title') === 'Delegated Security Request'
        ));
    }

    public function test_admin_can_manually_reassign_live_authority_approval_from_application_page(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        [$originalAuthorityUser, $authorityEntity] = $this->createAuthorityContext([
            'name' => 'Original Authority Handler',
            'username' => 'original-authority-handler',
            'email' => 'original-authority-handler@example.com',
        ]);

        $replacementAuthorityUser = User::query()->create([
            'name' => 'Replacement Authority Handler',
            'username' => 'replacement-authority-handler',
            'email' => 'replacement-authority-handler@example.com',
            'phone' => '0794111333',
            'status' => 'active',
            'password' => Hash::make('Authority@123'),
        ]);

        $replacementAuthorityUser->entities()->attach($authorityEntity->getKey(), [
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($authorityEntity->getKey());
        $replacementAuthorityUser->assignRole('authority_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_name' => 'Manual Authority Reassignment',
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->latest('id')->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $approval = ApplicationAuthorityApproval::query()
            ->where('application_id', $application->getKey())
            ->where('authority_code', 'public_security')
            ->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.applications.approvals.assign', [$application, $approval]), [
            'assigned_user_id' => $replacementAuthorityUser->getKey(),
            'assignment_note' => 'Shift coverage for today',
        ]);

        $response->assertRedirect(route('admin.applications.show', $application));

        $this->assertDatabaseHas('application_authority_approvals', [
            'id' => $approval->getKey(),
            'assigned_user_id' => $replacementAuthorityUser->getKey(),
        ]);

        $originalShow = $this->actingAs($originalAuthorityUser)->get(route('authority.applications.show', $application));
        $originalShow->assertNotFound();

        $replacementInbox = $this->actingAs($replacementAuthorityUser)->get(route('authority.applications.index'));
        $replacementInbox
            ->assertOk()
            ->assertSeeText('Manual Authority Reassignment');

        $showResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));
        $showResponse
            ->assertOk()
            ->assertSeeText('Replacement Authority Handler')
            ->assertSeeText('Reassigned')
            ->assertSeeText('Shift coverage for today');
    }

    public function test_authority_dashboard_and_inbox_highlight_my_assignments_and_shared_inbox(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $authorityEntity = Entity::query()->where('code', 'public-security-directorate')->firstOrFail();
        [$delegate] = $this->createAuthorityContext([
            'name' => 'Dashboard Delegate',
            'username' => 'dashboard-delegate',
            'email' => 'dashboard-delegate@example.com',
        ]);

        $peer = User::query()->create([
            'name' => 'Dashboard Peer',
            'username' => 'dashboard-peer',
            'email' => 'dashboard-peer@example.com',
            'phone' => '0794111444',
            'status' => 'active',
            'password' => Hash::make('Authority@123'),
        ]);

        $peer->entities()->attach($authorityEntity->getKey(), [
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($authorityEntity->getKey());
        $peer->assignRole('authority_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        [$applicant, $applicantEntity] = $this->createApplicantContext([
            'name' => 'Authority Metrics Applicant',
            'username' => 'authority-metrics-applicant',
            'email' => 'authority-metrics-applicant@example.com',
        ], [
            'name_en' => 'Authority Metrics Studio',
            'name_ar' => 'Authority Metrics Studio',
            'registration_no' => 'ORG-188',
        ]);

        $sharedApplication = Application::query()->create([
            'code' => 'REQ-SHARED-1',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Shared Authority Request',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-03',
            'project_summary' => 'Shared authority workload.',
            'status' => 'under_review',
            'current_stage' => 'authority_review',
            'submitted_at' => now()->subDay(),
        ]);

        $privateApplication = Application::query()->create([
            'code' => 'REQ-PRIVATE-1',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Private Authority Request',
            'project_nationality' => 'jordanian',
            'work_category' => 'documentary',
            'release_method' => 'festival',
            'planned_start_date' => '2026-08-05',
            'planned_end_date' => '2026-08-07',
            'project_summary' => 'Private delegated authority workload.',
            'status' => 'under_review',
            'current_stage' => 'authority_review',
            'submitted_at' => now()->subHours(18),
        ]);

        ApplicationAuthorityApproval::query()->create([
            'application_id' => $sharedApplication->getKey(),
            'authority_code' => 'public_security',
            'entity_id' => $authorityEntity->getKey(),
            'status' => 'pending',
        ]);

        ApplicationAuthorityApproval::query()->create([
            'application_id' => $privateApplication->getKey(),
            'authority_code' => 'public_security',
            'entity_id' => $authorityEntity->getKey(),
            'assigned_user_id' => $delegate->getKey(),
            'assigned_at' => now()->subHours(12),
            'status' => 'in_review',
        ]);

        $delegateDashboard = $this->actingAs($delegate)->get(route('dashboard'));
        $delegateDashboard
            ->assertOk()
            ->assertSee('authority-requests-table', false)
            ->assertSeeText('My assigned approvals')
            ->assertSeeText('Shared inbox approvals')
            ->assertSeeText('Private Authority Request')
            ->assertSeeText('Shared Authority Request')
            ->assertSeeText('My assignment')
            ->assertSeeText('Shared inbox');

        $peerDashboard = $this->actingAs($peer)->get(route('dashboard'));
        $peerDashboard
            ->assertOk()
            ->assertSeeText('Shared Authority Request')
            ->assertDontSeeText('Private Authority Request');

        $delegateSharedFilter = $this->actingAs($delegate)->get(route('authority.applications.index', [
            'ownership' => 'shared',
        ]));
        $delegateSharedFilter
            ->assertOk()
            ->assertSee('authority-requests-table', false)
            ->assertSeeText('Shared Authority Request')
            ->assertDontSeeText('Private Authority Request');
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
        [$authorityUser, $authorityEntity] = $this->createAuthorityContext([
            'name' => 'Directory Authority Owner',
            'username' => 'directory-authority-owner',
            'email' => 'directory-authority-owner@example.com',
        ]);

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
            'entity_id' => $authorityEntity->getKey(),
            'assigned_user_id' => $authorityUser->getKey(),
            'assigned_at' => now(),
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
            ->assertSeeText('Waiting for RFC decision')
            ->assertSeeText('Waiting on applicant')
            ->assertSeeText('Ready for final decision')
            ->assertSeeText('Request number')
            ->assertSeeText('Submitted date')
            ->assertDontSeeText('Responsibility')
            ->assertDontSeeText('RFC owner')
            ->assertDontSeeText('Authority owners')
            ->assertSeeText('Assign Reviewer Project')
            ->assertSeeText('Applicant Clarification Project')
            ->assertSeeText('Final Decision Project')
            ->assertDontSeeText('Directory Authority Owner');

        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));

        $dashboardResponse
            ->assertOk()
            ->assertSeeText('Operational Production KPIs')
            ->assertDontSeeText('Workflow Queue');
    }

    public function test_overdue_authority_approval_is_flagged_and_escalated_from_configured_response_time(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $rfcEntity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();
        [$user, $entity] = $this->createApplicantContext();
        [$authorityUser, $authorityEntity] = $this->createAuthorityContext([
            'name' => 'Escalation Authority Owner',
            'username' => 'escalation-authority-owner',
            'email' => 'escalation-authority-owner@example.com',
        ]);

        $rfcAdmin = User::query()->create([
            'name' => 'RFC Escalation Admin',
            'username' => 'rfc_escalation_admin',
            'email' => 'rfc-escalation-admin@example.com',
            'phone' => '0793111222',
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

        $authorityEntity->forceFill([
            'metadata' => [
                ...($authorityEntity->metadata ?? []),
                'authority_sla' => [
                    'response_time_days' => 2,
                    'escalation_user_ids' => [$admin->getKey()],
                    'escalation_role_names' => ['rfc_admin'],
                ],
            ],
        ])->save();

        $application = Application::query()->create([
            'code' => 'REQ-50004',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Overdue Authority Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-10-01',
            'planned_end_date' => '2026-10-12',
            'project_summary' => 'Waiting too long on authority.',
            'status' => 'submitted',
            'submitted_at' => now()->subDays(3),
        ]);

        $approval = $application->authorityApprovals()->create([
            'authority_code' => 'public_security',
            'entity_id' => $authorityEntity->getKey(),
            'assigned_user_id' => $authorityUser->getKey(),
            'assigned_at' => now()->subDays(3),
            'status' => 'pending',
        ]);

        $approval->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->saveQuietly();

        $indexResponse = $this->actingAs($admin)->get(route('admin.applications.index'));

        $indexResponse
            ->assertOk()
            ->assertSeeText('Overdue Authority Project')
            ->assertSeeText('Overdue by');

        Artisan::call('authority-approvals:check-escalations');

        $approval->refresh();

        $this->assertNotNull($approval->escalated_at);
        $this->assertTrue($admin->fresh()->notifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_escalated'
        ));
        $this->assertTrue($rfcAdmin->fresh()->notifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_escalated'
        ));

        $afterResponse = $this->actingAs($admin)->get(route('admin.applications.index'));

        $afterResponse
            ->assertOk()
            ->assertSeeText('Escalated');

        $showResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Response window')
            ->assertSeeText('Escalated');

        $authorityInboxResponse = $this->actingAs($authorityUser)->get(route('authority.applications.index'));

        $authorityInboxResponse
            ->assertOk()
            ->assertSee('authority-requests-table', false)
            ->assertSeeText('Overdue approvals')
            ->assertSeeText('Escalated approvals')
            ->assertSeeText('Escalated')
            ->assertSeeText('Approval type')
            ->assertSee('data-sla-countdown', false);

        $authorityDashboardResponse = $this->actingAs($authorityUser)->get(route('dashboard'));

        $authorityDashboardResponse
            ->assertOk()
            ->assertSee('authority-requests-table', false)
            ->assertSeeText('Overdue approvals')
            ->assertSeeText('Escalated approvals')
            ->assertSeeText('Escalated')
            ->assertSeeText('Approval type')
            ->assertSee('data-sla-countdown', false);

        $authorityShowResponse = $this->actingAs($authorityUser)->get(route('authority.applications.show', $application));

        $authorityShowResponse
            ->assertOk()
            ->assertSeeText('Escalated')
            ->assertSeeText('Due at:')
            ->assertSee('data-sla-countdown', false);
        $this->assertMatchesRegularExpression('/Due at: .* (AM|PM)/', $authorityShowResponse->getContent());
    }

    public function test_authority_pages_show_configured_response_time_for_legacy_code_based_approval(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$applicant, $applicantEntity] = $this->createApplicantContext();
        [$authorityUser, $authorityEntity] = $this->createAuthorityContext([
            'name' => 'Legacy SLA Authority Owner',
            'username' => 'legacy-sla-authority-owner',
            'email' => 'legacy-sla-authority-owner@example.com',
        ]);

        $authorityEntity->forceFill([
            'metadata' => [
                ...($authorityEntity->metadata ?? []),
                'authority_sla' => [
                    'response_time_days' => 2,
                    'escalation_user_ids' => [],
                    'escalation_role_names' => [],
                ],
            ],
        ])->save();

        $application = Application::query()->create([
            'code' => 'REQ-LEGACY-SLA',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Legacy SLA Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-10-01',
            'planned_end_date' => '2026-10-12',
            'project_summary' => 'Legacy approval row without an entity id.',
            'status' => 'submitted',
            'current_stage' => 'authority_approvals',
            'submitted_at' => now()->subDay(),
        ]);

        $approval = $application->authorityApprovals()->create([
            'authority_code' => 'public_security',
            'entity_id' => null,
            'status' => 'pending',
        ]);

        $approval->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        $showResponse = $this->actingAs($authorityUser)->get(route('authority.applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Due in')
            ->assertSeeText('Due at:')
            ->assertDontSeeText('No window');

        $indexResponse = $this->actingAs($authorityUser)->get(route('authority.applications.index'));

        $indexResponse
            ->assertOk()
            ->assertSeeText('Due in')
            ->assertSeeText('Due at:');

        $dashboardResponse = $this->actingAs($authorityUser)->get(route('dashboard'));

        $dashboardResponse
            ->assertOk()
            ->assertSeeText('Due in')
            ->assertSeeText('Due at:');
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
            ->assertSeeText('Project Information')
            ->assertSeeText('Studio One')
            ->assertSeeText('Amman')
            ->assertSeeText('065555555')
            ->assertSeeText('producer@example.com')
            ->assertSeeText('Liaison Person')
            ->assertSeeText('Open profile')
            ->assertDontSeeText('Producer Information')
            ->assertSeeText('Implementation schedule')
            ->assertSeeText('Pre-production')
            ->assertSeeText('2026-04-20 - 2026-04-30')
            ->assertSeeText('Filming')
            ->assertSeeText('2026-05-01 - 2026-05-10')
            ->assertSeeText('Wrap')
            ->assertSeeText('2026-05-11 - 2026-05-12')
            ->assertSeeText('Post-production')
            ->assertSeeText('2026-05-13 - 2026-06-01')
            ->assertSeeText('Authority progress')
            ->assertSeeText('RFC has not routed this request to external authorities yet.')
            ->assertSeeText('Final decision readiness')
            ->assertSeeText('Submit the request to start the official review workflow.')
            ->assertSeeText('120,000.00')
            ->assertSeeText('45,000.00');
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
        $this->routeApplicationToAuthorities($admin, $application);

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
            ->assertSeeText('RFC')
            ->assertSeeText('Public Security Directorate')
            ->assertSeeText('Security approval granted.')
            ->assertDontSeeText('Draft created by the applicant.')
            ->assertDontSeeText('The request was submitted for RFC review.')
            ->assertSeeText('Authority progress')
            ->assertSeeText('1 of 2 authority reviews are resolved.')
            ->assertSeeText('There are 1 authority review responses still pending.')
            ->assertSeeText('Latest official step')
            ->assertSeeText('RFC correspondence: Review Update')
            ->assertSeeText('Final decision readiness')
            ->assertSeeText('There are 1 authority review responses still pending before the RFC can issue the final decision.')
            ->assertSee('applicant-request-table-scroll', false)
            ->assertSee('applicant-approval-table', false);

        $adminResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $adminResponse
            ->assertOk()
            ->assertSeeText('Request Timeline')
            ->assertSeeText('Attached Forms')
            ->assertSeeText('Work content summary')
            ->assertSeeText('Cast and crew list')
            ->assertSeeText('View form')
            ->assertSee('href="#profile-Annex"', false)
            ->assertSeeText('No annex has been submitted yet.')
            ->assertSeeText('RFC')
            ->assertSeeText('Public Security Directorate')
            ->assertSeeText('Security approval granted.')
            ->assertDontSeeText('Draft created by the applicant.')
            ->assertDontSeeText('The request was submitted for RFC review.');
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
            ->assertSee('card-dashboard', false)
            ->assertSee('portal-request-table', false)
            ->assertSeeText('Production requests')
            ->assertSeeText('Scouting requests')
            ->assertSeeText('Clarification Project')
            ->assertSeeText('Needs clarification')
            ->assertSeeText('Applicant Studio');

        $indexResponse = $this->actingAs($user)->get(route('applications.index'));

        $indexResponse
            ->assertOk()
            ->assertSee('applicant-request-table', false)
            ->assertSee('request-tabs', false)
            ->assertSeeText('Clarification Project');
    }

    public function test_request_lists_put_same_second_newer_records_first(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user, $entity] = $this->createApplicantContext();
        $sameTime = now()->subDay()->setMicroseconds(0);

        $olderApplication = Application::query()->create([
            'code' => 'REQ-ORDER-001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Older Same Second Application',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => $sameTime,
        ]);
        $olderApplication->forceFill(['created_at' => $sameTime, 'updated_at' => $sameTime])->save();

        $newerApplication = Application::query()->create([
            'code' => 'REQ-ORDER-002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Newer Same Second Application',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => $sameTime,
        ]);
        $newerApplication->forceFill(['created_at' => $sameTime, 'updated_at' => $sameTime])->save();

        $olderScouting = ScoutingRequest::query()->create([
            'code' => 'SCOUT-ORDER-001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Older Same Second Scout',
            'project_nationality' => 'jordanian',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => $sameTime,
        ]);
        $olderScouting->forceFill(['created_at' => $sameTime, 'updated_at' => $sameTime])->save();

        $newerScouting = ScoutingRequest::query()->create([
            'code' => 'SCOUT-ORDER-002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Newer Same Second Scout',
            'project_nationality' => 'jordanian',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => $sameTime,
        ]);
        $newerScouting->forceFill(['created_at' => $sameTime, 'updated_at' => $sameTime])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Newer Same Second Application',
                'Older Same Second Application',
            ])
            ->assertSeeInOrder([
                'Newer Same Second Scout',
                'Older Same Second Scout',
            ]);

        $this->actingAs($user)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Newer Same Second Application',
                'Older Same Second Application',
            ]);

        $this->actingAs($user)
            ->get(route('scouting-requests.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Newer Same Second Scout',
                'Older Same Second Scout',
            ]);
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
            ->assertSee('card-dashboard', false)
            ->assertSee('portal-request-table', false)
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
            ->assertSee('portal-profile-projects-table', false)
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
            'code' => 'REQ-30003',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Rejected Short',
            'project_nationality' => 'jordanian',
            'work_category' => 'commercial',
            'release_method' => 'digital',
            'planned_start_date' => '2026-06-01',
            'planned_end_date' => '2026-06-03',
            'estimated_crew_count' => 9,
            'estimated_budget' => 12000,
            'project_summary' => 'Rejected project summary',
            'status' => 'rejected',
            'current_stage' => 'rejected',
            'submitted_at' => now()->subDays(8),
            'reviewed_at' => now()->subDays(4),
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
        $response
            ->assertSee('portal-profile-projects-table', false)
            ->assertSee('<span class="badge bg-success">Approved</span>', false)
            ->assertSee('<span class="badge bg-danger">Rejected</span>', false);
        $previousProjects = collect($response->viewData('previousProjects'));
        $activeWorkflowRequests = collect($response->viewData('activeWorkflowRequests'));

        $this->assertTrue($previousProjects->contains(fn ($project) => $project->project_name === 'Released Feature'));
        $this->assertTrue($previousProjects->contains(fn ($project) => $project->project_name === 'Rejected Short'));
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
            ->assertSee('href="'.route('profile.show').'"', false)
            ->assertDontSee('href="'.route('profile.show', ['variant' => 'foreign_producer']).'"', false);
    }

    public function test_authority_dashboard_hides_export_button_and_contains_shared_profile_route(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $response = $this->actingAs($authorityUser)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('href="'.route('profile.show', ['variant' => 'foreign_producer']).'"', false)
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
            ->assertSee('foreign-producer-applications-table', false)
            ->assertSee('foreign-producer-scouting-table', false)
            ->assertSeeText('Foreign Profile Studio')
            ->assertSeeText('Foreign Producer')
            ->assertSeeText('Production Requests')
            ->assertSeeText('Applicant')
            ->assertSeeText('Foreign Feature')
            ->assertSeeText('Scouting requests')
            ->assertSeeText('Foreign Scout')
            ->assertSeeText('Declaration and Undertaking')
            ->assertSeeText('Open request')
            ->assertDontSeeText('Work category');
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
        $this->routeApplicationToAuthorities($admin, $application);

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
        $this->routeApplicationToAuthorities($admin, $application);

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
        $this->routeApplicationToAuthorities($admin, $application);

        $response = $this->actingAs($admin)->post(route('admin.applications.finalize', $application), [
            'decision' => 'approved',
            'permit_number' => 'RFC-PERMIT-2026-002',
            'note' => 'Attempting early issuance.',
        ]);

        $response
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHasErrors('decision');

        $showResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSee('admin-final-decision-table', false);

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
        $this->routeApplicationToAuthorities($admin, $application);

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
            ->assertSee('permit-audit-table', false)
            ->assertSeeText('RFC-PERMIT-2026-010');
    }

    public function test_authority_user_can_export_only_scoped_inbox_requests(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_name' => 'Scoped Authority Request',
            'required_approvals' => ['public_security'],
        ]));
        $firstApplication = Application::query()->latest('id')->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $firstApplication));
        $this->routeApplicationToAuthorities($admin, $firstApplication);

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_name' => 'Airport Only Request',
            'required_approvals' => ['airports'],
        ]));
        $secondApplication = Application::query()->latest('id')->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $secondApplication));
        $this->routeApplicationToAuthorities($admin, $secondApplication);

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
    private function routeApplicationToAuthorities(User $admin, Application $application): void
    {
        $this->actingAs($admin)->post(route('admin.applications.review', $application), [
            'decision' => 'accepted',
        ])->assertRedirect(route('admin.applications.show', $application));

        $this->actingAs($admin)->post(route('admin.applications.issue-facilitation-letter', $application))
            ->assertRedirect(route('admin.applications.show', $application));

        $approvals = app(ApplicationAuthorityApprovalSyncService::class)->sync($application);

        $application->forceFill([
            'status' => 'under_review',
            'current_stage' => $approvals->isNotEmpty() ? 'authority_review' : 'final_decision',
        ])->save();

        $notificationService = app(AuthorityApprovalNotificationService::class);
        $approvals->each(fn (ApplicationAuthorityApproval $approval): int => $notificationService
            ->notifyRecipientsForApproval($approval, $admin->getKey()));

        $application->refresh();
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
    private function createAuthorityContext(array $userOverrides = [], string $entityCode = 'public-security-directorate'): array
    {
        $entity = Entity::query()->where('code', $entityCode)->firstOrFail();

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
    private function wrapReportPayload(array $overrides = []): array
    {
        return array_merge([
            'project_name' => 'Desert Dreams',
            'production_company' => 'Studio One',
            'local_producer_services_company' => 'Local Producer',
            'production_types' => ['feature_film'],
            'production_type_other' => null,
            'nationalities' => 'Jordanian',
            'production_year' => 2026,
            'local_crew_count' => 30,
            'foreign_crew_count' => 8,
            'hotel_nights_count' => 120,
            'accommodation_types' => ['hotel'],
            'hotel_stars' => 4,
            'national_carrier_ticket_count' => 16,
            'rented_cars_count' => 3,
            'rental_days_count' => 4,
            'production_days_pre_production' => 6,
            'production_days_production' => 12,
            'production_days_post_production' => 8,
            'total_local_spending_jod' => 65000,
            'additional_notes' => 'Production wrapped without incidents.',
            'submitted_by' => 'Applicant Owner',
            'submitted_position' => 'Producer',
            'submitted_date' => '2026-05-13',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(array $overrides = []): array
    {
        $approvalCodes = array_values((array) ($overrides['required_approvals'] ?? ['public_security', 'environment']));

        ApprovalRoutingRule::query()
            ->where('request_type', 'application')
            ->update(['is_active' => false]);

        ApprovalRoutingRule::query()
            ->where('request_type', 'application')
            ->whereIn('approval_code', $approvalCodes)
            ->update(['is_active' => true]);

        $payload = array_merge([
            'project_name' => 'Desert Dreams',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-05-01',
            'planned_end_date' => '2026-05-10',
            'schedule_phases' => [
                'preparation' => ['start_date' => '2026-04-20', 'end_date' => '2026-04-30'],
                'wrap' => ['start_date' => '2026-05-11', 'end_date' => '2026-05-12'],
                'post_production' => ['start_date' => '2026-05-13', 'end_date' => '2026-06-01'],
            ],
            'estimated_crew_count' => 35,
            'estimated_budget' => 120000,
            'local_spend_estimate' => 45000,
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
            'director_nationality' => 'jordanian',
            'director_profile_url' => 'https://example.com/director',
            'international_producer_name' => 'Global Partner',
            'international_producer_nationality' => 'non_jordanian',
            'international_producer_company' => 'Global Films',
            'filming_locations' => [[
                'governorate' => 'maan',
                'location_name' => 'Wadi Rum Reserve',
                'address' => 'Wadi Rum',
                'nature' => 'Protected reserve landscape',
                'location_type' => 'reserves',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-10',
                'notes' => 'Reserve filming coordination required.',
            ]],
            'special_location_requirements' => [
                'road_closures' => [
                    'locations' => ['Wadi Rum Reserve'],
                    'notes' => 'Temporary road closure near the filming site.',
                ],
            ],
            'safety_guidelines_acknowledged' => '1',
            'supporting_notes' => 'Need desert location and crowd management support.',
        ], $overrides);

        unset($payload['required_approvals']);

        return $payload;
    }
}
