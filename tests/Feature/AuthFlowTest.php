<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use App\Notifications\RegistrationCompletionRequestedNotification;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

        $response = $this->post(route('register.store'), [
            'registration_type' => 'student',
            'full_name' => 'Ali Ahmad',
            'email' => 'ali@example.com',
            'national_id' => '9876543210',
            'phone' => '0791234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));

        $user = User::query()->where('email', 'ali@example.com')->firstOrFail();
        $entity = Entity::query()->where('national_id', '9876543210')->firstOrFail();

        $this->assertSame('student', $user->registration_type);
        $this->assertSame('student', $entity->registration_type);

        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $entity->getKey(),
            'user_id' => $user->getKey(),
            'is_primary' => 1,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $this->assertTrue($user->hasRole('applicant_owner'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_company_registration_creates_entity_account_and_stores_uploaded_document(): void
    {
        Storage::fake('local');
        $this->seed(AccessControlSeeder::class);

        $response = $this->post(route('register.store'), [
            'registration_type' => 'company',
            'entity_name' => 'Future Films',
            'registration_number' => 'REG-1122',
            'email' => 'info@futurefilms.test',
            'phone' => '0790000001',
            'address' => 'Amman, Jordan',
            'description' => 'Production house',
            'registration_document' => UploadedFile::fake()->create('license.pdf', 250, 'application/pdf'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));

        $entity = Entity::query()->where('registration_no', 'REG-1122')->firstOrFail();
        $user = User::query()->where('email', 'info@futurefilms.test')->firstOrFail();

        $this->assertSame('company', $entity->registration_type);
        $this->assertSame('company', $user->registration_type);
        $this->assertSame('pending_review', $entity->status);
        $this->assertSame('pending_review', $user->status);
        $this->assertSame('Amman, Jordan', data_get($entity->metadata, 'address'));
        $this->assertSame('Production house', data_get($entity->metadata, 'description'));
        Storage::disk('local')->assertExists((string) data_get($entity->metadata, 'registration_document_path'));
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
            ->assertSeeText('حقل الاسم الكامل مطلوب.')
            ->assertSeeText('حقل البريد الإلكتروني مطلوب.')
            ->assertSeeText('حقل الرقم الوطني مطلوب.');
    }
}
