<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use App\Notifications\RegistrationCompletionRequestedNotification;
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

    public function test_student_registration_creates_user_entity_and_scoped_role(): void
    {
        $this->seed(AccessControlSeeder::class);

        $lookupResponse = $this->post(route('register.student.lookup'), [
            'national_id' => '9876543210',
        ]);

        $lookupResponse->assertOk();
        $studentData = $lookupResponse->json('data');

        $response = $this->post(route('register.store'), [
            'registration_type' => 'student',
            'email' => 'ali@example.com',
            'national_id' => '9876543210',
            'phone' => '0791234567',
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
            'phone' => '0791234568',
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

        Cache::forget('gsb:mohe_sanad:current:9876543213');

        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.client_id', 'client-id');
        config()->set('services.gsb.client_secret', 'client-secret');
        config()->set('services.gsb.services.mohe_sanad.enabled', true);
        config()->set('services.gsb.services.mohe_sanad.base_url', 'https://api-gateway.g2b.gsb.gov.jo:9443');
        config()->set('services.gsb.services.mohe_sanad.path', '/porg-gsb/g2b-catalog/api/mohe-sanad');

        Http::fake([
            'https://api-gateway.g2b.gsb.gov.jo:9443/porg-gsb/g2b-catalog/api/mohe-sanad' => Http::response([
                'code' => null,
                'data' => [[
                    'STUDENT_NAME' => 'MOHE Student',
                    'BIRTH_DATE' => '2002-01-02',
                    'gender_desc' => 'Female',
                    'NATIONALITY' => 'Jordanian',
                    'INSTITUTE_NAME' => 'Yarmouk University',
                    'S_MAJOR_NAME' => 'Cinema Production',
                    'degree' => 'Bachelor',
                    'student_status' => 'currently studying',
                ]],
            ], 200),
        ]);

        $response = $this->post(route('register.student.lookup'), [
            'national_id' => '9876543213',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.full_name', 'MOHE Student')
            ->assertJsonPath('data.birth_date', '2002-01-02')
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.university_name', 'Yarmouk University')
            ->assertJsonPath('data.major', 'Cinema Production');
    }

    public function test_pending_student_user_cannot_sign_in_before_admin_approval(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $this->post(route('register.student.lookup'), [
            'national_id' => '9876543212',
        ])->assertOk();

        $this->post(route('register.store'), [
            'registration_type' => 'student',
            'email' => 'pending-student@example.com',
            'national_id' => '9876543212',
            'phone' => '0791234569',
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
            'registration_number' => 'REG-1122',
        ]);

        $lookupResponse->assertOk();
        $companyData = $lookupResponse->json('data');

        $response = $this->post(route('register.store'), [
            'registration_type' => 'company',
            'registration_number' => 'REG-1122',
            'email' => 'info@futurefilms.test',
            'phone' => '0790000001',
            'address' => 'Amman, Jordan',
            'description' => 'Production house',
            'registration_document' => UploadedFile::fake()->create('license.pdf', 250, 'application/pdf'),
            'company_lookup_verified' => '1',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been submitted for review. We will notify you by email and SMS once it is approved, rejected, or requires additional information.');

        $entity = Entity::query()->where('registration_no', 'REG-1122')->firstOrFail();
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
    }

    public function test_company_registration_requires_verified_commercial_registration_lookup(): void
    {
        Storage::fake('local');
        $this->seed(AccessControlSeeder::class);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'registration_type' => 'company',
            'registration_number' => 'REG-3344',
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
            ->assertSeeText('حقل رقم الهاتف مطلوب.');
    }
}
