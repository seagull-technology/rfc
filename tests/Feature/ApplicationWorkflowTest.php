<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAnnexSubmission;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApplicationAuthorityChangeRequest;
use App\Models\ApplicationCorrespondence;
use App\Models\ApplicationDocument;
use App\Models\ApprovalRoutingRule;
use App\Models\Entity;
use App\Models\FilmingLocationType;
use App\Models\Group;
use App\Models\Permit;
use App\Models\ReleaseMethod;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Models\WorkCategory;
use App\Notifications\ForeignProducerInvitationNotification;
use App\Notifications\RegistrationApprovedNotification;
use App\Services\ApplicationAuthorityApprovalSyncService;
use App\Services\AuthorityApprovalNotificationService;
use App\Services\AuthorityEscalationService;
use App\Services\Gsb\CrewIdentityVerificationService;
use App\Services\Gsb\IndividualPersonalInfoLookupService;
use App\Support\MinistryInteriorPersonalDetails;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
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
            ->assertSee('data-page-validation-message=', false)
            ->assertSee('data-auto-required-marker', false)
            ->assertSee('id="step1"', false)
            ->assertSee('id="step2"', false)
            ->assertSee('class="btn btn-danger request-wizard-next action-button float-end btn-lg"', false)
            ->assertSee('value="egyptian"', false)
            ->assertSeeText('Egyptian')
            ->assertSeeText(__('app.applications.approval_route_preview_title'))
            ->assertSeeText(__('app.applications.shipping_customs_instruction_before_project'))
            ->assertSeeText(__('app.applications.shipping_customs_instruction_after_project'))
            ->assertSeeText(__('app.applications.shipping_customs_conclusion'))
            ->assertSee('data-shipping-customs-project-name', false)
            ->assertSee('name="shipping_equipment_acknowledged"', false)
            ->assertSeeText(__('app.applications.traveler_customs_instruction_before_project'))
            ->assertSeeText(__('app.applications.traveler_customs_instruction_after_project'))
            ->assertSee('data-traveler-customs-project-name', false)
            ->assertSee('data-equipment-traveler-select', false)
            ->assertSee('name="equipment_travelers[0][passport_image]"', false)
            ->assertSee('name="imported_equipment[traveler_0][traveler_name]"', false)
            ->assertSee('data-equipment-row-total readonly', false)
            ->assertDontSee('name="imported_equipment[traveler_0][shipping_method]"', false)
            ->assertSeeText('Equipment Handler')
            ->assertSee('name="airport_people[airport-person][nationality]"', false)
            ->assertSee('data-airport-person-nationality', false)
            ->assertSee('name="airport_people[airport-person][first_name]"', false)
            ->assertSee('data-airport-person-name-output', false)
            ->assertSee('data-import-cast-crew-to-airport', false)
            ->assertSeeText(__('app.applications.import_cast_crew_to_airport'))
            ->assertSee('importApplicationCastCrewToAirportPeople', false)
            ->assertSee('application-annex-offcanvas', false)
            ->assertDontSee('PublicSecuritySupport', false)
            ->assertDontSee('MilitarySupport', false)
            ->assertDontSeeText(__('app.applications.annex_sections.public_security_support'))
            ->assertDontSeeText(__('app.applications.annex_sections.military_support'))
            ->assertSeeText(__('app.applications.location_support_requirements_title'))
            ->assertSee('data-location-support-editor', false)
            ->assertSee('name="location_support_requirements[0][assignments][0][selected]"', false)
            ->assertSee('refreshSharedLocationSupportRequirements', false)
            ->assertSee('data-cast-crew-nationality', false)
            ->assertSee('name="cast_crew[lead][first_name]"', false)
            ->assertSee('data-cast-crew-name-output', false)
            ->assertSee('data-cast-crew-identity-feedback', false)
            ->assertSee('name="cast_crew[lead][passport_image]"', false)
            ->assertSee('data-cast-crew-passport-image', false)
            ->assertSeeText(__('app.applications.annex_fields.passport_image_note'))
            ->assertSeeText(__('app.applications.cast_crew_national_id_digits'))
            ->assertSee('data-row-count-for', false)
            ->assertSee('name="work_content_summary_attachment"', false)
            ->assertSeeText(__('app.applications.annex_fields.work_summary_english_attachment_note'))
            ->assertSee('data-bs-target="#ProductionTerms"', false)
            ->assertSee('data-requirement-incomplete', false)
            ->assertSeeText(__('app.applications.form_incomplete_status'))
            ->assertSee('data-annex-save', false)
            ->assertSee('name="production_terms_accepted"', false)
            ->assertSeeText(__('app.applications.production_terms.legal_document'))
            ->assertSee('value="Applicant Owner"', false)
            ->assertSee('data-bs-target="#MinistryInteriorPersonalDetails"', false)
            ->assertSee('name="ministry_interior_personal_details[0][current_full_name]"', false)
            ->assertSee('data-ministry-personal-details-add', false)
            ->assertSee('data-ministry-personal-details-template', false)
            ->assertDontSee('ministry-personal-details-form__heading', false)
            ->assertSeeText(__('app.applications.annex_sections.ministry_interior_personal_details'))
            ->assertDontSee('data-application-password-strength', false)
            ->assertDontSee('name="international_account_password"', false)
            ->assertSee('formnovalidate', false)
            ->assertSee('js/form-wizard.js', false);

        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/name="producer_name"[^>]*value="Applicant Owner"[^>]*readonly/s', $content);
        $this->assertMatchesRegularExpression('/name="production_company_name"[^>]*value="Applicant Studio"[^>]*readonly/s', $content);
        $this->assertMatchesRegularExpression('/name="contact_address"[^>]*value="Amman, Jordan"[^>]*readonly/s', $content);
        $this->assertMatchesRegularExpression('/name="contact_phone"[^>]*value="0793333111"[^>]*readonly/s', $content);
        $this->assertMatchesRegularExpression('/name="contact_email"[^>]*value="studio@applicant\.test"[^>]*readonly/s', $content);
        $this->assertTrue(
            strpos($content, 'data-bs-target="#RFCGuidelines"') < strpos($content, 'data-bs-target="#ProductionTerms"'),
            'General terms must be the last item in the mandatory permit forms list.',
        );
        $this->assertTrue(
            strpos($content, 'data-bs-target="#ProductionTerms"') < strpos($content, 'data-bs-target="#MinistryInteriorPersonalDetails"'),
            'The Ministry of Interior form must be listed under project-needs forms after the mandatory forms.',
        );
    }

    public function test_application_profile_contact_fields_are_locked_to_approved_account_data(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $tamperedPayload = $this->applicationPayload([
            'producer_name' => 'Tampered Producer',
            'production_company_name' => 'Tampered Studio',
            'contact_address' => 'Tampered Address',
            'contact_phone' => '0000000000',
            'contact_email' => 'tampered@example.test',
        ]);

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $tamperedPayload)
            ->assertRedirect();

        $application = Application::query()->firstOrFail();

        $this->assertSame('Applicant Owner', data_get($application->metadata, 'producer.producer_name'));
        $this->assertSame('Applicant Studio', data_get($application->metadata, 'producer.production_company_name'));
        $this->assertSame('Amman, Jordan', data_get($application->metadata, 'producer.contact_address'));
        $this->assertSame('0793333111', data_get($application->metadata, 'producer.contact_phone'));
        $this->assertSame('studio@applicant.test', data_get($application->metadata, 'producer.contact_email'));

        $this
            ->actingAs($user)
            ->post(route('applications.update', $application), $this->applicationPayload([
                'project_name' => 'Updated Locked Fields Check',
                'producer_name' => 'Updated Tampered Producer',
                'production_company_name' => 'Updated Tampered Studio',
                'contact_address' => 'Updated Tampered Address',
                'contact_phone' => '1111111111',
                'contact_email' => 'updated-tampered@example.test',
            ]))
            ->assertRedirect(route('applications.show', $application));

        $application->refresh();

        $this->assertSame('Updated Locked Fields Check', $application->project_name);
        $this->assertSame('Applicant Owner', data_get($application->metadata, 'producer.producer_name'));
        $this->assertSame('Applicant Studio', data_get($application->metadata, 'producer.production_company_name'));
        $this->assertSame('Amman, Jordan', data_get($application->metadata, 'producer.contact_address'));
        $this->assertSame('0793333111', data_get($application->metadata, 'producer.contact_phone'));
        $this->assertSame('studio@applicant.test', data_get($application->metadata, 'producer.contact_email'));

        $metadata = $application->metadata ?? [];
        data_set($metadata, 'producer.producer_name', 'Legacy Edited Producer');
        data_set($metadata, 'producer.production_company_name', 'Legacy Edited Studio');
        data_set($metadata, 'producer.contact_address', 'Legacy Edited Address');
        data_set($metadata, 'producer.contact_phone', '2222222222');
        data_set($metadata, 'producer.contact_email', 'legacy-edited@example.test');
        $application->forceFill(['metadata' => $metadata])->save();

        $this
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application));

        $application->refresh();

        $this->assertSame('Applicant Owner', data_get($application->metadata, 'producer.producer_name'));
        $this->assertSame('Applicant Studio', data_get($application->metadata, 'producer.production_company_name'));
        $this->assertSame('Amman, Jordan', data_get($application->metadata, 'producer.contact_address'));
        $this->assertSame('0793333111', data_get($application->metadata, 'producer.contact_phone'));
        $this->assertSame('studio@applicant.test', data_get($application->metadata, 'producer.contact_email'));
    }

    public function test_work_type_controls_work_content_summary_minimum_words(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        WorkCategory::query()
            ->where('code', 'feature_film')
            ->update(['work_summary_min_words' => 5]);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->get(route('applications.create'))
            ->assertOk()
            ->assertSee('data-work-summary-min-words="5"', false)
            ->assertSeeText(__('app.applications.work_summary_instruction', ['min' => 5]));

        $this->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'work_content_summary_synopsis' => $this->arabicWorkContentSummary(4),
            ]))
            ->assertRedirect();

        $application = Application::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertSessionHasErrors('work_content_summary_synopsis');

        $this->assertSame('draft', $application->fresh()->status);

        $this->actingAs($user)
            ->post(route('applications.update', $application), $this->applicationPayload([
                'work_content_summary_synopsis' => $this->arabicWorkContentSummary(5),
            ]))
            ->assertRedirect(route('applications.show', $application));

        $this->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application));

        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_cast_crew_birth_date_must_be_before_today(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->from(route('applications.create'))
            ->post(route('applications.store'), $this->applicationPayload([
                'cast_crew' => [[
                    'name' => 'Future Actor',
                    'first_name' => 'Future',
                    'second_name' => 'Jordanian',
                    'third_name' => 'Test',
                    'family_name' => 'Actor',
                    'role' => 'Actor',
                    'nationality' => 'jordanian',
                    'gender' => 'male',
                    'birth_date' => now()->addDay()->toDateString(),
                    'identity_number' => '1234567890',
                ]],
            ]))
            ->assertRedirect(route('applications.create'))
            ->assertSessionHasErrors('cast_crew.0.birth_date');
    }

    public function test_jordanian_cast_crew_identity_must_be_ten_digits(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->from(route('applications.create'))
            ->post(route('applications.store'), $this->applicationPayload([
                'cast_crew' => [[
                    'name' => 'Jordanian Actor',
                    'first_name' => 'Jordanian',
                    'second_name' => 'Identity',
                    'third_name' => 'Test',
                    'family_name' => 'Actor',
                    'role' => 'Actor',
                    'nationality' => 'jordanian',
                    'gender' => 'male',
                    'birth_date' => '1990-03-15',
                    'identity_number' => 'J12345',
                ]],
            ]))
            ->assertRedirect(route('applications.create'))
            ->assertSessionHasErrors('cast_crew.0.identity_number');
    }

    public function test_jordanian_cast_crew_identity_rejects_short_and_long_numeric_values(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        foreach (['123456789', '12345678901'] as $identityNumber) {
            $this
                ->actingAs($user)
                ->from(route('applications.create'))
                ->post(route('applications.store'), $this->applicationPayload([
                    'cast_crew' => [[
                        'name' => 'Jordanian Identity Test Actor',
                        'first_name' => 'Jordanian',
                        'second_name' => 'Identity',
                        'third_name' => 'Test',
                        'family_name' => 'Actor',
                        'role' => 'Actor',
                        'nationality' => 'jordanian',
                        'gender' => 'male',
                        'birth_date' => '1990-03-15',
                        'identity_number' => $identityNumber,
                    ]],
                ]))
                ->assertRedirect(route('applications.create'))
                ->assertSessionHasErrors('cast_crew.0.identity_number');
        }
    }

    public function test_jordanian_cast_crew_requires_all_four_name_parts(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->from(route('applications.create'))
            ->post(route('applications.store'), $this->applicationPayload([
                'cast_crew' => [[
                    'name' => 'Incomplete Jordanian Name',
                    'role' => 'Actor',
                    'nationality' => 'jordanian',
                    'gender' => 'male',
                    'birth_date' => '1990-03-15',
                    'identity_number' => '1234567890',
                ]],
            ]))
            ->assertRedirect(route('applications.create'))
            ->assertSessionHasErrors([
                'cast_crew.0.first_name',
                'cast_crew.0.second_name',
                'cast_crew.0.third_name',
                'cast_crew.0.family_name',
            ]);
    }

    public function test_final_submission_requires_complete_foreign_cast_crew_passport_data(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'cast_crew' => [[
                    'name' => 'Foreign Actor',
                    'role' => 'Actor',
                    'nationality' => 'egyptian',
                    'gender' => 'male',
                    'birth_date' => '1990-03-15',
                    'identity_number' => 'P1234567',
                ]],
            ]))
            ->assertRedirect();

        $application = Application::query()->firstOrFail();

        $this
            ->actingAs($user)
            ->from(route('applications.show', $application))
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('cast_crew.0.passport_image');

        $this->assertSame('draft', $application->fresh()->status);
    }

    public function test_cast_crew_passports_follow_identity_and_ignore_forged_hidden_file_metadata(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'cast_crew' => [
                    [
                        'name' => 'Foreign Actor A',
                        'role' => 'Actor',
                        'nationality' => 'egyptian',
                        'gender' => 'male',
                        'birth_date' => '1990-03-15',
                        'identity_number' => 'PASS-A',
                        'passport_image' => UploadedFile::fake()->image('passport-a.png', 800, 600),
                    ],
                    [
                        'name' => 'Foreign Actor B',
                        'role' => 'Actor',
                        'nationality' => 'syrian',
                        'gender' => 'female',
                        'birth_date' => '1992-07-21',
                        'identity_number' => 'PASS-B',
                        'passport_image' => UploadedFile::fake()->image('passport-b.png', 800, 600),
                    ],
                ],
            ]))
            ->assertRedirect();

        $application = Application::query()->firstOrFail();
        $actorAPath = data_get($application->metadata, 'annex.cast_crew.0.passport_image_path');
        $actorBPath = data_get($application->metadata, 'annex.cast_crew.1.passport_image_path');

        Storage::disk('local')->assertExists($actorAPath);
        Storage::disk('local')->assertExists($actorBPath);

        $this
            ->actingAs($user)
            ->post(route('applications.update', $application), $this->applicationPayload([
                'project_name' => 'Crew Identity Matching Check',
                'cast_crew' => [
                    [
                        'name' => 'Foreign Actor B',
                        'role' => 'Actor',
                        'nationality' => 'syrian',
                        'gender' => 'female',
                        'birth_date' => '1992-07-21',
                        'identity_number' => 'PASS-B',
                        'passport_image_path' => $actorBPath,
                    ],
                    [
                        'name' => 'Foreign Actor A',
                        'role' => 'Actor',
                        'nationality' => 'egyptian',
                        'gender' => 'male',
                        'birth_date' => '1990-03-15',
                        'identity_number' => 'PASS-A',
                        'passport_image_path' => $actorAPath,
                    ],
                    [
                        'name' => 'Foreign Actor C',
                        'role' => 'Actor',
                        'nationality' => 'lebanese',
                        'gender' => 'male',
                        'birth_date' => '1994-01-11',
                        'identity_number' => 'PASS-C',
                        'passport_image_path' => $actorAPath,
                        'passport_image_name' => 'forged-passport.png',
                        'passport_image_mime_type' => 'image/png',
                        'passport_image_size' => 1234,
                        'passport_image_uploaded_at' => now()->toDateTimeString(),
                    ],
                ],
            ]))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();
        $crew = data_get($application->metadata, 'annex.cast_crew', []);

        $this->assertSame('PASS-B', data_get($crew, '0.identity_number'));
        $this->assertSame($actorBPath, data_get($crew, '0.passport_image_path'));
        $this->assertSame('PASS-A', data_get($crew, '1.identity_number'));
        $this->assertSame($actorAPath, data_get($crew, '1.passport_image_path'));
        $this->assertSame('PASS-C', data_get($crew, '2.identity_number'));
        $this->assertNull(data_get($crew, '2.passport_image_path'));
        $this->assertNull(data_get($crew, '2.passport_image_name'));
    }

    public function test_jordanian_airport_person_identity_must_be_ten_digits(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->from(route('applications.create'))
            ->post(route('applications.store'), $this->applicationPayload([
                'airport_people' => [[
                    'first_name' => 'Airport',
                    'second_name' => 'Access',
                    'third_name' => 'Crew',
                    'family_name' => 'Member',
                    'nationality' => 'jordanian',
                    'mother_name' => 'Mariam',
                    'identity_number' => 'A12345',
                    'profession' => 'Camera operator',
                    'address_phone' => 'Amman 0790000000',
                    'entry_reason' => 'Filming',
                    'target_area' => 'Departures hall',
                ]],
            ]))
            ->assertRedirect(route('applications.create'))
            ->assertSessionHasErrors('airport_people.0.identity_number');
    }

    public function test_jordanian_airport_person_name_parts_are_saved_as_full_name(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'airport_people' => [[
                    'first_name' => 'Airport',
                    'second_name' => 'Access',
                    'third_name' => 'Crew',
                    'family_name' => 'Member',
                    'nationality' => 'jordanian',
                    'mother_name' => 'Mariam',
                    'identity_number' => '9876543210',
                    'profession' => 'Camera operator',
                    'address_phone' => 'Amman 0790000000',
                    'entry_reason' => 'Filming',
                    'target_area' => 'Departures hall',
                ]],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();

        $this->assertSame('Airport Access Crew Member', data_get($application->metadata, 'annex.airport_people.0.full_name'));
        $this->assertSame('Airport', data_get($application->metadata, 'annex.airport_people.0.first_name'));
    }

    public function test_foreign_producer_account_is_created_by_secure_invitation_without_applicant_password_fields(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Notification::fake();

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'project_nationalities' => ['international'],
                'international_producer_email' => 'invited-producer@example.com',
                'international_producer_profile_url' => 'https://example.com/invited-producer',
                'international_producer_address' => 'Madrid',
                'international_producer_website' => 'https://invited-producer.example.com',
                'international_liaison_email' => 'invited-liaison@example.com',
                'international_liaison_mobile' => '+962799123456',
            ]))
            ->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();
        $foreignProducer = User::query()->findOrFail($application->foreignProducerUserId());

        $this->assertTrue($foreignProducer->requiresPasswordSetup());
        $this->assertNotNull($foreignProducer->invitation_sent_at);
        $this->assertFalse($foreignProducer->canSignIn());

        Notification::assertSentTo(
            $foreignProducer,
            ForeignProducerInvitationNotification::class,
            function (ForeignProducerInvitationNotification $notification) use ($application, $foreignProducer): bool {
                $mail = $notification->toMail($foreignProducer);
                $mailLines = collect([...$mail->introLines, ...$mail->outroLines])
                    ->map(static fn (mixed $line): string => (string) $line)
                    ->implode("\n");

                $this->assertStringContainsString(
                    trans('app.notifications.foreign_producer_invitation_mail_subject', [], 'ar'),
                    (string) $mail->subject,
                );
                $this->assertStringContainsString(
                    trans('app.notifications.foreign_producer_invitation_mail_subject', [], 'en'),
                    (string) $mail->subject,
                );
                $this->assertStringContainsString(
                    trans('app.notifications.foreign_producer_invitation_mail_intro', [
                        'project' => $application->project_name,
                        'code' => $application->code,
                    ], 'ar'),
                    $mailLines,
                );
                $this->assertStringContainsString(
                    trans('app.notifications.foreign_producer_invitation_mail_intro', [
                        'project' => $application->project_name,
                        'code' => $application->code,
                    ], 'en'),
                    $mailLines,
                );
                $this->assertStringContainsString(
                    trans('app.notifications.foreign_producer_invitation_mail_action', [], 'ar'),
                    (string) $mail->actionText,
                );
                $this->assertStringContainsString(
                    trans('app.notifications.foreign_producer_invitation_mail_action', [], 'en'),
                    (string) $mail->actionText,
                );

                parse_str((string) parse_url((string) $mail->actionUrl, PHP_URL_QUERY), $query);

                $this->assertSame($foreignProducer->email, $query['email'] ?? null);
                $this->assertSame('1', (string) ($query['invitation'] ?? ''));

                return true;
            },
        );

        $this
            ->actingAs($user)
            ->get(route('applications.edit', $application))
            ->assertOk()
            ->assertDontSee('name="international_account_password"', false)
            ->assertDontSee('name="international_account_password_confirmation"', false)
            ->assertSeeText(__('app.applications.foreign_producer_account_linked_title'));
    }

    public function test_foreign_producer_must_activate_account_before_login_and_signing(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Notification::fake();

        [$applicant] = $this->createApplicantContext();

        $this
            ->actingAs($applicant)
            ->post(route('applications.store'), $this->applicationPayload([
                'project_nationalities' => ['international'],
                'international_producer_email' => 'activation-producer@example.com',
                'international_producer_profile_url' => 'https://example.com/activation-producer',
                'international_producer_address' => 'Paris',
                'international_producer_website' => 'https://activation-producer.example.com',
                'international_liaison_email' => 'activation-liaison@example.com',
                'international_liaison_mobile' => '+962799222333',
            ]))
            ->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();
        $foreignProducer = User::query()->findOrFail($application->foreignProducerUserId());

        auth()->logout();

        $this
            ->post(route('login.store'), [
                'identifier' => $foreignProducer->email,
                'password' => 'AnyPassword@123',
            ])
            ->assertSessionHasErrors([
                'identifier' => __('app.auth.account_activation_required'),
            ]);

        $this
            ->actingAs($foreignProducer)
            ->post(route('profile.foreign-producer.applications.declaration.store', $application), [
                'declaration_accepted' => '1',
            ])
            ->assertForbidden();

        auth()->logout();
        $token = Password::broker()->createToken($foreignProducer);

        $this
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $foreignProducer->email,
                'password' => 'Activated@Password123',
                'password_confirmation' => 'Activated@Password123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        $foreignProducer->refresh();

        $this->assertFalse($foreignProducer->requiresPasswordSetup());
        $this->assertNotNull($foreignProducer->password_changed_at);
        $this->assertTrue(Hash::check('Activated@Password123', $foreignProducer->password));

        $this
            ->actingAs($foreignProducer)
            ->post(route('profile.foreign-producer.applications.declaration.store', $application), [
                'declaration_accepted' => '1',
            ])
            ->assertRedirect(route('profile.show', ['variant' => 'foreign_producer']));

        $this->assertTrue($application->fresh()->foreignProducerDeclarationIsSigned());
    }

    public function test_non_jordanian_project_requires_international_section_before_submission(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'project_nationalities' => ['international'],
                'international_producer_name' => null,
                'international_producer_nationality' => null,
                'international_producer_company' => null,
                'international_producer_email' => null,
                'international_producer_profile_url' => null,
                'international_producer_address' => null,
                'international_producer_website' => null,
                'international_liaison_name' => null,
                'international_liaison_email' => null,
                'international_liaison_mobile' => null,
            ]));

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors([
                'international_producer_name',
                'international_producer_nationality',
                'international_producer_company',
                'international_producer_email',
                'international_producer_profile_url',
                'international_producer_address',
                'international_producer_website',
                'international_liaison_email',
                'international_liaison_mobile',
                'international_account_exists',
            ]);

        $this
            ->actingAs($user)
            ->post(route('applications.update', $application), $this->applicationPayload([
                'project_nationalities' => ['international'],
                'international_producer_name' => 'Global Partner',
                'international_producer_nationality' => 'non_jordanian',
                'international_producer_company' => 'Global Films',
                'international_producer_email' => 'global-submit@example.com',
                'international_producer_profile_url' => 'https://example.com/global-submit',
                'international_producer_address' => 'London',
                'international_producer_website' => 'https://global-submit.example.com',
                'international_liaison_name' => 'Global Liaison',
                'international_liaison_email' => 'liaison-submit@example.com',
                'international_liaison_mobile' => '+962799000111',
            ]))
            ->assertRedirect(route('applications.show', $application));

        $this->assertNotEmpty(data_get($application->fresh()->metadata, 'international.account.user_id'));

        $application->refresh();
        $foreignProducer = User::query()->findOrFail($application->foreignProducerUserId());

        $foreignProducer->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $this
            ->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSeeText(__('app.applications.foreign_producer_approval_pending_title'))
            ->assertSeeText(__('app.applications.foreign_producer_approval_pending_action'));

        $this
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('foreign_producer_declaration');

        $this->assertSame('draft', $application->fresh()->status);

        $this
            ->actingAs($foreignProducer)
            ->post(route('profile.foreign-producer.applications.declaration.store', $application), [
                'declaration_accepted' => '1',
            ])
            ->assertRedirect(route('profile.show', ['variant' => 'foreign_producer']));

        $this->assertTrue($application->fresh()->foreignProducerDeclarationIsSigned());

        $this
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_student_non_jordanian_project_never_requires_international_project_section(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $individualsGroup = Group::query()->where('code', 'individuals')->firstOrFail();
        [$user] = $this->createApplicantContext([
            'registration_type' => 'student',
            'name' => 'Student Applicant',
            'username' => 'student-international-check',
            'email' => 'student-international-check@example.com',
            'national_id' => '9988776611',
        ], [
            'group_id' => $individualsGroup->getKey(),
            'registration_type' => 'student',
            'name_en' => 'Student Applicant',
            'name_ar' => 'Student Applicant',
            'registration_no' => null,
            'national_id' => '9988776611',
            'metadata' => [
                'address' => 'Amman Student Address',
            ],
        ]);

        $this
            ->actingAs($user)
            ->get(route('applications.create'))
            ->assertOk()
            ->assertDontSee('id="international_projects_tab"', false);

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'project_nationality' => 'international',
                'project_nationalities' => ['international'],
                'international_producer_name' => null,
                'international_producer_nationality' => null,
                'international_producer_company' => null,
                'international_producer_email' => null,
                'international_producer_profile_url' => null,
                'international_producer_address' => null,
                'international_producer_website' => null,
                'international_liaison_name' => null,
                'international_liaison_email' => null,
                'international_liaison_mobile' => null,
                'international_account_exists' => null,
                'international_account_password' => null,
                'international_account_password_confirmation' => null,
            ]))
            ->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();

        $this->assertNull(data_get($application->metadata, 'international.account.user_id'));
        $this->assertNull(data_get($application->metadata, 'international.international_liaison_email'));

        $this
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_applicant_can_save_incomplete_draft_but_cannot_submit_until_complete(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user, $entity] = $this->createApplicantContext();

        $storeResponse = $this
            ->actingAs($user)
            ->post(route('applications.store'), [
                'project_name' => 'Partial Draft',
            ]);

        $application = Application::query()->firstOrFail();

        $storeResponse->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'entity_id' => $entity->getKey(),
            'project_name' => 'Partial Draft',
            'status' => 'draft',
        ]);

        $submitResponse = $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application));

        $submitResponse
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors([
                'project_nationalities',
                'work_category',
                'release_method',
                'planned_start_date',
                'estimated_crew_count',
                'safety_guidelines_acknowledged',
            ]);

        $this->assertSame('draft', $application->fresh()->status);

        $this
            ->actingAs($user)
            ->post(route('applications.update', $application), $this->applicationPayload([
                'project_name' => 'Completed Draft',
            ]))
            ->assertRedirect(route('applications.show', $application));

        $this
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'project_name' => 'Completed Draft',
            'status' => 'submitted',
        ]);
    }

    public function test_application_form_accepts_lookup_backed_nationalities(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $response = $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'project_nationalities' => ['egyptian', 'jordanian'],
                'director_nationality' => 'egyptian',
                'international_producer_nationality' => 'american',
            ]));

        $application = Application::query()->firstOrFail();

        $response
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $this->assertSame('egyptian', $application->project_nationality);
        $this->assertSame(['egyptian', 'jordanian'], $application->project_nationalities);
        $this->assertSame(['egyptian', 'jordanian'], data_get($application->metadata, 'project.nationalities'));
        $this->assertSame('egyptian', data_get($application->metadata, 'director.director_nationality'));
        $this->assertSame('american', data_get($application->metadata, 'international.international_producer_nationality'));

        $this
            ->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSeeText('Egyptian, Jordanian');
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

        $response
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

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

    public function test_filming_location_start_date_must_be_current_on_final_submit(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->travelTo(Carbon::parse('2026-07-10 09:00:00'));
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'maan',
                    'location_name' => 'Wadi Rum Reserve',
                    'address' => 'Wadi Rum',
                    'nature' => 'Protected reserve landscape',
                    'location_type' => 'reserves',
                    'start_date' => '2026-07-10',
                    'end_date' => '2026-07-12',
                ]],
            ]))
            ->assertRedirect();

        $application = Application::query()->firstOrFail();

        $this->travelTo(Carbon::parse('2026-07-17 09:00:00'));

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('filming_locations.0.start_date');

        $application->refresh();

        $this->assertSame('draft', $application->status);

        $this->travelBack();
    }

    public function test_filming_location_address_is_required_on_final_submit(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->travelTo(Carbon::parse('2026-07-10 09:00:00'));
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'maan',
                    'location_name' => 'Wadi Rum Reserve',
                    'address' => '',
                    'nature' => 'Protected reserve landscape',
                    'location_type' => 'reserves',
                    'start_date' => '2026-08-10',
                    'end_date' => '2026-08-12',
                ]],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('filming_locations.0.address');

        $this->assertSame('draft', $application->fresh()->status);

        $this->travelBack();
    }

    public function test_location_support_requirement_date_must_stay_inside_location_range(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->travelTo(Carbon::parse('2026-07-10 09:00:00'));
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'maan',
                    'location_name' => 'Wadi Rum Reserve',
                    'address' => 'Wadi Rum',
                    'nature' => 'Protected reserve landscape',
                    'location_type' => 'reserves',
                    'start_date' => '2026-07-12',
                    'end_date' => '2026-07-15',
                    'support_requirements' => [[
                        'authority' => 'public_security',
                        'requirement' => 'Patrol support',
                        'date' => '2026-07-16',
                        'time_from' => '08:00',
                        'time_to' => '10:00',
                    ]],
                ]],
            ]))
            ->assertRedirect();

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('filming_locations.0.support_requirements.0.date');

        $application->refresh();

        $this->assertSame('draft', $application->status);

        $this->travelBack();
    }

    public function test_location_support_requirement_notes_are_required_before_submit_when_requirement_is_selected(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->travelTo(Carbon::parse('2026-07-10 09:00:00'));
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'maan',
                    'location_name' => 'Wadi Rum Reserve',
                    'address' => 'Wadi Rum',
                    'nature' => 'Protected reserve landscape',
                    'location_type' => 'reserves',
                    'start_date' => '2026-07-12',
                    'end_date' => '2026-07-15',
                    'support_requirements' => [[
                        'authority' => 'public_security',
                        'requirement' => 'police_presence',
                        'date' => '2026-07-13',
                        'time_from' => '08:00',
                        'time_to' => '10:00',
                        'notes' => '',
                    ]],
                ]],
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('filming_locations.0.support_requirements.0.notes');

        $this
            ->actingAs($user)
            ->post(route('applications.update', $application), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'maan',
                    'location_name' => 'Wadi Rum Reserve',
                    'address' => 'Wadi Rum',
                    'nature' => 'Protected reserve landscape',
                    'location_type' => 'reserves',
                    'start_date' => '2026-07-12',
                    'end_date' => '2026-07-15',
                    'support_requirements' => [[
                        'authority' => 'public_security',
                        'requirement' => 'police_presence',
                        'date' => '2026-07-13',
                        'time_from' => '08:00',
                        'time_to' => '10:00',
                        'notes' => 'Public security presence is required at the western access road with full checkpoint details.',
                    ]],
                ]],
            ]))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionDoesntHaveErrors();

        $this
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('submitted', $application->fresh()->status);

        $this->travelBack();
    }

    public function test_one_shared_location_support_requirement_can_be_assigned_to_multiple_filming_locations(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();
        $firstStart = now()->addDays(30)->toDateString();
        $firstEnd = now()->addDays(36)->toDateString();
        $secondStart = now()->addDays(32)->toDateString();
        $secondEnd = now()->addDays(38)->toDateString();
        $sharedDate = now()->addDays(34)->toDateString();

        $response = $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [
                    [
                        'location_key' => 'amman_street',
                        'governorate' => 'amman',
                        'location_name' => 'Amman Street',
                        'address' => 'Central Amman',
                        'nature' => 'Public street',
                        'location_type' => 'public_locations',
                        'start_date' => $firstStart,
                        'end_date' => $firstEnd,
                    ],
                    [
                        'location_key' => 'zarqa_street',
                        'governorate' => 'zarqa',
                        'location_name' => 'Zarqa Street',
                        'address' => 'Central Zarqa',
                        'nature' => 'Public street',
                        'location_type' => 'public_locations',
                        'start_date' => $secondStart,
                        'end_date' => $secondEnd,
                    ],
                ],
                'location_support_requirements' => [[
                    'requirement_key' => 'shared_road_closure',
                    'authority' => 'public_security',
                    'requirement' => 'road_closures',
                    'notes' => 'Close both streets using the same traffic management plan.',
                    'schedule_mode' => 'shared',
                    'shared_date' => $sharedDate,
                    'shared_time_from' => '08:00',
                    'shared_time_to' => '12:00',
                    'assignments' => [
                        ['location_key' => 'amman_street', 'selected' => '1'],
                        ['location_key' => 'zarqa_street', 'selected' => '1'],
                    ],
                ]],
            ]));

        $application = Application::query()->firstOrFail();

        $response
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $requirements = data_get($application->metadata, 'annex.location_support_requirements');
        $locations = data_get($application->metadata, 'annex.filming_locations');
        $legacyRows = data_get($application->metadata, 'annex.public_security_support');
        $publicSecurity = Entity::query()->where('code', 'public-security-directorate')->firstOrFail();

        $this->assertCount(1, $requirements);
        $this->assertCount(2, data_get($requirements, '0.assignments'));
        $this->assertSame($publicSecurity->code, data_get($requirements, '0.authority'));
        $this->assertSame($publicSecurity->name_en, data_get($requirements, '0.authority_name_en'));
        $this->assertSame('Road closures', data_get($requirements, '0.requirement_name_en'));
        $this->assertSame('shared', data_get($requirements, '0.schedule_mode'));
        $this->assertSame($sharedDate, data_get($requirements, '0.shared_date'));
        $this->assertSame('road_closures', data_get($locations, '0.support_requirements.0.requirement'));
        $this->assertSame($sharedDate, data_get($locations, '0.support_requirements.0.date'));
        $this->assertSame($sharedDate, data_get($locations, '1.support_requirements.0.date'));
        $this->assertCount(2, $legacyRows);
        $this->assertSame(['Amman Street', 'Zarqa Street'], collect($legacyRows)->pluck('location')->all());
    }

    public function test_location_support_requirement_must_be_assigned_to_the_selected_authority(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $response = $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [[
                    'location_key' => 'mismatched_location',
                    'governorate' => 'amman',
                    'location_name' => 'Mismatched Location',
                    'address' => 'Amman',
                    'nature' => 'Public location',
                    'location_type' => 'public_locations',
                    'start_date' => now()->addDays(30)->toDateString(),
                    'end_date' => now()->addDays(32)->toDateString(),
                ]],
                'location_support_requirements' => [[
                    'authority' => 'public-security-directorate',
                    'requirement' => 'armed_forces',
                    'notes' => 'This requirement belongs to another authority.',
                    'schedule_mode' => 'shared',
                    'shared_date' => now()->addDays(31)->toDateString(),
                    'assignments' => [[
                        'location_key' => 'mismatched_location',
                        'selected' => '1',
                    ]],
                ]],
            ]));

        $response
            ->assertRedirect()
            ->assertSessionHasErrors('location_support_requirements.0.requirement');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_location_support_requirement_can_keep_separate_schedules_for_each_assigned_location(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();
        $firstDate = now()->addDays(31)->toDateString();
        $secondDate = now()->addDays(35)->toDateString();

        $response = $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [
                    [
                        'location_key' => 'first_location',
                        'governorate' => 'amman',
                        'location_name' => 'First Location',
                        'address' => 'Amman',
                        'nature' => 'Public site',
                        'location_type' => 'public_locations',
                        'start_date' => now()->addDays(30)->toDateString(),
                        'end_date' => now()->addDays(32)->toDateString(),
                    ],
                    [
                        'location_key' => 'second_location',
                        'governorate' => 'zarqa',
                        'location_name' => 'Second Location',
                        'address' => 'Zarqa',
                        'nature' => 'Public site',
                        'location_type' => 'public_locations',
                        'start_date' => now()->addDays(34)->toDateString(),
                        'end_date' => now()->addDays(36)->toDateString(),
                    ],
                ],
                'location_support_requirements' => [[
                    'requirement_key' => 'separate_police_presence',
                    'authority' => 'public_security',
                    'requirement' => 'police_presence',
                    'notes' => 'Coordinate separate teams and schedules for both sites.',
                    'schedule_mode' => 'per_location',
                    'assignments' => [
                        [
                            'location_key' => 'first_location',
                            'selected' => '1',
                            'date' => $firstDate,
                            'time_from' => '07:00',
                            'time_to' => '09:00',
                        ],
                        [
                            'location_key' => 'second_location',
                            'selected' => '1',
                            'date' => $secondDate,
                            'time_from' => '14:00',
                            'time_to' => '16:00',
                        ],
                    ],
                ]],
            ]));

        $application = Application::query()->firstOrFail();

        $response
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $requirements = data_get($application->metadata, 'annex.location_support_requirements');
        $locations = data_get($application->metadata, 'annex.filming_locations');

        $this->assertSame('per_location', data_get($requirements, '0.schedule_mode'));
        $this->assertSame($firstDate, data_get($requirements, '0.assignments.0.date'));
        $this->assertSame($secondDate, data_get($requirements, '0.assignments.1.date'));
        $this->assertSame($firstDate, data_get($locations, '0.support_requirements.0.date'));
        $this->assertSame('07:00', data_get($locations, '0.support_requirements.0.time_from'));
        $this->assertSame($secondDate, data_get($locations, '1.support_requirements.0.date'));
        $this->assertSame('14:00', data_get($locations, '1.support_requirements.0.time_from'));
    }

    public function test_shared_location_support_date_must_fit_every_selected_location(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [
                    [
                        'location_key' => 'first_location',
                        'governorate' => 'amman',
                        'location_name' => 'First Location',
                        'address' => 'Amman',
                        'nature' => 'Public site',
                        'location_type' => 'public_locations',
                        'start_date' => now()->addDays(30)->toDateString(),
                        'end_date' => now()->addDays(32)->toDateString(),
                    ],
                    [
                        'location_key' => 'second_location',
                        'governorate' => 'zarqa',
                        'location_name' => 'Second Location',
                        'address' => 'Zarqa',
                        'nature' => 'Public site',
                        'location_type' => 'public_locations',
                        'start_date' => now()->addDays(31)->toDateString(),
                        'end_date' => now()->addDays(34)->toDateString(),
                    ],
                ],
                'location_support_requirements' => [[
                    'authority' => 'public_security',
                    'requirement' => 'road_closures',
                    'notes' => 'Shared closure details for both sites.',
                    'schedule_mode' => 'shared',
                    'shared_date' => now()->addDays(34)->toDateString(),
                    'assignments' => [
                        ['location_key' => 'first_location', 'selected' => '1'],
                        ['location_key' => 'second_location', 'selected' => '1'],
                    ],
                ]],
            ]));

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('location_support_requirements.0.shared_date');

        $this->assertSame('draft', $application->fresh()->status);
    }

    public function test_location_type_approval_days_use_jordan_business_days(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->travelTo(Carbon::parse('2026-07-09 09:00:00'));
        $this->seed(AccessControlSeeder::class);

        FilmingLocationType::query()
            ->where('code', 'religious_sites')
            ->update(['approval_days' => 2]);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'amman',
                    'location_name' => 'Historic Church',
                    'address' => 'Central Amman',
                    'nature' => 'Religious site',
                    'location_type' => 'religious_sites',
                    'start_date' => '2026-07-12',
                    'end_date' => '2026-07-13',
                ]],
            ]))
            ->assertRedirect();

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('filming_locations.0.start_date');

        $this
            ->actingAs($user)
            ->post(route('applications.update', $application), $this->applicationPayload([
                'filming_locations' => [[
                    'governorate' => 'amman',
                    'location_name' => 'Historic Church',
                    'address' => 'Central Amman',
                    'nature' => 'Religious site',
                    'location_type' => 'religious_sites',
                    'start_date' => '2026-07-13',
                    'end_date' => '2026-07-14',
                ]],
            ]))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $this->assertSame('submitted', $application->fresh()->status);

        $this->travelBack();
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
                    'post_production' => ['start_date' => '2026-04-30', 'end_date' => '2026-06-01'],
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

    public function test_post_production_can_start_when_filming_starts_before_wrap_ends(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'planned_start_date' => '2026-05-01',
                'planned_end_date' => '2026-05-10',
                'schedule_phases' => [
                    'preparation' => ['start_date' => '2026-04-20', 'end_date' => '2026-04-30'],
                    'wrap' => ['start_date' => '2026-05-10', 'end_date' => '2026-05-12'],
                    'post_production' => ['start_date' => '2026-05-01', 'end_date' => '2026-06-01'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();

        $this->assertSame('2026-05-01', data_get($application->metadata, 'schedule.phases.post_production.start_date'));
    }

    public function test_application_requires_budget_breakdown_when_local_spend_reaches_threshold(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $storeResponse = $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'local_spend_estimate' => 175000,
                'budget_items' => [
                    'jordanian_actors' => ['units' => '', 'total' => ''],
                ],
            ]));

        $application = Application::query()->firstOrFail();

        $storeResponse->assertRedirect(route('applications.show', $application));

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors([
                'budget_items.jordanian_actors.units',
                'budget_items.jordanian_actors.total',
            ]);

        $this->assertSame('draft', $application->fresh()->status);
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

    public function test_traveler_equipment_acknowledgement_is_required_before_final_submit(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this->actingAs($user)->post(route('applications.store'), $this->applicationPayload([
            'equipment_travelers' => [
                ['traveler_name' => 'Equipment Handler', 'arrival_date' => '2026-04-28'],
            ],
            'imported_equipment' => [
                ['transport_group' => 'traveler', 'item' => 'Camera kit', 'traveler_name' => 'Equipment Handler'],
            ],
            'traveler_equipment_acknowledged' => '0',
        ]));

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('traveler_equipment_acknowledged');

        $this->assertSame('draft', $application->fresh()->status);
    }

    public function test_shipping_equipment_acknowledgement_is_required_before_final_submit(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this->actingAs($user)->post(route('applications.store'), $this->applicationPayload([
            'imported_equipment' => [
                [
                    'transport_group' => 'shipping',
                    'shipping_company_name' => 'RFC Freight Services',
                    'invoice_number' => 'INV-2026-001',
                ],
            ],
            'shipping_equipment_acknowledged' => '0',
        ]));

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('shipping_equipment_acknowledged');

        $this->assertSame('draft', $application->fresh()->status);
    }

    public function test_started_project_needs_forms_require_completion_before_final_submit(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $cases = [
            [
                'payload' => [
                    'ministry_interior_personal_details' => [[
                        'current_full_name' => 'Partially Entered Visitor',
                    ]],
                ],
                'errors' => [
                    'ministry_interior_personal_details.0.current_nationality',
                    'ministry_interior_personal_details.0.confirmed',
                ],
            ],
            [
                'payload' => [
                    'imported_equipment' => [[
                        'transport_group' => 'shipping',
                        'shipping_company_name' => 'Partial Shipping Company',
                    ]],
                    'shipping_equipment_acknowledged' => '0',
                ],
                'errors' => [
                    'imported_equipment.0.invoice_number',
                    'imported_equipment.0.arrival_date',
                    'shipping_equipment_acknowledged',
                ],
            ],
            [
                'payload' => [
                    'airport_filming_airport_name' => 'Queen Alia International Airport',
                ],
                'errors' => [
                    'airport_filming_area',
                    'airport_filming_date',
                    'airport_people',
                ],
            ],
            [
                'payload' => [
                    'governmental_scenes' => [[
                        'site_name' => 'Partially Entered Government Site',
                    ]],
                    'governmental_scenes_confirmed' => '0',
                ],
                'errors' => [
                    'governmental_scenes.0.authority',
                    'governmental_scenes.0.scene_description',
                    'governmental_scenes_confirmed',
                ],
            ],
        ];

        foreach ($cases as $case) {
            $this
                ->actingAs($user)
                ->post(route('applications.store'), $this->applicationPayload($case['payload']))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $application = Application::query()->latest('id')->firstOrFail();

            $this
                ->from(route('applications.show', $application))
                ->actingAs($user)
                ->post(route('applications.submit', $application))
                ->assertRedirect(route('applications.show', $application))
                ->assertSessionHasErrors($case['errors']);

            $this->assertSame('draft', $application->fresh()->status);
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

        $this->assertSame('Applicant Owner', data_get($application->metadata, 'producer.producer_name'));
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

        $submitResponse
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

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
            ->assertSeeText('Official books prepared by')
            ->assertSeeText('Issue Official Books')
            ->assertSee('admin-official-letters-table', false)
            ->assertDontSeeText('Issue facilitation book')
            ->assertDontSeeText('Waiting on authority responses');

        $this->assertSame(
            ['public_security', 'environment'],
            data_get($application->fresh()->metadata, 'requirements.required_approvals')
        );
        $this->assertNotNull(data_get($application->fresh()->metadata, 'rfc_decision.official_books_prepared_at'));
        $this->assertSame($admin->getKey(), data_get($application->fresh()->metadata, 'rfc_decision.official_books_prepared_by_user_id'));
        $this->assertNotNull(data_get($application->fresh()->metadata, 'rfc_decision.facilitation_issued_at'));
        $this->assertSame($admin->getKey(), data_get($application->fresh()->metadata, 'rfc_decision.facilitation_issued_by_user_id'));
        $this->assertDatabaseMissing('application_authority_approvals', [
            'application_id' => $application->getKey(),
        ]);
        $this->assertDatabaseHas('application_official_letters', [
            'application_id' => $application->getKey(),
            'application_authority_approval_id' => null,
            'recipient_type' => 'authority',
            'serial_number' => $application->code.'-BOOK-01',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('application_official_letters', [
            'application_id' => $application->getKey(),
            'application_authority_approval_id' => null,
            'target_entity_id' => $entity->getKey(),
            'recipient_type' => 'applicant',
            'serial_number' => $application->code.'-RFC-FAC-01',
            'status' => 'draft',
        ]);
        $this->assertSame(3, $application->fresh()->officialLetters()->count());
        $applicantLetter = $application->fresh()->officialLetters()
            ->where('recipient_type', 'applicant')
            ->firstOrFail();
        $this->assertNull($applicantLetter->issued_at);
        $this->assertFalse($user->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'official_letter_issued'
                && data_get($notification->data, 'notification_highlight_summary') === 'Filming facilitation letter: Filming facilitation letter for request '.$application->code
        ));
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

        $applicantSendResponse = $this->actingAs($admin)->post(route('admin.applications.official-letters.send', [$application, $applicantLetter]));

        $applicantSendResponse->assertRedirect(route('admin.applications.show', $application));

        $applicantLetter->refresh();

        $this->assertSame('issued', $applicantLetter->status);
        $this->assertNotNull($applicantLetter->issued_at);
        $this->assertTrue($user->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'official_letter_issued'
                && data_get($notification->data, 'notification_highlight_summary') === 'Filming facilitation letter: Filming facilitation letter for request '.$application->code
        ));

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

    public function test_applicant_cannot_submit_without_accepting_general_terms_and_conditions(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $this->actingAs($user)->post(route('applications.store'), $this->applicationPayload([
            'production_terms_accepted' => '0',
        ]));

        $application = Application::query()->firstOrFail();

        $this
            ->from(route('applications.show', $application))
            ->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasErrors('production_terms_accepted');

        $this->assertSame('draft', $application->fresh()->status);
    }

    public function test_ministry_of_interior_personal_details_form_stores_authenticated_signature_and_is_displayed(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$user] = $this->createApplicantContext();

        $details = [
            'personal_number' => 'VISITOR-2048',
            'current_nationality' => 'jordanian',
            'current_full_name' => 'Applicant Owner Full Name',
            'original_nationality' => 'jordanian',
            'original_full_name' => 'Applicant Owner Original Name',
            'gender' => 'male',
            'passport_number' => 'P12345678',
            'passport_type' => 'Ordinary',
            'passport_issue_place' => 'Amman',
            'passport_issue_date' => '2024-01-15',
            'passport_expiry_date' => '2029-01-14',
            'birth_place' => 'Amman',
            'birth_date' => '1990-06-20',
            'education_qualification' => 'Bachelor degree',
            'profession' => 'Producer',
            'workplace' => 'Applicant Studio',
            'mother_full_name' => 'Mother Full Name',
            'mother_nationality' => 'jordanian',
            'spouse_full_name' => 'Spouse Full Name',
            'spouse_nationality' => 'jordanian',
            'spouse_birth_date' => '1992-04-10',
            'spouse_mother_full_name' => 'Spouse Mother Full Name',
            'visit_residence_reason' => 'Filming and production activity',
            'country_of_arrival' => 'Jordan',
            'country_of_residence' => 'Jordan',
            'residence_issue_date' => '2025-01-01',
            'residence_expiry_date' => '2027-01-01',
            'jordan_residence_address' => 'Amman, Jordan',
            'signature' => 'Forged Signature',
            'confirmed' => '1',
        ];

        $secondDetails = array_merge($details, [
            'personal_number' => 'VISITOR-4096',
            'current_full_name' => 'Second Visitor Full Name',
            'original_full_name' => 'Second Visitor Original Name',
            'passport_number' => 'P87654321',
            'signature' => 'Another Forged Signature',
        ]);

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'ministry_interior_personal_details' => [$details, $secondDetails],
            ]))
            ->assertRedirect();

        $application = Application::query()->firstOrFail();
        $storedDetails = MinistryInteriorPersonalDetails::rows(
            data_get($application->metadata, 'annex.ministry_interior_personal_details', []),
        );

        $this->assertCount(2, $storedDetails);
        $this->assertSame('Applicant Owner Full Name', data_get($storedDetails, '0.current_full_name'));
        $this->assertSame('P12345678', data_get($storedDetails, '0.passport_number'));
        $this->assertSame('Second Visitor Full Name', data_get($storedDetails, '1.current_full_name'));
        $this->assertSame('P87654321', data_get($storedDetails, '1.passport_number'));

        foreach ($storedDetails as $storedDetail) {
            $this->assertTrue((bool) data_get($storedDetail, 'confirmed'));
            $this->assertSame('Applicant Owner', data_get($storedDetail, 'signature'));
            $this->assertSame($user->getKey(), data_get($storedDetail, 'signed_by_user_id'));
            $this->assertNotNull(data_get($storedDetail, 'signed_at'));
        }

        $this
            ->actingAs($user)
            ->get(route('applications.edit', $application))
            ->assertOk()
            ->assertSee('name="ministry_interior_personal_details[0][current_full_name]"', false)
            ->assertSee('name="ministry_interior_personal_details[1][current_full_name]"', false)
            ->assertSee('data-ministry-personal-details-add', false)
            ->assertSee('value="Applicant Owner Full Name"', false)
            ->assertSee('value="Second Visitor Full Name"', false)
            ->assertSee('value="Applicant Owner"', false);

        $this
            ->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('MinistryInteriorPersonalDetailsView', false)
            ->assertSee('value="Applicant Owner Full Name"', false)
            ->assertSee('value="Second Visitor Full Name"', false)
            ->assertSeeText(__('app.applications.annex_sections.ministry_interior_personal_details'));
    }

    public function test_modern_ministry_personal_details_store_attachment_and_support_protected_download(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');

        [$user] = $this->createApplicantContext();

        $details = [
            'personal_number' => '1234567890',
            'nationality_category' => 'foreign',
            'current_nationality' => 'egyptian',
            'first_name' => 'Nadia',
            'father_name' => 'Mahmoud',
            'grandfather_name' => 'Hassan',
            'family_name' => 'Ali',
            'gender' => 'female',
            'marital_status' => 'married',
            'birth_place' => 'Cairo',
            'birth_date' => '1992-05-11',
            'mother_full_name' => 'Mona Hassan',
            'mother_nationality' => 'egyptian',
            'education_qualification' => 'Bachelor degree',
            'country_of_arrival' => 'Egypt',
            'country_of_residence' => 'Egypt',
            'schengen_us_visa' => 'no',
            'spouse_full_name' => 'Omar Salem',
            'spouse_nationality' => 'egyptian',
            'spouse_birth_date' => '1990-02-10',
            'spouse_mother_full_name' => 'Huda Salem',
            'residence_expiry_date' => '2028-01-01',
            'previous_jordan_residence' => 'yes',
            'investment_card' => 'no',
            'free_zones_card' => 'no',
            'jordan_governorate' => 'amman',
            'jordan_residence_address' => 'Amman, Jordan',
            'passport_type' => 'ordinary',
            'passport_number' => 'A1234567',
            'passport_issue_place' => 'Cairo',
            'passport_issue_date' => '2024-01-01',
            'passport_expiry_date' => '2029-01-01',
            'entry_method' => 'visa',
            'departure_document' => 'passport',
            'departure_method' => 'facilitation_letter',
            'attachments' => [[
                'document_type' => 'passport_copy',
                'file' => UploadedFile::fake()->image('passport.jpg', 800, 600)->size(200),
            ]],
            'confirmed' => '1',
        ];

        $this
            ->actingAs($user)
            ->post(route('applications.store'), $this->applicationPayload([
                'ministry_interior_personal_details' => [$details],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();
        $storedDetails = MinistryInteriorPersonalDetails::rows(
            data_get($application->metadata, 'annex.ministry_interior_personal_details', []),
        );
        $storedAttachment = (array) data_get($storedDetails, '0.attachments.0', []);

        $this->assertSame('Nadia Mahmoud Hassan Ali', data_get($storedDetails, '0.current_full_name'));
        $this->assertSame('Applicant Owner', data_get($storedDetails, '0.signature'));
        $this->assertSame('passport_copy', $storedAttachment['document_type'] ?? null);
        Storage::disk('local')->assertExists((string) ($storedAttachment['path'] ?? ''));

        $this
            ->actingAs($user)
            ->get(route('applications.edit', $application))
            ->assertOk()
            ->assertSee('data-ministry-residence-document-notice', false)
            ->assertSeeText(__('app.applications.ministry_interior_personal_details.residence_document_notice'));

        $this
            ->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('value="Nadia"', false)
            ->assertSee('value="Mahmoud"', false)
            ->assertSee(route('applications.annex.personal-details.attachments.download', [
                $application,
                0,
                $storedAttachment['id'],
            ]), false);

        $this
            ->actingAs($user)
            ->get(route('applications.annex.personal-details.attachments.download', [
                $application,
                0,
                $storedAttachment['id'],
            ]))
            ->assertOk();

        $this
            ->actingAs($user)
            ->get(route('applications.forms.print', $application).'?form=ministry_interior_personal_details')
            ->assertOk()
            ->assertSee('value="Nadia"', false)
            ->assertSee('value="Mahmoud"', false)
            ->assertSee('data-print-form="ministry_interior_personal_details"', false);
    }

    public function test_application_stores_and_displays_structured_annex_forms(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->travelTo(Carbon::parse('2026-04-01 09:00:00'));
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant, $applicantEntity] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $workContentSummary = $this->arabicWorkContentSummary();
        $crewLookup = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $crewLookup->shouldReceive('lookup')
            ->once()
            ->with('1234567890', 'jordanian')
            ->andReturn(['ok' => false, 'error' => 'HTTP_ERROR']);
        $crewVerification = (new CrewIdentityVerificationService($crewLookup))->lookup(
            '1234567890',
            'jordanian',
            $applicant->getKey(),
        );

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_nationalities' => ['international'],
            'work_category' => 'feature_film',
            'work_category_other' => 'Hybrid docudrama',
            'release_methods' => ['cinema', 'web', 'other'],
            'release_method_other' => 'Community screenings',
            'planned_start_date' => '2026-05-02',
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
            'work_content_summary_synopsis' => $workContentSummary,
            'work_content_summary_confirmed' => '1',
            'work_content_summary_attachment' => UploadedFile::fake()->create(
                'english-work-content-summary.pdf',
                320,
                'application/pdf',
            ),
            'cast_crew' => [
                ['name' => 'Jordanian Lead Test Actor', 'first_name' => 'Jordanian', 'second_name' => 'Lead', 'third_name' => 'Test', 'family_name' => 'Actor', 'role' => 'Actor', 'nationality' => 'jordanian', 'gender' => 'male', 'birth_date' => '1990-03-15', 'identity_number' => '1234567890', 'identity_verification_status' => $crewVerification['status'], 'identity_verification_source' => $crewVerification['source'], 'identity_verified_at' => $crewVerification['verified_at'], 'identity_verification_category' => 'jordanian', 'verification_token' => $crewVerification['proof'], 'passport_image' => UploadedFile::fake()->image('ignored-jordanian-passport.png', 800, 600)->size(200)],
                ['name' => 'International Supporting Actor', 'role' => 'Supporting actor', 'nationality' => 'egyptian', 'gender' => 'female', 'birth_date' => '1992-07-21', 'identity_number' => 'P9876543', 'passport_image' => UploadedFile::fake()->image('international-actor-passport.png', 800, 600)->size(280)],
            ],
            'filming_locations' => [
                [
                    'governorate' => 'amman',
                    'location_name' => 'Downtown Amman',
                    'address' => 'GPS pin 31.9,35.9',
                    'nature' => 'Open public street',
                    'location_type' => 'public_locations',
                    'special_requirements' => ['road_closures', 'police_presence'],
                    'start_date' => '2026-05-02',
                    'end_date' => '2026-05-04',
                    'support_requirements' => [
                        ['authority' => 'public_security', 'date' => '2026-05-04', 'time_from' => '08:00', 'time_to' => '12:00', 'requirement' => 'Traffic escort', 'notes' => 'Street lockup support'],
                        ['authority' => 'military', 'date' => '2026-05-04', 'time_from' => '12:30', 'time_to' => '14:00', 'requirement' => 'Military standby', 'notes' => 'Shared location coordination'],
                    ],
                ],
                [
                    'governorate' => 'mafraq',
                    'location_name' => 'Northern range',
                    'address' => 'Northern range',
                    'nature' => 'Controlled zone perimeter',
                    'location_type' => 'public_locations',
                    'start_date' => '2026-05-05',
                    'end_date' => '2026-05-05',
                    'support_requirements' => [
                        ['authority' => 'military', 'date' => '2026-05-05', 'time_from' => '09:00', 'time_to' => '11:00', 'requirement' => 'Military escort', 'notes' => 'Controlled access coordination'],
                    ],
                ],
            ],
            'special_location_requirements' => [
                'road_closures' => ['locations' => ['Downtown Amman'], 'notes' => 'Road lockup from 6 AM'],
            ],
            'safety_guidelines_acknowledged' => '1',
            'safety_guidelines_notes' => 'No pyrotechnics. Traffic marshals requested.',
            'equipment_travelers' => [
                [
                    'traveler_name' => 'Equipment Handler',
                    'arrival_date' => '2026-04-28',
                    'arrival_flight_number' => 'RJ102',
                    'departure_date' => '2026-05-12',
                    'departure_flight_number' => 'RJ103',
                    'passport_image' => UploadedFile::fake()->image('equipment-handler-passport.jpg', 800, 600)->size(300),
                ],
            ],
            'traveler_equipment_acknowledged' => '1',
            'shipping_equipment_acknowledged' => '1',
            'imported_equipment' => [
                ['transport_group' => 'shipping', 'shipping_company_name' => 'Jordan Freight', 'invoice_number' => 'INV-7788', 'bill_of_lading_number' => '', 'arrival_date' => '2026-04-28', 'departure_date' => '', 'customs_center' => 'queen_alia_international_airport', 'attachment' => UploadedFile::fake()->create('jordan-freight-invoice.pdf', 240, 'application/pdf')],
                ['transport_group' => 'traveler', 'item' => 'Camera crane', 'serial_number' => 'CR-7788', 'flight_reference' => 'RJ102', 'traveler_name' => 'Equipment Handler', 'quantity' => 3, 'unit_value' => 9000, 'total_value' => 1, 'classification' => 'camera_equipment', 'shipping_method' => 'luggage', 'origin_country' => 'Germany', 'entry_point' => 'queen_alia_international_airport'],
            ],
            'airport_filming_airport_name' => 'Queen Alia International Airport',
            'airport_filming_area' => 'Departures hall',
            'airport_filming_date' => '2026-05-04',
            'airport_filming_crew_count' => 12,
            'airport_filming_notes' => 'Small handheld crew.',
            'airport_people' => [
                ['full_name' => 'Airport Crew Member', 'first_name' => 'Airport', 'second_name' => 'Crew', 'third_name' => 'Test', 'family_name' => 'Member', 'nationality' => 'jordanian', 'mother_name' => 'Mariam', 'identity_number' => '9876543210', 'profession' => 'Camera operator', 'address_phone' => 'Amman 0790000000', 'entry_reason' => 'Filming', 'target_area' => 'Departures hall'],
            ],
            'governmental_scenes' => [
                ['site_name' => 'Municipal archive', 'authority' => 'Greater Amman Municipality', 'scene_description' => 'Exterior establishing shot', 'filming_date' => '2026-05-05'],
            ],
            'governmental_scenes_confirmed' => '1',
        ]));

        $application = Application::query()->firstOrFail();

        $this->assertSame($workContentSummary, data_get($application->metadata, 'annex.work_content_summary.synopsis'));
        $this->assertTrue(data_get($application->metadata, 'annex.production_terms.accepted'));
        $this->assertSame('production_form_2025', data_get($application->metadata, 'annex.production_terms.version'));
        $this->assertSame('Applicant Owner', data_get($application->metadata, 'annex.production_terms.local_applicant_name'));
        $this->assertSame('Applicant Owner', data_get($application->metadata, 'annex.production_terms.local_signature'));
        $this->assertSame('Global Partner', data_get($application->metadata, 'annex.production_terms.foreign_applicant_name'));
        $this->assertSame(['feature_film'], data_get($application->metadata, 'project.work_categories'));
        $this->assertSame('Hybrid docudrama', data_get($application->metadata, 'project.work_category_other'));
        $this->assertSame('2026-04-20', data_get($application->metadata, 'schedule.phases.preparation.start_date'));
        $this->assertSame(55000, data_get($application->metadata, 'budget.local_spend_estimate'));
        $this->assertSame(12000, data_get($application->metadata, 'budget.items.equipment_costs.total'));
        $this->assertSame('non_jordanian', data_get($application->metadata, 'international.international_producer_nationality'));
        $this->assertSame('liaison.global@example.com', data_get($application->metadata, 'international.account.email'));
        $this->assertTrue(data_get($application->metadata, 'international.account.read_only'));
        $this->assertTrue(data_get($application->metadata, 'annex.work_content_summary.confirmed'));
        $this->assertSame('english-work-content-summary.pdf', data_get($application->metadata, 'annex.work_content_summary.attachment_name'));
        Storage::disk('local')->assertExists(data_get($application->metadata, 'annex.work_content_summary.attachment_path'));
        $this->assertSame('Jordanian Lead Test Actor', data_get($application->metadata, 'annex.cast_crew.0.name'));
        $this->assertSame('male', data_get($application->metadata, 'annex.cast_crew.0.gender'));
        $this->assertSame('1990-03-15', data_get($application->metadata, 'annex.cast_crew.0.birth_date'));
        $this->assertNull(data_get($application->metadata, 'annex.cast_crew.0.passport_image_path'));
        $this->assertSame('International Supporting Actor', data_get($application->metadata, 'annex.cast_crew.1.name'));
        $this->assertSame('international-actor-passport.png', data_get($application->metadata, 'annex.cast_crew.1.passport_image_name'));
        Storage::disk('local')->assertExists(data_get($application->metadata, 'annex.cast_crew.1.passport_image_path'));
        $this->assertCount(1, Storage::disk('local')->allFiles('application-annex/cast-crew-passports'));
        $this->assertSame('Downtown Amman', data_get($application->metadata, 'annex.filming_locations.0.location_name'));
        $this->assertSame('GPS pin 31.9,35.9', data_get($application->metadata, 'annex.filming_locations.0.address'));
        $this->assertSame(['road_closures', 'police_presence'], data_get($application->metadata, 'annex.filming_locations.0.special_requirements'));
        $this->assertSame('Military standby', data_get($application->metadata, 'annex.filming_locations.0.support_requirements.1.requirement'));
        $this->assertSame('Road lockup from 6 AM', data_get($application->metadata, 'annex.special_location_requirements.road_closures.notes'));
        $this->assertSame(['Downtown Amman'], data_get($application->metadata, 'annex.special_location_requirements.police_presence.locations'));
        $this->assertSame('Equipment Handler', data_get($application->metadata, 'annex.equipment_travelers.0.traveler_name'));
        $this->assertSame('equipment-handler-passport.jpg', data_get($application->metadata, 'annex.equipment_travelers.0.passport_image_name'));
        Storage::disk('local')->assertExists(data_get($application->metadata, 'annex.equipment_travelers.0.passport_image_path'));
        $this->assertSame('Jordan Freight', data_get($application->metadata, 'annex.imported_equipment.0.shipping_company_name'));
        $this->assertSame('INV-7788', data_get($application->metadata, 'annex.imported_equipment.0.invoice_number'));
        $this->assertNull(data_get($application->metadata, 'annex.imported_equipment.0.bill_of_lading_number'));
        $this->assertNull(data_get($application->metadata, 'annex.imported_equipment.0.departure_date'));
        $this->assertSame('Camera crane', data_get($application->metadata, 'annex.imported_equipment.1.item'));
        $this->assertSame('RJ102', data_get($application->metadata, 'annex.imported_equipment.1.flight_reference'));
        $this->assertEquals(27000, data_get($application->metadata, 'annex.imported_equipment.1.total_value'));
        $this->assertArrayNotHasKey('shipping_method', data_get($application->metadata, 'annex.imported_equipment.1'));
        $this->assertNull(data_get($application->metadata, 'annex.military_border_locations'));
        $this->assertNull(data_get($application->metadata, 'annex.military_border_equipment'));
        $this->assertSame('Traffic escort', data_get($application->metadata, 'annex.public_security_support.0.requirement'));
        $this->assertSame('Military standby', data_get($application->metadata, 'annex.military_support.0.requirement'));
        $this->assertSame('Queen Alia International Airport', data_get($application->metadata, 'annex.airport_filming.airport_name'));
        $this->assertSame('Airport Crew Member', data_get($application->metadata, 'annex.airport_people.0.full_name'));
        $this->assertSame('jordanian', data_get($application->metadata, 'annex.airport_people.0.nationality'));
        $this->assertTrue(data_get($application->metadata, 'annex.governmental_scenes_confirmed'));

        $internationalUser = User::query()->where('email', 'liaison.global@example.com')->firstOrFail();

        $this->assertTrue($internationalUser->requiresPasswordSetup());
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
            ->assertSee('ProductionTerms', false)
            ->assertSeeText(__('app.applications.production_terms.legal_document'))
            ->assertDontSee('EquipmentMilitaryBorder', false)
            ->assertDontSee('PublicSecuritySupport', false)
            ->assertDontSee('MilitarySupport', false)
            ->assertSeeText('Attached Annexes:')
            ->assertSeeText('Add annex')
            ->assertSee('WorkContentSummaryView', false)
            ->assertDontSee('EquipmentMilitaryBorderView', false)
            ->assertDontSee('PublicSecuritySupportView', false)
            ->assertDontSee('MilitarySupportView', false)
            ->assertSeeText('View form')
            ->assertDontSeeText('Uploaded files')
            ->assertSeeText('Jordanian Lead')
            ->assertSeeText('international-actor-passport.png')
            ->assertSeeText('Downtown Amman')
            ->assertSee('attached-location-list', false)
            ->assertSee('attached-location-card', false)
            ->assertSee('attached-support-item', false)
            ->assertSeeText('GPS pin 31.9,35.9')
            ->assertSeeText('Traffic escort')
            ->assertSeeText('Military escort')
            ->assertSeeText('equipment-handler-passport.jpg')
            ->assertSeeText('Queen Alia International Airport');

        $foreignProducer = User::query()->findOrFail($application->foreignProducerUserId());

        $foreignProducer->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $this
            ->actingAs($foreignProducer)
            ->post(route('profile.foreign-producer.applications.declaration.store', $application), [
                'declaration_accepted' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($applicant)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();
        $this->routeApplicationToAuthorities($admin, $application);

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Jordan Freight')
            ->assertSeeText('INV-7788')
            ->assertSeeText('Camera crane')
            ->assertSeeText('Equipment Handler')
            ->assertSeeText('international-actor-passport.png')
            ->assertSeeText('Traffic escort')
            ->assertSeeText('Military escort')
            ->assertSeeText('Airport Crew Member')
            ->assertSeeText('Municipal archive');

        $this->actingAs($authorityUser)
            ->get(route('authority.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Downtown Amman')
            ->assertSeeText('Traffic escort');
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
        $this->travelTo(Carbon::parse('2026-04-01 09:00:00'));
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload());

        $application = Application::query()->firstOrFail();
        $originalWorkContentSummary = data_get($application->metadata, 'annex.work_content_summary.synopsis');
        $this->assertTrue(data_get($application->metadata, 'annex.production_terms.accepted'));

        $this
            ->actingAs($applicant)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('submitted', $application->status);

        $showResponse = $this->actingAs($applicant)->get(route('applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSee('data-annex-add-button', false);

        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-annex-add-button[^>]*disabled/s', $showResponse->getContent());

        $updatedWorkContentSummary = $this->arabicWorkContentSummary(520);
        $crewLookup = Mockery::mock(IndividualPersonalInfoLookupService::class);
        $crewLookup->shouldReceive('lookup')
            ->once()
            ->with('1234567890', 'jordanian')
            ->andReturn(['ok' => false, 'error' => 'HTTP_ERROR']);
        $crewVerification = (new CrewIdentityVerificationService($crewLookup))->lookup(
            '1234567890',
            'jordanian',
            $applicant->getKey(),
        );

        $response = $this->actingAs($applicant)->post(route('applications.annex.update', $application), [
            'work_content_summary_synopsis' => $updatedWorkContentSummary,
            'work_content_summary_confirmed' => '1',
            'cast_crew' => [
                [
                    'name' => 'New Annex Test Actor',
                    'first_name' => 'New',
                    'second_name' => 'Annex',
                    'third_name' => 'Test',
                    'family_name' => 'Actor',
                    'role' => 'Lead',
                    'nationality' => 'jordanian',
                    'gender' => 'female',
                    'birth_date' => '1995-08-20',
                    'identity_number' => '1234567890',
                    'identity_verification_status' => $crewVerification['status'],
                    'identity_verification_source' => $crewVerification['source'],
                    'identity_verified_at' => $crewVerification['verified_at'],
                    'identity_verification_category' => 'jordanian',
                    'verification_token' => $crewVerification['proof'],
                ],
            ],
            'safety_guidelines_acknowledged' => '1',
            'airport_filming_airport_name' => 'Queen Alia International Airport',
            'airport_filming_area' => 'Departures hall',
            'airport_filming_date' => '2026-05-06',
            'airport_filming_crew_count' => 1,
            'airport_people' => [
                ['full_name' => 'Airport Access Lead', 'first_name' => 'Airport', 'second_name' => 'Access', 'third_name' => 'Test', 'family_name' => 'Lead', 'nationality' => 'jordanian', 'mother_name' => 'Mariam', 'identity_number' => '9876543210', 'profession' => 'Producer', 'address_phone' => 'Amman 0790000000', 'entry_reason' => 'Filming', 'target_area' => 'Departures hall'],
            ],
            'filming_locations' => [
                [
                    'governorate' => 'maan',
                    'location_name' => 'Wadi Rum Reserve',
                    'address' => 'Wadi Rum',
                    'nature' => 'Protected reserve landscape',
                    'location_type' => 'reserves',
                    'start_date' => '2026-05-01',
                    'end_date' => '2026-05-10',
                    'support_requirements' => [
                        ['authority' => 'public_security', 'date' => '2026-05-06', 'time_from' => '07:30', 'time_to' => '09:30', 'requirement' => 'Patrol support', 'notes' => 'Applicant annex update'],
                        ['authority' => 'military', 'date' => '2026-05-06', 'time_from' => '10:00', 'time_to' => '11:00', 'requirement' => 'Reserve liaison', 'notes' => 'Second support row for same location'],
                    ],
                ],
                [
                    'governorate' => 'amman',
                    'location_name' => 'Training zone',
                    'address' => 'Training zone',
                    'nature' => 'Controlled training area',
                    'location_type' => 'public_locations',
                    'start_date' => '2026-05-07',
                    'end_date' => '2026-05-07',
                    'support_requirements' => [
                        ['authority' => 'military', 'date' => '2026-05-07', 'time_from' => '10:00', 'time_to' => '13:00', 'requirement' => 'Army liaison', 'notes' => 'Applicant annex update'],
                    ],
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame($originalWorkContentSummary, data_get($application->metadata, 'annex.work_content_summary.synopsis'));
        $this->assertNull(data_get($application->metadata, 'annex.cast_crew.0.name'));
        $this->assertSame('Wadi Rum Reserve', data_get($application->metadata, 'annex.filming_locations.0.location_name'));

        $submission = ApplicationAnnexSubmission::query()->firstOrFail();

        $this->assertSame($application->getKey(), $submission->application_id);
        $this->assertSame(ApplicationAnnexSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame($updatedWorkContentSummary, data_get($submission->payload, 'work_content_summary.synopsis'));
        $this->assertTrue(data_get($submission->payload, 'work_content_summary.confirmed'));
        $this->assertSame('New Annex Test Actor', data_get($submission->payload, 'cast_crew.0.name'));
        $this->assertSame('female', data_get($submission->payload, 'cast_crew.0.gender'));
        $this->assertSame('1995-08-20', data_get($submission->payload, 'cast_crew.0.birth_date'));
        $this->assertSame('Queen Alia International Airport', data_get($submission->payload, 'airport_filming.airport_name'));
        $this->assertSame('Airport Access Lead', data_get($submission->payload, 'airport_people.0.full_name'));
        $this->assertSame('Patrol support', data_get($submission->payload, 'public_security_support.0.requirement'));
        $this->assertSame('Reserve liaison', data_get($submission->payload, 'military_support.0.requirement'));
        $this->assertSame('Wadi Rum Reserve', data_get($submission->previous_payload, 'filming_locations.0.location_name'));

        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'status' => 'submitted',
            'note' => 'An annex update was submitted by the applicant for RFC review.',
        ]);
        $this->assertNotNull(data_get($application->metadata, 'applicant_annex_submission.submitted_at'));
        $this->assertSame(ApplicationAnnexSubmission::STATUS_SUBMITTED, data_get($application->metadata, 'applicant_annex_submission.status'));
        $this->assertSame($applicant->getKey(), data_get($application->metadata, 'applicant_annex_submission.submitted_by_user_id'));

        $applicantPendingResponse = $this->actingAs($applicant)
            ->get(route('applications.show', $application))
            ->assertOk();

        $applicantPendingResponse
            ->assertSee('applicant-annex-table', false)
            ->assertSeeText('Annex under RFC review')
            ->assertSee('WorkContentSummary', false)
            ->assertSeeText('Annex 1')
            ->assertSeeText('تصوير تصوير تصوير')
            ->assertSeeText('New Annex Test Actor')
            ->assertSeeText('Patrol support')
            ->assertSeeText('Airport Access Lead');

        $this->assertMatchesRegularExpression('/<button[^>]*data-annex-add-button[^>]*disabled/s', $applicantPendingResponse->getContent());

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('href="#profile-Annex"', false)
            ->assertSeeText('Attached Annexes:')
            ->assertSeeText('Annex awaiting RFC review')
            ->assertSeeText('تصوير تصوير تصوير')
            ->assertSeeText('New Annex Test Actor')
            ->assertSeeText('Patrol support');

        $this->actingAs($admin)->post(route('admin.applications.annex-submissions.review', [$application, $submission]), [
            'decision' => ApplicationAnnexSubmission::STATUS_APPROVED,
        ])->assertRedirect(route('admin.applications.show', $application));

        $application->refresh();
        $submission->refresh();

        $this->assertSame(ApplicationAnnexSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame($updatedWorkContentSummary, data_get($application->metadata, 'annex.work_content_summary.synopsis'));
        $this->assertTrue(data_get($application->metadata, 'annex.work_content_summary.confirmed'));
        $this->assertSame('New Annex Test Actor', data_get($application->metadata, 'annex.cast_crew.0.name'));
        $this->assertSame('Queen Alia International Airport', data_get($application->metadata, 'annex.airport_filming.airport_name'));
        $this->assertSame('Patrol support', data_get($application->metadata, 'annex.public_security_support.0.requirement'));
        $this->assertSame('Reserve liaison', data_get($application->metadata, 'annex.military_support.0.requirement'));
        $this->assertSame(ApplicationAnnexSubmission::STATUS_APPROVED, data_get($application->metadata, 'applicant_annex_submission.status'));
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
                    'director_email' => 'director-review@example.com',
                    'director_profile_url' => 'https://example.com/director-review',
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
            ->assertSee('data-open-applicant-correspondence', false)
            ->assertSee('id="applicant-correspondence-section"', false)
            ->assertSee('streamit-wraper-table', false);

        $adminShowResponse = $this->actingAs($admin)->get(route('admin.applications.show', $application));

        $adminShowResponse
            ->assertOk()
            ->assertSeeText('Waiting for applicant clarification')
            ->assertSeeText('The applicant needs to provide clarification on this production request.')
            ->assertSeeText('Open review');
    }

    public function test_admin_can_assign_reviewer_but_cannot_change_authority_decision(): void
    {
        Storage::fake('local');

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

        $approval = Application::query()->firstOrFail()->authorityApprovals()->firstOrFail();

        $this->assertFalse(Route::has('admin.applications.approvals.update'));

        $updateResponse = $this->actingAs($admin)->post("/en/admin/applications/{$application->getKey()}/approvals/{$approval->getKey()}/update", [
            'status' => 'approved',
            'note' => 'Airport approval issued.',
        ]);

        $updateResponse->assertNotFound();

        $this->assertDatabaseHas('application_authority_approvals', [
            'id' => $approval->getKey(),
            'status' => 'pending',
            'note' => null,
            'reviewed_by_user_id' => null,
        ]);

        $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
            'status' => 'approved',
            'note' => 'Airport approval issued.',
            'response_attachment' => UploadedFile::fake()->create('authority-book.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('authority.applications.show', $application));

        $this->assertDatabaseHas('application_authority_approvals', [
            'id' => $approval->getKey(),
            'status' => 'approved',
            'note' => 'Airport approval issued.',
            'reviewed_by_user_id' => $authorityUser->getKey(),
        ]);

        $approval->refresh();
        Storage::disk('local')->assertExists($approval->response_attachment_path);

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee(route('admin.applications.approvals.attachment.download', [$application, $approval]), false)
            ->assertDontSee("/admin/applications/{$application->getKey()}/approvals/{$approval->getKey()}/update", false);

        $this->assertDatabaseHas('applications', [
            'id' => $application->getKey(),
            'current_stage' => 'final_decision',
        ]);
        $this->assertTrue($user->fresh()->unreadNotifications->contains(
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

        $documentPath = ApplicationDocument::query()->value('file_path');

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

        $documentId = ApplicationDocument::query()->value('id');

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
            'recipient_type' => 'authority',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Official books prepared by')
            ->assertSeeText($admin->displayName());
        $this->assertDatabaseHas('application_official_letters', [
            'application_id' => $application->getKey(),
            'application_authority_approval_id' => null,
            'target_entity_id' => $environmentApproval->entity_id,
            'recipient_type' => 'authority',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('application_official_letters', [
            'application_id' => $application->getKey(),
            'application_authority_approval_id' => null,
            'recipient_type' => 'applicant',
            'serial_number' => $application->code.'-RFC-FAC-01',
            'status' => 'draft',
        ]);
        $this->assertSame(3, $application->officialLetters()->count());

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
                && data_get($notification->data, 'notification_highlight_summary') === 'Filming facilitation letter: Filming facilitation letter for request '.$application->code
        ));
        $this->assertFalse($applicant->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'official_letter_issued'
                && data_get($notification->data, 'notification_highlight_summary') === 'Official book: Updated facilitation book'
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
            ->assertSee('data-admin-home-link', false)
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
            ->assertSee('data-authority-request-section="project-information"', false)
            ->assertSee('data-authority-request-section="director-information"', false)
            ->assertSee('data-authority-request-section="work-summary"', false)
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
            ->assertSeeText('Start or continue review')
            ->assertSeeText('Approve request')
            ->assertSeeText('Reject request')
            ->assertSeeText('Entity book')
            ->assertSeeText('You may attach your authority\'s official letter or response when available.')
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

        $this->assertSame(
            3,
            substr_count($showResponse->getContent(), 'data-authority-request-section='),
            'The authority request tab must expose only project information, director information, and the work summary.'
        );

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

        $missingRecipientResponse = $this->actingAs($authorityUser)->post(route('authority.applications.correspondence.store', $application), [
            'subject' => 'Missing Recipient',
            'message' => 'This message must not be stored without a recipient.',
        ]);

        $missingRecipientResponse->assertSessionHasErrors('recipient_type');

        $adminCorrespondenceCount = $admin->fresh()->unreadNotifications
            ->where('data.type_key', 'application_correspondence')
            ->count();
        $applicantCorrespondenceCount = $applicant->fresh()->unreadNotifications
            ->where('data.type_key', 'application_correspondence')
            ->count();

        $correspondenceResponse = $this->actingAs($authorityUser)->post(route('authority.applications.correspondence.store', $application), [
            'recipient_type' => ApplicationCorrespondence::RECIPIENT_RFC,
            'subject' => 'RFC-only Authority Note',
            'message' => 'Security authority has approved the request.',
            'attachment' => UploadedFile::fake()->create('rfc-authority-letter.pdf', 50, 'application/pdf'),
        ]);

        $correspondenceResponse->assertRedirect(route('authority.applications.show', $application));

        $this->assertDatabaseHas('application_correspondences', [
            'application_id' => $application->getKey(),
            'sender_type' => 'authority',
            'sender_name' => $authorityEntity->displayName('en'),
            'recipient_type' => ApplicationCorrespondence::RECIPIENT_RFC,
            'subject' => 'RFC-only Authority Note',
        ]);
        $this->assertSame(
            $adminCorrespondenceCount + 1,
            $admin->fresh()->unreadNotifications->where('data.type_key', 'application_correspondence')->count(),
        );
        $this->assertSame(
            $applicantCorrespondenceCount,
            $applicant->fresh()->unreadNotifications->where('data.type_key', 'application_correspondence')->count(),
        );

        $applicantOnlyCorrespondenceResponse = $this->actingAs($authorityUser)->post(route('authority.applications.correspondence.store', $application), [
            'recipient_type' => ApplicationCorrespondence::RECIPIENT_APPLICANT,
            'subject' => 'Applicant-only Authority Note',
            'message' => 'Please review the attached authority instructions.',
            'attachment' => UploadedFile::fake()->create('applicant-authority-letter.pdf', 50, 'application/pdf'),
        ]);

        $applicantOnlyCorrespondenceResponse->assertSessionHasErrors('recipient_type');

        $this->assertDatabaseMissing('application_correspondences', [
            'application_id' => $application->getKey(),
            'sender_type' => 'authority',
            'recipient_type' => ApplicationCorrespondence::RECIPIENT_APPLICANT,
            'subject' => 'Applicant-only Authority Note',
        ]);
        $this->assertSame(
            $adminCorrespondenceCount + 1,
            $admin->fresh()->unreadNotifications->where('data.type_key', 'application_correspondence')->count(),
        );
        $this->assertSame(
            $applicantCorrespondenceCount,
            $applicant->fresh()->unreadNotifications->where('data.type_key', 'application_correspondence')->count(),
        );

        $sharedCorrespondenceResponse = $this->actingAs($authorityUser)->post(route('authority.applications.correspondence.store', $application), [
            'recipient_type' => ApplicationCorrespondence::RECIPIENT_ALL,
            'subject' => 'Shared Authority Note',
            'message' => 'This authority message is addressed to the RFC and the applicant.',
            'attachment' => UploadedFile::fake()->create('shared-authority-letter.pdf', 50, 'application/pdf'),
        ]);

        $sharedCorrespondenceResponse->assertRedirect(route('authority.applications.show', $application));

        $this->assertDatabaseHas('application_correspondences', [
            'application_id' => $application->getKey(),
            'sender_type' => 'authority',
            'recipient_type' => ApplicationCorrespondence::RECIPIENT_ALL,
            'subject' => 'Shared Authority Note',
        ]);
        $this->assertSame(
            $adminCorrespondenceCount + 2,
            $admin->fresh()->unreadNotifications->where('data.type_key', 'application_correspondence')->count(),
        );
        $this->assertSame(
            $applicantCorrespondenceCount + 1,
            $applicant->fresh()->unreadNotifications->where('data.type_key', 'application_correspondence')->count(),
        );

        $rfcMessage = ApplicationCorrespondence::query()->where('subject', 'RFC-only Authority Note')->firstOrFail();
        $sharedMessage = ApplicationCorrespondence::query()->where('subject', 'Shared Authority Note')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeText('RFC-only Authority Note')
            ->assertSeeText('Shared Authority Note');

        $this->actingAs($applicant)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSeeText('Shared Authority Note')
            ->assertDontSeeText('RFC-only Authority Note');

        $this->actingAs($admin)
            ->get(route('admin.applications.correspondence.download', [$application, $sharedMessage]))
            ->assertOk();

        $this->actingAs($applicant)
            ->get(route('applications.correspondence.download', [$application, $sharedMessage]))
            ->assertOk();

        $this->actingAs($applicant)
            ->get(route('applications.correspondence.download', [$application, $rfcMessage]))
            ->assertNotFound();

        $this->actingAs($authorityUser)
            ->get(route('authority.applications.show', $application))
            ->assertOk()
            ->assertSee('data-correspondence-recipient-selector', false)
            ->assertDontSee('authority_correspondence_recipient_applicant', false)
            ->assertSeeText('Royal Film Commission (RFC)')
            ->assertSeeText('RFC and applicant');
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
                'classification' => 'camera_equipment',
                'shipping_method' => 'luggage',
                'entry_point' => 'queen_alia_international_airport',
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
            ->assertSeeText('Travelers list')
            ->assertSeeText('Equipment to be brought from abroad')
            ->assertSeeText('Camera Package')
            ->assertSeeText('Traveler One')
            ->assertSee('data-authority-annex-sections="equipment_travelers,imported_equipment"', false)
            ->assertDontSee('authority-documents-table', false);
    }

    public function test_authority_can_request_structured_changes_and_only_its_review_reopens_after_resubmission(): void
    {
        Storage::fake('local');

        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant] = $this->createApplicantContext();
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security', 'environment'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $requestingApproval = ApplicationAuthorityApproval::query()
            ->where('application_id', $application->getKey())
            ->where('authority_code', 'public_security')
            ->firstOrFail();
        $completedApproval = ApplicationAuthorityApproval::query()
            ->where('application_id', $application->getKey())
            ->where('authority_code', 'environment')
            ->firstOrFail();

        $completedApproval->forceFill([
            'status' => 'approved',
            'note' => 'Environment approval remains final.',
            'decided_at' => now(),
        ])->save();

        $changeResponse = $this->actingAs($authorityUser)->post(
            route('authority.applications.approval.update', $application),
            [
                'status' => 'changes_requested',
                'note' => 'Please correct the filming location information.',
                'change_requests' => [
                    [
                        'section_key' => 'filming_locations',
                        'details' => 'Add the complete street address and filming dates.',
                        'attachment' => UploadedFile::fake()->create('location-guidance.pdf', 40, 'application/pdf'),
                    ],
                    [
                        'section_key' => 'cast_crew',
                        'details' => 'Correct the national number for the second crew member.',
                    ],
                ],
            ],
        );

        $changeResponse->assertRedirect(route('authority.applications.show', $application));

        $requestingApproval->refresh();
        $application->refresh();

        $this->assertSame('changes_requested', $requestingApproval->status);
        $this->assertSame('needs_clarification', $application->status);
        $this->assertSame('clarification', $application->current_stage);
        $this->assertSame('approved', $completedApproval->fresh()->status);
        $this->assertDatabaseCount('application_authority_change_requests', 2);
        $this->assertDatabaseHas('application_authority_change_requests', [
            'application_authority_approval_id' => $requestingApproval->getKey(),
            'section_key' => 'filming_locations',
            'status' => ApplicationAuthorityChangeRequest::STATUS_REQUESTED,
            'requested_by_user_id' => $authorityUser->getKey(),
        ]);

        $storedAttachment = $requestingApproval->changeRequests()
            ->where('section_key', 'filming_locations')
            ->firstOrFail();
        Storage::disk('local')->assertExists($storedAttachment->attachment_path);

        $this->actingAs($authorityUser)
            ->get(route('authority.applications.show', $application))
            ->assertOk()
            ->assertSeeText('Changes requested from the applicant')
            ->assertSeeText('Add the complete street address and filming dates.')
            ->assertDontSee('class="authority-decision-panel" enctype="multipart/form-data" data-authority-decision-form', false);

        $this->actingAs($applicant)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSeeText('Requested application corrections')
            ->assertSeeText('Filming locations list')
            ->assertSeeText('Correct the national number for the second crew member.');

        $adminOverrideResponse = $this->actingAs($admin)->post(
            "/en/admin/applications/{$application->getKey()}/approvals/{$requestingApproval->getKey()}/update",
            [
                'status' => 'approved',
                'note' => 'This shortcut must remain blocked.',
            ],
        );

        $adminOverrideResponse->assertNotFound();
        $this->assertSame('changes_requested', $requestingApproval->fresh()->status);

        $this->actingAs($applicant)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();
        $requestingApproval->refresh();

        $this->assertSame('under_review', $application->status);
        $this->assertSame('authority_review', $application->current_stage);
        $this->assertSame('pending', $requestingApproval->status);
        $this->assertSame('approved', $completedApproval->fresh()->status);
        $this->assertSame(
            2,
            $requestingApproval->changeRequests()
                ->where('status', ApplicationAuthorityChangeRequest::STATUS_RESUBMITTED)
                ->count(),
        );
        $this->assertTrue($authorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_changes_resubmitted'
        ));

        $this->actingAs($authorityUser)
            ->get(route('authority.applications.show', $application))
            ->assertOk()
            ->assertSeeText('The applicant resubmitted these corrections for this authority to review.')
            ->assertSee('class="authority-decision-panel" enctype="multipart/form-data" data-authority-decision-form', false);
    }

    public function test_authority_can_approve_without_uploading_a_response_book(): void
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

        $this->actingAs($authorityUser)
            ->get(route('authority.applications.show', $application))
            ->assertOk()
            ->assertSeeText('You may attach your authority\'s official letter or response when available.');

        $approveResponse = $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
            'status' => 'approved',
            'note' => 'Approved without an attached book.',
        ]);

        $approveResponse
            ->assertRedirect(route('authority.applications.show', $application))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('application_authority_approvals', [
            'id' => $approval->getKey(),
            'status' => 'approved',
            'note' => 'Approved without an attached book.',
            'response_attachment_path' => null,
        ]);

        $approval->refresh();

        $this->assertSame('approved', $approval->status);
        $this->assertNull($approval->response_attachment_name);
        $this->assertNull($approval->response_attachment_uploaded_at);
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
            ->assertSee('data-authority-home-link', false)
            ->assertSeeText('Home')
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

    public function test_authority_sla_warning_notifies_rfc_before_deadline_without_escalating(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $now = Carbon::parse('2026-07-04 10:00:00');
        Carbon::setTestNow($now);

        try {
            $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
            $rfcEntity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();
            [$user, $entity] = $this->createApplicantContext();
            [$authorityUser, $authorityEntity] = $this->createAuthorityContext([
                'name' => 'SLA Warning Authority Owner',
                'username' => 'sla-warning-authority-owner',
                'email' => 'sla-warning-authority-owner@example.com',
            ]);

            $rfcAdmin = User::query()->create([
                'name' => 'RFC SLA Warning Admin',
                'username' => 'rfc_sla_warning_admin',
                'email' => 'rfc-sla-warning-admin@example.com',
                'phone' => '0793111333',
                'status' => 'active',
                'password' => Hash::make('Password@123'),
            ]);

            $rfcAdmin->entities()->attach($rfcEntity->getKey(), [
                'is_primary' => true,
                'status' => 'active',
                'joined_at' => $now,
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
                'code' => 'REQ-50005',
                'entity_id' => $entity->getKey(),
                'submitted_by_user_id' => $user->getKey(),
                'project_name' => 'Due Soon Authority Project',
                'project_nationality' => 'jordanian',
                'work_category' => 'feature_film',
                'release_method' => 'cinema',
                'planned_start_date' => '2026-10-01',
                'planned_end_date' => '2026-10-12',
                'project_summary' => 'Close to the authority deadline.',
                'status' => 'submitted',
                'submitted_at' => $now->copy()->subHours(36),
            ]);

            $approval = $application->authorityApprovals()->create([
                'authority_code' => 'public_security',
                'entity_id' => $authorityEntity->getKey(),
                'assigned_user_id' => $authorityUser->getKey(),
                'assigned_at' => $now->copy()->subHours(36),
                'status' => 'pending',
            ]);

            $approval->forceFill([
                'created_at' => $now->copy()->subHours(36),
                'updated_at' => $now->copy()->subHours(36),
            ])->saveQuietly();

            $signal = app(AuthorityEscalationService::class)->signalForApproval($approval->fresh(), $now);

            $this->assertTrue($signal['is_due_soon']);
            $this->assertFalse($signal['is_overdue']);

            Artisan::call('authority-approvals:check-escalations');

            $approval->refresh();

            $this->assertNotNull($approval->sla_warning_notified_at);
            $this->assertNull($approval->escalated_at);
            $this->assertTrue($admin->fresh()->notifications->contains(
                fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_sla_warning'
            ));
            $this->assertTrue($rfcAdmin->fresh()->notifications->contains(
                fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_sla_warning'
            ));
            $this->assertFalse($authorityUser->fresh()->notifications->contains(
                fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_sla_warning'
            ));

            Artisan::call('authority-approvals:check-escalations');

            $this->assertSame(1, $admin->fresh()->notifications
                ->filter(fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_sla_warning')
                ->count());

            $reportResponse = $this->actingAs($admin)->get(route('admin.authority-escalations.report', [
                'window' => '30',
                'authority' => $authorityEntity->getKey(),
            ]));

            $reportResponse
                ->assertOk()
                ->assertSeeText('Approaching response deadlines')
                ->assertSeeText('Due Soon Authority Project')
                ->assertSeeText('Due soon approvals');

            $indexResponse = $this->actingAs($admin)->get(route('admin.applications.index'));

            $indexResponse
                ->assertOk()
                ->assertSeeText('Requests with authorities due soon')
                ->assertSeeText('Due Soon Authority Project')
                ->assertSeeText('Due soon:');
        } finally {
            Carbon::setTestNow();
        }
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
        $filmingDateRange = optional($application->planned_start_date)->toDateString()
            .' - '
            .optional($application->planned_end_date)->toDateString();

        $response = $this->actingAs($applicant)->get(route('applications.show', $application));

        $response
            ->assertOk()
            ->assertSeeText('Project Information')
            ->assertSeeText('Applicant Studio')
            ->assertSeeText('Amman, Jordan')
            ->assertSeeText('0793333111')
            ->assertSeeText('studio@applicant.test')
            ->assertDontSeeText('Open profile')
            ->assertDontSeeText('Producer Information')
            ->assertSeeText('Implementation schedule')
            ->assertSeeText('Pre-production')
            ->assertSeeText('2026-04-20 - 2026-04-30')
            ->assertSeeText('Filming')
            ->assertSeeText($filmingDateRange)
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
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security', 'environment'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $approval = $application->fresh()->authorityApprovals()->where('authority_code', 'public_security')->firstOrFail();

        $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
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
            ->assertSee('data-portal-home-link', false)
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

        ScoutingRequest::query()->create([
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

    public function test_international_producer_profile_only_lists_linked_requests(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$owner, $entity] = $this->createApplicantContext([], [
            'name_en' => 'Linked Foreign Studio',
            'name_ar' => 'Linked Foreign Studio',
            'registration_no' => 'ORG-FOREIGN-1',
        ]);
        $foreignProducer = $this->createInternationalProducerUser($entity);

        $linkedApplication = Application::query()->create([
            'code' => 'REQ-LINKED',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $owner->getKey(),
            'project_name' => 'Linked International Feature',
            'project_nationality' => 'international',
            'work_category' => 'feature_film',
            'release_method' => 'festival',
            'planned_start_date' => '2026-09-01',
            'planned_end_date' => '2026-09-12',
            'estimated_crew_count' => 14,
            'estimated_budget' => 64000,
            'project_summary' => 'Visible to the linked producer.',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
            'metadata' => [
                'international' => [
                    'account' => [
                        'user_id' => $foreignProducer->getKey(),
                        'email' => $foreignProducer->email,
                        'read_only' => true,
                    ],
                ],
            ],
        ]);

        $hiddenApplication = Application::query()->create([
            'code' => 'REQ-HIDDEN',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $owner->getKey(),
            'project_name' => 'Hidden Entity Feature',
            'project_nationality' => 'international',
            'work_category' => 'feature_film',
            'release_method' => 'festival',
            'planned_start_date' => '2026-10-01',
            'planned_end_date' => '2026-10-12',
            'estimated_crew_count' => 12,
            'estimated_budget' => 50000,
            'project_summary' => 'Must not leak to the foreign producer.',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
            'metadata' => [
                'international' => [
                    'account' => [
                        'user_id' => $owner->getKey(),
                        'email' => $owner->email,
                        'read_only' => true,
                    ],
                ],
            ],
        ]);

        $linkedScoutingRequest = ScoutingRequest::query()->create([
            'code' => 'SCOUT-LINKED',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $owner->getKey(),
            'project_name' => 'Linked Foreign Scout',
            'project_nationality' => 'international',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
            'metadata' => [
                'international' => [
                    'account' => [
                        'user_id' => $foreignProducer->getKey(),
                    ],
                ],
            ],
        ]);

        $hiddenScoutingRequest = ScoutingRequest::query()->create([
            'code' => 'SCOUT-HIDDEN',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $owner->getKey(),
            'project_name' => 'Hidden Foreign Scout',
            'project_nationality' => 'international',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
            'metadata' => [],
        ]);

        $this
            ->actingAs($foreignProducer)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.show', ['variant' => 'foreign_producer']));

        $response = $this->actingAs($foreignProducer)->get(route('profile.show', ['variant' => 'foreign_producer']));

        $response
            ->assertOk()
            ->assertSeeText('Linked International Feature')
            ->assertSeeText('Linked Foreign Scout')
            ->assertSeeText('Declaration pending')
            ->assertDontSeeText('Hidden Entity Feature')
            ->assertDontSeeText('Hidden Foreign Scout');

        $this
            ->actingAs($foreignProducer)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSeeText('Linked International Feature')
            ->assertDontSeeText('Hidden Entity Feature');

        $this
            ->actingAs($foreignProducer)
            ->get(route('applications.show', $linkedApplication))
            ->assertOk()
            ->assertSeeText('Linked International Feature')
            ->assertDontSee('href="'.route('applications.edit', $linkedApplication).'"', false)
            ->assertDontSeeText(__('app.applications.submit_action'));

        $this
            ->actingAs($foreignProducer)
            ->get(route('applications.edit', $linkedApplication))
            ->assertForbidden();

        $this
            ->actingAs($foreignProducer)
            ->get(route('applications.show', $hiddenApplication))
            ->assertForbidden();

        $this
            ->actingAs($foreignProducer)
            ->get(route('scouting-requests.index'))
            ->assertOk()
            ->assertSeeText('Linked Foreign Scout')
            ->assertDontSeeText('Hidden Foreign Scout');

        $this
            ->actingAs($foreignProducer)
            ->get(route('scouting-requests.show', $linkedScoutingRequest))
            ->assertOk()
            ->assertSeeText('Linked Foreign Scout');

        $this
            ->actingAs($foreignProducer)
            ->get(route('scouting-requests.show', $hiddenScoutingRequest))
            ->assertForbidden();

        $this->assertSame($foreignProducer->getKey(), data_get($linkedApplication->fresh()->metadata, 'international.account.user_id'));
    }

    public function test_international_producer_can_sign_linked_application_declaration(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$owner, $entity] = $this->createApplicantContext([], [
            'registration_no' => 'ORG-FOREIGN-2',
        ]);
        $foreignProducer = $this->createInternationalProducerUser($entity, [
            'email' => 'signer.foreign@example.com',
            'username' => 'signer-foreign-producer',
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-SIGN',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $owner->getKey(),
            'project_name' => 'Signature Required Feature',
            'project_nationality' => 'international',
            'work_category' => 'feature_film',
            'release_method' => 'festival',
            'planned_start_date' => '2026-11-01',
            'planned_end_date' => '2026-11-12',
            'estimated_crew_count' => 14,
            'estimated_budget' => 64000,
            'project_summary' => 'Needs foreign producer declaration.',
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
            'metadata' => [
                'international' => [
                    'account' => [
                        'user_id' => $foreignProducer->getKey(),
                        'email' => $foreignProducer->email,
                        'read_only' => true,
                    ],
                ],
            ],
        ]);

        $this
            ->actingAs($foreignProducer)
            ->withServerVariables(['REMOTE_ADDR' => '127.10.10.10'])
            ->post(route('profile.foreign-producer.applications.declaration.store', $application), [
                'declaration_accepted' => '1',
            ])
            ->assertRedirect(route('profile.show', ['variant' => 'foreign_producer']))
            ->assertSessionHas('status', __('app.profile.foreign_producer_declaration_saved'));

        $declaration = data_get($application->fresh()->metadata, 'international.account.declaration');

        $this->assertTrue(data_get($declaration, 'accepted'));
        $this->assertSame($foreignProducer->getKey(), data_get($declaration, 'signed_by_user_id'));
        $this->assertSame('signer.foreign@example.com', data_get($declaration, 'signed_by_email'));
        $this->assertNotEmpty(data_get($declaration, 'signed_at'));

        $this
            ->actingAs($owner)
            ->post(route('profile.foreign-producer.applications.declaration.store', $application), [
                'declaration_accepted' => '1',
            ])
            ->assertForbidden();
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
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $approval = $application->authorityApprovals()->firstOrFail();

        $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
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
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $approval = ApplicationAuthorityApproval::query()->where('application_id', $application->getKey())->firstOrFail();
        $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
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
        [$authorityUser] = $this->createAuthorityContext();

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.submit', $application));
        $this->routeApplicationToAuthorities($admin, $application);

        $approval = ApplicationAuthorityApproval::query()->where('application_id', $application->getKey())->firstOrFail();
        $this->actingAs($authorityUser)->post(route('authority.applications.approval.update', $application), [
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

    public function test_application_forms_can_be_printed_with_results_for_each_allowed_role(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant, $applicantEntity] = $this->createApplicantContext();
        [$authorityUser, $authorityEntity] = $this->createAuthorityContext();

        $application = Application::query()->create([
            'code' => 'REQ-PRINT-001',
            'entity_id' => $applicantEntity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Printable Forms Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-10',
            'estimated_crew_count' => 8,
            'status' => 'under_review',
            'current_stage' => 'authority_review',
            'metadata' => [
                'annex' => [
                    'work_content_summary' => [
                        'synopsis' => 'ملخص عربي معد لنسخة الطباعة',
                        'confirmed' => true,
                    ],
                    'cast_crew' => [[
                        'name' => 'Printable Crew Member',
                        'role' => 'Director',
                        'nationality' => 'jordanian',
                        'gender' => 'male',
                        'birth_date' => '1990-01-01',
                        'identity_number' => '1234567890',
                    ]],
                ],
            ],
        ]);

        $routingRule = ApprovalRoutingRule::query()->create([
            'name' => 'Printable cast and crew route',
            'request_type' => 'application',
            'approval_code' => 'public_security',
            'target_entity_id' => $authorityEntity->getKey(),
            'conditions' => ['annex_flags' => ['cast_crew']],
            'priority' => 10,
            'is_active' => true,
        ]);

        ApplicationAuthorityApproval::query()->create([
            'application_id' => $application->getKey(),
            'authority_code' => 'public_security',
            'entity_id' => $authorityEntity->getKey(),
            'approval_routing_rule_id' => $routingRule->getKey(),
            'status' => 'pending',
        ]);

        $this->actingAs($applicant)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee(route('applications.forms.print', $application), false)
            ->assertSee(route('applications.forms.print', $application).'?form=work_content_summary', false)
            ->assertSee(route('applications.forms.print', $application).'?form=cast_crew', false)
            ->assertSeeText('Print all forms');

        $applicantPrint = $this->actingAs($applicant)
            ->get(route('applications.forms.print', $application));

        $applicantPrint
            ->assertOk()
            ->assertSeeText('All application forms')
            ->assertSeeText('REQ-PRINT-001')
            ->assertSeeText('Printable Forms Project')
            ->assertSeeText('Printable Crew Member')
            ->assertSee('data-print-form="work_content_summary"', false)
            ->assertSee('data-print-form="cast_crew"', false)
            ->assertSeeText('Form completed')
            ->assertDontSeeText('Form not completed')
            ->assertDontSee('data-print-form="governmental_scenes"', false)
            ->assertSee('images/logo.svg', false)
            ->assertDontSee('images/logo-full.png', false);

        $this->actingAs($applicant)
            ->get(route('applications.forms.print', $application).'?form=cast_crew')
            ->assertOk()
            ->assertSeeText('Cast and crew list')
            ->assertSeeText('Printable Crew Member')
            ->assertSee('data-print-form="cast_crew"', false)
            ->assertDontSee('data-print-form="work_content_summary"', false)
            ->assertDontSee('data-print-form="governmental_scenes"', false);

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('data-bs-target="#WorkContentSummaryView"', false)
            ->assertSee('data-bs-target="#CastCrewListView"', false)
            ->assertDontSee('data-bs-target="#FilmingGovernmentalView"', false);

        $this->actingAs($admin)
            ->get(route('admin.applications.forms.print', $application))
            ->assertOk()
            ->assertSeeText('Printable Forms Project')
            ->assertSee('data-print-form="work_content_summary"', false)
            ->assertSee('data-print-form="cast_crew"', false)
            ->assertDontSee('data-print-form="governmental_scenes"', false);

        $authorityPrint = $this->actingAs($authorityUser)
            ->get(route('authority.applications.forms.print', $application));

        $authorityPrint
            ->assertOk()
            ->assertSeeText('Printable Crew Member')
            ->assertSee('data-print-form="cast_crew"', false)
            ->assertDontSee('data-print-form="work_content_summary"', false)
            ->assertDontSee('data-print-form="airport_filming"', false);
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
     * @param  array<string, mixed>  $overrides
     */
    private function createInternationalProducerUser(Entity $entity, array $overrides = []): User
    {
        $user = User::query()->create(array_merge([
            'name' => 'International Producer',
            'username' => 'international-producer',
            'email' => 'international.producer@example.com',
            'phone' => '0797777000',
            'status' => 'active',
            'registration_type' => 'international_producer',
            'password' => Hash::make('International@12345'),
        ], $overrides));

        $user->entities()->attach($entity->getKey(), [
            'job_title' => 'International Producer',
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->givePermissionTo('applications.view.entity');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user;
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

    private function arabicWorkContentSummary(int $words = 500): string
    {
        return implode(' ', array_fill(0, $words, 'تصوير'));
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
            'project_nationalities' => ['jordanian'],
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-05-02',
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
            'director_email' => 'director@example.com',
            'international_producer_name' => 'Global Partner',
            'international_producer_nationality' => 'non_jordanian',
            'international_producer_company' => 'Global Films',
            'filming_locations' => [[
                'governorate' => 'maan',
                'location_name' => 'Wadi Rum Reserve',
                'address' => 'Wadi Rum',
                'nature' => 'Protected reserve landscape',
                'location_type' => 'reserves',
                'start_date' => now()->addDays(30)->toDateString(),
                'end_date' => now()->addDays(39)->toDateString(),
            ]],
            'special_location_requirements' => [
                'road_closures' => [
                    'locations' => ['Wadi Rum Reserve'],
                    'notes' => 'Temporary road closure near the filming site.',
                ],
            ],
            'safety_guidelines_acknowledged' => '1',
            'production_terms_accepted' => '1',
            'work_content_summary_synopsis' => $this->arabicWorkContentSummary(),
            'work_content_summary_confirmed' => '1',
            'supporting_notes' => 'Need desert location and crowd management support.',
        ], $overrides);

        unset($payload['required_approvals']);

        return $payload;
    }
}
