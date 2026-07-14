<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use App\Notifications\RegistrationCompletionRequestedNotification;
use App\Services\StudentRegistrationLookupService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_control_reseeding_does_not_reset_existing_system_account_passwords(): void
    {
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $rfcOwner = User::query()->where('email', 'ref@ref.test')->firstOrFail();
        $admin->forceFill(['password' => Hash::make('ExistingAdminPassword!1')])->save();
        $rfcOwner->forceFill(['password' => Hash::make('ExistingRfcPassword!1')])->save();

        $this->seed(AccessControlSeeder::class);

        $this->assertTrue(Hash::check('ExistingAdminPassword!1', $admin->fresh()->password));
        $this->assertTrue(Hash::check('ExistingRfcPassword!1', $rfcOwner->fresh()->password));
    }

    public function test_student_registration_creates_user_entity_and_scoped_role(): void
    {
        $this->seed(AccessControlSeeder::class);

        $lookupResponse = $this->post(route('register.student.lookup'), [
            'national_id' => '9876543210',
            'birth_date' => '1999-01-15',
        ]);

        $lookupResponse->assertOk();
        $studentData = $lookupResponse->json('data');

        $response = $this->post(route('register.store'), [
            'registration_type' => 'student',
            'email' => 'ali@example.com',
            'national_id' => '9876543210',
            'birth_date' => '1999-01-15',
            'phone' => '0791234567',
            'address' => 'Student address, Amman',
            'student_lookup_verified' => '1',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been submitted for review. We will notify you by email and SMS once it is approved, rejected, or requires additional information.');

        $user = User::query()->where('email', 'ali@example.com')->firstOrFail();
        $entity = Entity::query()->where('national_id', '9876543210')->firstOrFail();

        $this->assertSame('student', $user->registration_type);
        $this->assertSame('student', $entity->registration_type);
        $this->assertSame('pending_review', $user->status);
        $this->assertSame('pending_review', $entity->status);
        $this->assertSame($studentData['full_name'], $user->name);
        $this->assertSame($studentData['full_name'], $entity->name_en);
        $this->assertSame($studentData['birth_date'], data_get($entity->metadata, 'birth_date'));
        $this->assertSame($studentData['gender'], data_get($entity->metadata, 'gender'));
        $this->assertSame($studentData['nationality'], data_get($entity->metadata, 'nationality'));
        $this->assertSame($studentData['university_name'], data_get($entity->metadata, 'university_name'));
        $this->assertSame($studentData['major'], data_get($entity->metadata, 'major'));
        $this->assertSame('Student address, Amman', data_get($entity->metadata, 'address'));

        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $entity->getKey(),
            'user_id' => $user->getKey(),
            'is_primary' => 1,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $this->assertTrue($user->hasRole('applicant_owner'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_student_registration_requires_verified_national_id_lookup(): void
    {
        $this->seed(AccessControlSeeder::class);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'registration_type' => 'student',
            'email' => 'unchecked@example.com',
            'national_id' => '9876543211',
            'birth_date' => '1999-01-16',
            'phone' => '0791234568',
            'address' => 'Unchecked student address',
            'student_lookup_verified' => '1',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('national_id');

        $this->assertDatabaseMissing('users', [
            'email' => 'unchecked@example.com',
        ]);
    }

    public function test_student_lookup_uses_mohe_sanad_when_gsb_is_configured(): void
    {
        $this->seed(AccessControlSeeder::class);

        Cache::forget('gsb:mohe_sanad:current:9876543213:1998-10-03');

        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.client_id', 'client-id');
        config()->set('services.gsb.client_secret', 'client-secret');
        config()->set('services.gsb.services.mohe_sanad.enabled', true);
        config()->set('services.gsb.services.mohe_sanad.base_url', 'https://api-gateway.stg.gsb.gov.jo:9443');
        config()->set('services.gsb.services.mohe_sanad.path', '/porg-g2g/g2g/newstandard/api/MoheStandard');

        Http::fake([
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/newstandard/api/MoheStandard' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => [
                    [
                        'STUDENT_NAME' => 'سجل تخرج سابق',
                        'BIRTH_DATE' => '03-OCT-98',
                        'INSTITUTE_NAME' => 'جامعة سابقة',
                        'major' => 'تخصص سابق',
                        'student_status' => 'خريج',
                    ],
                    [
                        'STUDENT_NAME' => 'طالب جامعي تجريبي',
                        'BIRTH_DATE' => '03-OCT-98',
                        'gender_desc' => 'ذكر',
                        'NATIONALITY' => 'أردني',
                        'INSTITUTE_NAME' => 'جامعة تجريبية',
                        'S_MAJOR_NAME' => 'هندسة برمجيات',
                        'degree' => 'البكالوريوس',
                        'student_status' => 'على مقاعد الدراسة',
                        'STUDENT_PHONE' => '799999999',
                        'STUDENT_ID' => 'TEST-2018-001',
                        'UNIVERSITY_TYPE_NAME' => 'خاصة',
                        'INSTITUTE_GOVERNORATE' => 'محافظة العاصمة',
                        'CITY_NAME' => 'عمان',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post(route('register.student.lookup'), [
            'national_id' => '9876543213',
            'birth_date' => '03/10/1998',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.full_name', 'طالب جامعي تجريبي')
            ->assertJsonPath('data.birth_date', '1998-10-03')
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.nationality', 'أردني')
            ->assertJsonPath('data.university_name', 'جامعة تجريبية')
            ->assertJsonPath('data.major', 'هندسة برمجيات')
            ->assertJsonPath('data.student_phone', '0799999999')
            ->assertJsonPath('data.student_id', 'TEST-2018-001');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/newstandard/api/MoheStandard'
            && $request['nationalNo'] === '9876543213'
            && $request['birthDate'] === '1998-10-03');
    }

    public function test_student_lookup_rejects_mohe_graduate_record(): void
    {
        $this->seed(AccessControlSeeder::class);

        Cache::forget('gsb:mohe_sanad:current:9876543214:1998-10-03');

        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.client_id', 'client-id');
        config()->set('services.gsb.client_secret', 'client-secret');
        config()->set('services.gsb.services.mohe_sanad.enabled', true);
        config()->set('services.gsb.services.mohe_sanad.base_url', 'https://api-gateway.stg.gsb.gov.jo:9443');
        config()->set('services.gsb.services.mohe_sanad.path', '/porg-g2g/g2g/newstandard/api/MoheStandard');

        Http::fake([
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/newstandard/api/MoheStandard' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => [[
                    'STUDENT_NAME' => 'خريج جامعي تجريبي',
                    'BIRTH_DATE' => '03-OCT-98',
                    'INSTITUTE_NAME' => 'جامعة تجريبية',
                    'major' => 'هندسة البرمجيات',
                    'student_status' => 'خريج',
                ]],
            ], 200),
        ]);

        $response = $this->post(route('register.student.lookup'), [
            'national_id' => '9876543214',
            'birth_date' => '03/10/1998',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error', 'NOT_CURRENT_STUDENT');

        $this->assertNull(session(StudentRegistrationLookupService::SESSION_KEY));

        Http::assertSent(fn ($request): bool => $request['birthDate'] === '1998-10-03');
    }

    public function test_pending_student_user_cannot_sign_in_before_admin_approval(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $this->post(route('register.student.lookup'), [
            'national_id' => '9876543212',
            'birth_date' => '1999-01-17',
        ])->assertOk();

        $this->post(route('register.store'), [
            'registration_type' => 'student',
            'email' => 'pending-student@example.com',
            'national_id' => '9876543212',
            'birth_date' => '1999-01-17',
            'phone' => '0791234569',
            'address' => 'Pending student address',
            'student_lookup_verified' => '1',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('login'));

        $response = $this->from(route('login'))->post(route('login.store'), [
            'identifier' => 'pending-student@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'identifier' => 'Your registration is pending admin approval. You can sign in after your account has been approved.',
            ]);

        $this->assertGuest();
    }

    public function test_company_registration_creates_entity_account_and_stores_uploaded_document(): void
    {
        Storage::fake('local');
        $this->seed(AccessControlSeeder::class);

        $lookupResponse = $this->post(route('register.company.lookup'), [
            'registration_number' => '2000011122',
        ]);

        $lookupResponse->assertOk();
        $companyData = $lookupResponse->json('data');

        $response = $this->post(route('register.store'), [
            'registration_type' => 'company',
            'registration_number' => '2000011122',
            'email' => 'info@futurefilms.test',
            'phone' => '0790000001',
            'address' => 'Amman, Jordan',
            'description' => 'Production house',
            'registration_document' => UploadedFile::fake()->create('license.pdf', 250, 'application/pdf'),
            'logo' => UploadedFile::fake()->image('company-logo.png')->size(100),
            'company_lookup_verified' => '1',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been submitted for review. We will notify you by email and SMS once it is approved, rejected, or requires additional information.');

        $entity = Entity::query()->where('registration_no', '2000011122')->firstOrFail();
        $user = User::query()->where('email', 'info@futurefilms.test')->firstOrFail();

        $this->assertSame('company', $entity->registration_type);
        $this->assertSame('company', $user->registration_type);
        $this->assertSame('pending_review', $entity->status);
        $this->assertSame('pending_review', $user->status);
        $this->assertSame($companyData['entity_name'], $entity->name_en);
        $this->assertSame($companyData['company_registration_date'], data_get($entity->metadata, 'company_registration_date'));
        $this->assertSame($companyData['company_capital'], data_get($entity->metadata, 'company_capital'));
        $this->assertSame('Amman, Jordan', data_get($entity->metadata, 'address'));
        $this->assertSame('Production house', data_get($entity->metadata, 'description'));
        Storage::disk('local')->assertExists((string) data_get($entity->metadata, 'registration_document_path'));
        Storage::disk('local')->assertExists((string) data_get($entity->metadata, 'logo_path'));
        $this->assertSame('company-logo.png', data_get($entity->metadata, 'logo_name'));
        $this->assertSame('image/png', data_get($entity->metadata, 'logo_mime'));
    }

    public function test_registration_page_exposes_registration_address_fields(): void
    {
        $this->refreshApplicationWithLocale('en');

        $response = $this->get(route('register'));

        $response
            ->assertOk()
            ->assertSeeText('Student address')
            ->assertSee('id="student-address"', false)
            ->assertSee('data-student-birth-date', false)
            ->assertSeeText('Company address')
            ->assertSee('id="company-address"', false)
            ->assertSee('autocomplete="street-address"', false);
    }

    public function test_company_registration_page_accepts_one_to_ten_digit_national_ids(): void
    {
        $this->refreshApplicationWithLocale('en');

        $response = $this->get(route('register'));

        $response->assertOk();

        $content = $response->getContent();
        $companyLookupScript = strstr($content, 'const setupCompanyLookup = function () {');

        $this->assertStringContainsString('pattern="\\d{1,10}"', $content);
        $this->assertIsString($companyLookupScript);
        $this->assertStringContainsString('if (!/^\\d{1,10}$/.test(value))', $companyLookupScript);
        $this->assertStringNotContainsString('if (!/^\\d{10}$/.test(value))', $companyLookupScript);
    }

    public function test_company_registration_requires_verified_commercial_registration_lookup(): void
    {
        Storage::fake('local');
        $this->seed(AccessControlSeeder::class);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'registration_type' => 'company',
            'registration_number' => '2000033344',
            'email' => 'unchecked-company@example.com',
            'phone' => '0790000002',
            'address' => 'Amman, Jordan',
            'description' => 'Production house',
            'registration_document' => UploadedFile::fake()->create('license.pdf', 250, 'application/pdf'),
            'company_lookup_verified' => '1',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('registration_number');

        $this->assertDatabaseMissing('users', [
            'email' => 'unchecked-company@example.com',
        ]);
    }

    public function test_company_lookup_uses_ccd_company_registry_when_record_exists(): void
    {
        Cache::forget('gsb:ccd_company:44455');

        $this->configureCompanyGsbServices();

        Http::fake([
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/api/companies/CompanybyNo/44455' => Http::response([
                'data' => [[
                    'COMPANY_NAME_AR' => 'شركة أفلام تجريبية',
                    'REGISTRATION_DATE' => '2020-02-15',
                    'CAPITAL' => '250000',
                    'COMPANY_TYPE_NAME' => 'شركة ذات مسؤولية محدودة',
                    'GOVERNORATE_NAME' => 'العاصمة',
                    'REG_NO' => '4455',
                ]],
            ]),
        ]);

        $response = $this->post(route('register.company.lookup'), [
            'registration_number' => '44455',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.entity_name', 'شركة أفلام تجريبية')
            ->assertJsonPath('data.company_registration_date', '2020-02-15')
            ->assertJsonPath('data.company_capital', '250000')
            ->assertJsonPath('data.organization_type', 'شركة ذات مسؤولية محدودة')
            ->assertJsonPath('data.governorate', 'العاصمة')
            ->assertJsonPath('meta.source', 'gsb_ccd_company');

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/api/companies/CompanybyNo/44455'
            && $request->hasHeader('X-MODEE-Client-Id', 'client-id'));
    }

    public function test_company_lookup_falls_back_to_mit_for_establishment_record(): void
    {
        Cache::forget('gsb:ccd_company:66677');
        Cache::forget('gsb:mit_establishment:66677');

        $this->configureCompanyGsbServices();

        Http::fake([
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/api/companies/CompanybyNo/66677' => Http::response(['data' => []]),
            'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/api/Registry/getRegisteryInfoByEstablishmentNationalNumber' => Http::response([
                'code' => 200,
                'data' => [[
                    'ESTABLISHMENT_NAME' => 'مؤسسة إنتاج فردية',
                    'REG_DATE' => '2021-06-10',
                    'CAPITAL_VALUE' => '75000',
                    'ESTABLISHMENT_TYPE_NAME' => 'مؤسسة فردية',
                    'GOV_NAME' => 'إربد',
                ]],
            ]),
        ]);

        $response = $this->post(route('register.company.lookup'), [
            'registration_number' => '66677',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.entity_name', 'مؤسسة إنتاج فردية')
            ->assertJsonPath('data.company_registration_date', '2021-06-10')
            ->assertJsonPath('data.company_capital', '75000')
            ->assertJsonPath('data.organization_type', 'مؤسسة فردية')
            ->assertJsonPath('data.governorate', 'إربد')
            ->assertJsonPath('meta.source', 'gsb_mit_services')
            ->assertJsonCount(2, 'meta.attempts');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api-gateway.stg.gsb.gov.jo:9443/porg-g2g/g2g/api/Registry/getRegisteryInfoByEstablishmentNationalNumber'
            && $request['nationalNo'] === '66677');
    }

    public function test_company_lookup_rejects_non_numeric_or_more_than_ten_digit_numbers(): void
    {
        $this->postJson(route('register.company.lookup'), [
            'registration_number' => 'COMP-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('registration_number');

        $this->postJson(route('register.company.lookup'), [
            'registration_number' => '12345678901',
        ])->assertUnprocessable()->assertJsonValidationErrors('registration_number');
    }

    public function test_ngo_registration_creates_pending_account_with_address(): void
    {
        $this->assertOrganizationRegistrationCreatesPendingAccount(
            registrationType: 'ngo',
            registrationNumber: 'NGO-REG-1122',
            email: 'ngo-registration@example.com',
            phone: '0790000011',
            address: 'NGO address, Amman',
        );
    }

    public function test_school_registration_creates_pending_account_with_address(): void
    {
        $this->assertOrganizationRegistrationCreatesPendingAccount(
            registrationType: 'school',
            registrationNumber: 'SCH-REG-1122',
            email: 'school-registration@example.com',
            phone: '0790000012',
            address: 'School address, Amman',
        );
    }

    public function test_pending_organization_user_can_sign_in_and_is_sent_to_registration_status_page(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Mail::fake();

        $group = \App\Models\Group::query()->where('code', 'organizations')->firstOrFail();
        $reviewer = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Pending Org',
            'username' => 'pending-org',
            'email' => 'pending@example.com',
            'phone' => '0793000000',
            'status' => 'pending_review',
            'registration_type' => 'company',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Pending Org',
            'name_ar' => 'Pending Org',
            'registration_no' => 'PEND-001',
            'email' => 'pending@example.com',
            'phone' => '0793000000',
            'status' => 'pending_review',
            'registration_type' => 'company',
            'metadata' => [
                'address' => 'Amman',
                'review' => [
                    'decision' => 'needs_completion',
                    'note' => 'Please update the trade license copy.',
                    'reviewed_at' => '2026-04-12 10:00:00',
                    'reviewed_by_user_id' => $reviewer->getKey(),
                ],
                'review_history' => [
                    [
                        'decision' => 'approve',
                        'note' => 'Initial registration accepted.',
                        'reviewed_at' => '2026-04-10 09:00:00',
                        'reviewed_by_user_id' => $reviewer->getKey(),
                    ],
                    [
                        'decision' => 'needs_completion',
                        'note' => 'Please update the trade license copy.',
                        'reviewed_at' => '2026-04-12 10:00:00',
                        'reviewed_by_user_id' => $reviewer->getKey(),
                    ],
                ],
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
        $user->notify(new RegistrationCompletionRequestedNotification(
            entity: $entity,
            decision: 'needs_completion',
            note: 'Please update the trade license copy.',
        ));

        $loginResponse = $this->post(route('login.store'), [
            'identifier' => 'PEND-001',
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('otp.create'));

        $verifyResponse = $this->post(route('otp.store'), [
            'code' => (string) session('otp_debug_code'),
        ]);

        $verifyResponse->assertRedirect(route('dashboard'));

        $dashboardResponse = $this->actingAs($user)->get('/en/dashboard');

        $dashboardResponse
            ->assertOk()
            ->assertSeeText('Registration Status')
            ->assertSeeText('Latest registration update')
            ->assertSeeText('Registration review history')
            ->assertSeeText('Please update the trade license copy.')
            ->assertSeeText('Registration update required')
            ->assertSeeText('Approve registration');
    }

    public function test_pending_school_user_cannot_sign_in_before_admin_approval(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $group = \App\Models\Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Pending School',
            'username' => 'pending-school',
            'email' => 'school@example.com',
            'phone' => '0793111111',
            'status' => 'pending_review',
            'registration_type' => 'school',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Pending School',
            'name_ar' => 'Pending School',
            'registration_no' => 'SCH-001',
            'email' => 'school@example.com',
            'phone' => '0793111111',
            'status' => 'pending_review',
            'registration_type' => 'school',
            'metadata' => ['address' => 'Amman'],
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'identifier' => 'SCH-001',
            'password' => 'password123',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'identifier' => 'Your registration is pending admin approval. You can sign in after your account has been approved.',
            ]);

        $this->assertGuest();
    }

    public function test_pending_ngo_user_cannot_sign_in_before_admin_approval(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $group = \App\Models\Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Pending NGO',
            'username' => 'pending-ngo',
            'email' => 'ngo@example.com',
            'phone' => '0793222222',
            'status' => 'pending_review',
            'registration_type' => 'ngo',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Pending NGO',
            'name_ar' => 'Pending NGO',
            'registration_no' => 'NGO-001',
            'email' => 'ngo@example.com',
            'phone' => '0793222222',
            'status' => 'pending_review',
            'registration_type' => 'ngo',
            'metadata' => ['address' => 'Amman'],
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'identifier' => 'NGO-001',
            'password' => 'password123',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'identifier' => 'Your registration is pending admin approval. You can sign in after your account has been approved.',
            ]);

        $this->assertGuest();
    }

    public function test_login_requires_five_digit_otp_before_accessing_dashboard(): void
    {
        $this->seed(AccessControlSeeder::class);

        $entity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();

        $user = User::query()->create([
            'name' => 'RFC Reviewer',
            'username' => 'rfc_reviewer',
            'email' => 'reviewer@example.com',
            'national_id' => '4455667788',
            'phone' => '0791112233',
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

        $loginResponse = $this->post(route('login.store'), [
            'identifier' => 'reviewer@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('otp.create'));

        $otpCode = (string) session('otp_debug_code');

        $this->assertSame(5, strlen($otpCode));
        $this->assertGuest();

        $verifyResponse = $this->post(route('otp.store'), [
            'code' => $otpCode,
        ]);

        $verifyResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_debug_otp_fallback_survives_sms_gateway_timeout(): void
    {
        $this->seed(AccessControlSeeder::class);

        config()->set('services.otp_debug_fallback', true);

        app()->instance(\App\Services\SmsService::class, new class extends \App\Services\SmsService
        {
            public function send(string $text, string $to): array
            {
                return [
                    'ok' => false,
                    'stage' => 'auth_failed',
                    'http' => null,
                    'raw' => null,
                    'msisdn' => '962791112244',
                ];
            }
        });

        $entity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();

        $user = User::query()->create([
            'name' => 'RFC Timeout Tester',
            'username' => 'rfc_timeout_tester',
            'email' => 'timeout@example.com',
            'national_id' => '1122334455',
            'phone' => '0791112244',
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

        $loginResponse = $this->post(route('login.store'), [
            'identifier' => 'timeout@example.com',
            'password' => 'password123',
        ]);

        $loginResponse
            ->assertRedirect(route('otp.create'))
            ->assertSessionHas('status', __('app.auth.otp_debug_fallback_status'));

        $this->assertSame(5, strlen((string) session('otp_debug_code')));
        $this->assertGuest();
    }

    public function test_otp_page_marks_first_digit_for_autofocus(): void
    {
        $html = view('auth.verify-otp', [
            'maskedPhone' => '******2233',
            'debugCode' => '12345',
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('data-index="0"', $html);
        $this->assertStringContainsString('autofocus', $html);
        $this->assertStringContainsString('data-otp-autofocus="true"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('autocomplete="one-time-code"', $html);
        $this->assertStringContainsString('focus({ preventScroll: true })', $html);
    }

    public function test_arabic_registration_validation_messages_are_localized(): void
    {
        $this->refreshApplicationWithLocale('ar');

        $response = $this->from('/ar/register')
            ->followingRedirects()
            ->post('/ar/register', [
                'registration_type' => 'student',
            ]);

        $response
            ->assertOk()
            ->assertSeeText('حقل البريد الإلكتروني مطلوب.')
            ->assertSeeText('حقل الرقم الوطني مطلوب.')
            ->assertSeeText('حقل رقم الهاتف مطلوب.')
            ->assertSeeText('حقل العنوان مطلوب.');
    }

    private function configureCompanyGsbServices(): void
    {
        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.client_id', 'client-id');
        config()->set('services.gsb.client_secret', 'client-secret');
        config()->set('services.gsb.services.ccd_company.enabled', true);
        config()->set('services.gsb.services.ccd_company.base_url', 'https://api-gateway.stg.gsb.gov.jo:9443');
        config()->set('services.gsb.services.ccd_company.path', '/porg-g2g/g2g/api/companies/CompanybyNo/{nationalNo}');
        config()->set('services.gsb.services.ccd_company.method', 'GET');
        config()->set('services.gsb.services.mit_services.enabled', true);
        config()->set('services.gsb.services.mit_services.base_url', 'https://api-gateway.stg.gsb.gov.jo:9443');
        config()->set('services.gsb.services.mit_services.path', '/porg-g2g/g2g/api/Registry/getRegisteryInfoByEstablishmentNationalNumber');
        config()->set('services.gsb.services.mit_services.method', 'POST');
    }

    private function assertOrganizationRegistrationCreatesPendingAccount(
        string $registrationType,
        string $registrationNumber,
        string $email,
        string $phone,
        string $address,
    ): void {
        Storage::fake('local');
        $this->seed(AccessControlSeeder::class);

        $response = $this->post(route('register.store'), [
            'registration_type' => $registrationType,
            'entity_name' => ucfirst($registrationType).' Test Entity',
            'registration_number' => $registrationNumber,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'description' => ucfirst($registrationType).' registration test',
            'registration_document' => UploadedFile::fake()->create($registrationType.'-license.pdf', 250, 'application/pdf'),
            'logo' => UploadedFile::fake()->image($registrationType.'-logo.png')->size(100),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been submitted for review. We will notify you by email and SMS once it is approved, rejected, or requires additional information.');

        $entity = Entity::query()->where('registration_no', $registrationNumber)->firstOrFail();
        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertSame($registrationType, $entity->registration_type);
        $this->assertSame($registrationType, $user->registration_type);
        $this->assertSame('pending_review', $entity->status);
        $this->assertSame('pending_review', $user->status);
        $this->assertSame($address, data_get($entity->metadata, 'address'));
        Storage::disk('local')->assertExists((string) data_get($entity->metadata, 'registration_document_path'));
        Storage::disk('local')->assertExists((string) data_get($entity->metadata, 'logo_path'));
        $this->assertSame($registrationType.'-logo.png', data_get($entity->metadata, 'logo_name'));
    }
}
