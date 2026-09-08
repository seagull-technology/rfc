<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\PasswordResetOtpController;
use App\Jobs\SendPasswordResetOtp;
use App\Models\ContactCenterMessage;
use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use App\Models\WorkCategory;
use App\Services\OtpService;
use App\Services\SmsService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountEnumerationAndAbuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_without_lookup_proof_does_not_reveal_an_existing_national_id(): void
    {
        User::factory()->create(['national_id' => '9876543201']);

        $existing = $this->postJson(route('register.store'), $this->studentPayload('9876543201'));
        $unknown = $this->postJson(route('register.store'), $this->studentPayload('9876543202'));

        $existing->assertUnprocessable()->assertJsonValidationErrors('national_id');
        $unknown->assertStatus($existing->status())->assertExactJson($existing->json());
    }

    public function test_student_duplicate_and_new_registration_have_the_same_http_acknowledgement(): void
    {
        $this->seed(AccessControlSeeder::class);
        $payload = $this->studentPayload('9876543203');
        $this->postJson(route('register.student.lookup'), $payload)->assertOk();
        $new = $this->postJson(route('register.store'), $payload)->assertRedirect(route('register.submitted'));
        $count = User::query()->count();

        $this->postJson(route('register.student.lookup'), $payload)->assertOk();
        $duplicate = $this->postJson(route('register.store'), array_replace($payload, [
            'email' => 'unclaimed@example.com',
            'phone' => '0799990002',
        ]));

        $duplicate->assertStatus($new->status())
            ->assertRedirect($new->headers->get('Location'))
            ->assertContent($new->getContent())
            ->assertSessionHasNoErrors();
        $this->assertSame($count, User::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'unclaimed@example.com']);
    }

    public function test_organization_email_phone_and_number_duplicates_share_the_new_registration_response(): void
    {
        Storage::fake('local');
        $this->seed(AccessControlSeeder::class);
        $payload = [
            'registration_type' => 'school',
            'entity_name' => 'Example School',
            'registration_number' => 'SCHOOL-ORIGINAL',
            'email' => 'school-original@example.com',
            'phone' => '0799990003',
            'address' => 'Amman',
            'description' => 'School registration',
            'registration_document' => $this->fakePdf('school.pdf'),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];
        $new = $this->post(route('register.organization.store'), $payload)->assertRedirect(route('register.submitted'));
        $userCount = User::query()->count();
        $entityCount = Entity::query()->count();

        foreach (['email', 'phone', 'registration_number'] as $field) {
            $attempt = array_replace($payload, [
                'registration_number' => 'SCHOOL-UNCLAIMED',
                'email' => 'school-unclaimed@example.com',
                'phone' => '0799990004',
                'registration_document' => $this->fakePdf('school.pdf'),
                $field => $payload[$field],
            ]);

            $this->post(route('register.organization.store'), $attempt)
                ->assertStatus($new->status())
                ->assertRedirect($new->headers->get('Location'))
                ->assertContent($new->getContent())
                ->assertSessionHasNoErrors();
        }

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($entityCount, Entity::query()->count());
    }

    public function test_production_password_recovery_queues_every_identifier_without_contacting_sms(): void
    {
        Bus::fake([SendPasswordResetOtp::class]);
        $user = User::factory()->create(['national_id' => '9876543204', 'phone' => '0799990005']);
        $request = Request::create(route('password.otp.send'), 'POST', ['identifier' => $user->national_id]);
        $request->setLaravelSession(app('session.store'));
        $otpService = $this->mock(OtpService::class);
        $otpService->shouldNotReceive('issuePasswordResetOtp');

        $this->app['env'] = 'production';
        try {
            $existing = app(ForgotPasswordController::class)->store($request, $otpService);
            $request->merge(['identifier' => '9876543205']);
            $unknown = app(ForgotPasswordController::class)->store($request, $otpService);
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame($existing->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame($existing->getTargetUrl(), $unknown->getTargetUrl());
        Bus::assertDispatched(SendPasswordResetOtp::class, fn ($job): bool => $job->userId === $user->getKey());
        Bus::assertDispatched(SendPasswordResetOtp::class, fn ($job): bool => $job->userId === 0);
        Bus::assertDispatchedTimes(SendPasswordResetOtp::class, 2);
        $this->assertDatabaseCount('login_otps', 0);
        $this->assertNull(session('password_reset_otp_debug_code'));
    }

    public function test_soft_deleted_accounts_are_acknowledged_without_recreating_their_identity(): void
    {
        $this->seed(AccessControlSeeder::class);
        $payload = $this->studentPayload('9876543219');
        $user = User::factory()->create(['national_id' => $payload['national_id']]);
        $user->delete();
        $userCount = User::withTrashed()->count();

        $this->postJson(route('register.student.lookup'), $payload)->assertOk();
        $this->postJson(route('register.store'), $payload)
            ->assertRedirect(route('register.submitted'))->assertSessionHasNoErrors();

        $this->assertSame($userCount, User::withTrashed()->count());
        $this->assertSoftDeleted($user);
    }

    public function test_soft_deleted_entities_do_not_disclose_reserved_registration_numbers(): void
    {
        Storage::fake('local');
        $this->seed(AccessControlSeeder::class);
        $entity = Entity::query()->create([
            'group_id' => Group::query()->where('code', 'organizations')->value('id'),
            'name_en' => 'Deleted school', 'name_ar' => 'مدرسة',
            'registration_no' => 'SCHOOL-DELETED', 'registration_type' => 'school', 'status' => 'active',
        ]);
        $entity->delete();

        $this->post(route('register.store'), [
            'registration_type' => 'school', 'registration_number' => 'SCHOOL-DELETED',
            'entity_name' => 'New school', 'email' => 'school-deleted@example.com',
            'phone' => '0799990020', 'address' => 'Amman', 'description' => '',
            'registration_document' => $this->fakePdf('deleted.pdf'),
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('register.submitted'))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($entity);
        $this->assertDatabaseMissing('users', ['email' => 'school-deleted@example.com']);
        $this->assertSame([], Storage::disk('local')->allFiles('registration-documents'));
    }

    public function test_database_queue_defers_otp_creation_until_the_worker_handles_the_job(): void
    {
        config()->set('queue.default', 'database');
        $user = User::factory()->create(['national_id' => '9876543216', 'phone' => '0799990016']);
        $request = Request::create(route('password.otp.send'), 'POST', ['identifier' => $user->national_id]);
        $request->setLaravelSession(app('session.store'));

        $this->app['env'] = 'production';
        try {
            app(ForgotPasswordController::class)->store($request, app(OtpService::class));
            $request->merge(['identifier' => '9876543215']);
            app(ForgotPasswordController::class)->store($request, app(OtpService::class));
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseCount('login_otps', 0);

        $job = Queue::connection('database')->pop();
        $this->assertNotNull($job);
        $job->fire();
        $this->assertDatabaseHas('login_otps', ['user_id' => $user->getKey(), 'purpose' => 'password_reset']);

        $unknownJob = Queue::connection('database')->pop();
        $this->assertNotNull($unknownJob);
        $unknownJob->fire();
        $this->assertDatabaseCount('login_otps', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_username_constraint_conflicts_return_the_neutral_response_and_remove_uploaded_files(): void
    {
        Storage::fake('local');
        $this->seed(AccessControlSeeder::class);
        $user = User::factory()->create(['username' => 'school-school-race']);
        $userCount = User::query()->count();

        // The username conflict is detected by the unique constraint, after the
        // email/phone/registration-number availability check has succeeded.
        $this->post(route('register.store'), [
            'registration_type' => 'school', 'registration_number' => 'SCHOOL-RACE',
            'entity_name' => 'Race test', 'email' => 'school-race@example.com',
            'phone' => '0799990019', 'address' => 'Amman', 'description' => '',
            'registration_document' => $this->fakePdf('race.pdf'),
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('register.submitted'))->assertSessionHasNoErrors();

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($user->getKey(), User::query()->where('username', 'school-school-race')->value('id'));
        $this->assertSame([], Storage::disk('local')->allFiles('registration-documents'));
    }

    public function test_other_sessions_otp_cooldown_is_not_exposed_on_the_public_recovery_page(): void
    {
        $user = User::factory()->create(['national_id' => '9876543218', 'phone' => '0799990018']);
        $otpService = app(OtpService::class);
        $otpService->issuePasswordResetOtp($user);
        $request = Request::create(route('password.otp.create'));
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([
            'pending_password_reset_identifier' => $user->national_id,
            'pending_password_reset_user_id' => $user->getKey(),
            'pending_password_reset_requested_at' => now()->subMinutes(6)->timestamp,
        ]);

        $existing = app(PasswordResetOtpController::class)->create($request, $otpService)->getData();
        $request->session()->put([
            'pending_password_reset_identifier' => '9876543217',
            'pending_password_reset_user_id' => 0,
        ]);
        $unknown = app(PasswordResetOtpController::class)->create($request, $otpService)->getData();

        $this->assertSame(0, $existing['resendAvailableIn']);
        $this->assertSame($existing, $unknown);
    }

    public function test_production_password_recovery_resend_is_queued_without_contacting_sms(): void
    {
        Bus::fake([SendPasswordResetOtp::class]);
        $user = User::factory()->create(['national_id' => '9876543206', 'phone' => '0799990006']);
        $request = Request::create(route('password.otp.resend'), 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([
            'pending_password_reset_identifier' => $user->national_id,
            'pending_password_reset_user_id' => $user->getKey(),
            'pending_password_reset_requested_at' => now()->subMinutes(6)->timestamp,
        ]);
        $otpService = $this->mock(OtpService::class);
        $otpService->shouldNotReceive('issuePasswordResetOtp');

        $this->app['env'] = 'production';
        try {
            app(PasswordResetOtpController::class)->resend($request, $otpService);
        } finally {
            $this->app['env'] = 'testing';
        }

        Bus::assertDispatched(SendPasswordResetOtp::class, fn ($job): bool => $job->userId === $user->getKey());
        $this->assertDatabaseCount('login_otps', 0);
    }

    public function test_failed_deferred_password_reset_delivery_removes_the_unusable_otp(): void
    {
        $user = User::factory()->create(['phone' => '0799990007']);
        $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['ok' => false]);

        (new SendPasswordResetOtp((int) $user->getKey(), '203.0.113.10', 'test'))->handle(app(OtpService::class));

        $this->assertDatabaseCount('login_otps', 0);
    }

    public function test_stale_queued_recovery_requests_do_not_create_or_replace_an_otp(): void
    {
        $user = User::factory()->create(['phone' => '0799990021']);
        $this->mock(SmsService::class)->shouldNotReceive('send');

        (new SendPasswordResetOtp(
            (int) $user->getKey(), '203.0.113.10', 'test', now()->subMinutes(6)->timestamp,
        ))->handle(app(OtpService::class));

        $this->assertDatabaseCount('login_otps', 0);
    }

    public function test_unknown_recovery_identifiers_do_not_share_an_existence_revealing_otp_limit(): void
    {
        $this->withSession([
            'pending_password_reset_user_id' => 0,
            'pending_password_reset_identifier' => '9876543207',
        ]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.otp.store'), ['code' => '12345'])->assertRedirect();
        }
        $this->post(route('password.otp.store'), ['code' => '12345'])->assertTooManyRequests();

        $this->withSession(['pending_password_reset_identifier' => '9876543208'])
            ->post(route('password.otp.store'), ['code' => '12345'])->assertRedirect();
    }

    public function test_malformed_authentication_identifier_gets_validation_instead_of_a_server_error(): void
    {
        $this->postJson(route('login.store'), ['identifier' => ['invalid'], 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('identifier');
        $this->postJson(route('password.otp.send'), ['identifier' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('identifier');
    }

    public function test_contact_message_replays_are_blocked_before_writing_a_sixth_message(): void
    {
        Notification::fake();
        $this->seed(AccessControlSeeder::class);
        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $this->actingAs($admin);
        $payload = ['title' => 'Replay test', 'message_type' => 'general_notice', 'message' => 'Test', 'recipient_scope' => 'all'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$attempt])
                ->post(route('admin.contact-center.messages.store'), $payload)
                ->assertRedirect(route('admin.contact-center.index'));
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.6'])
            ->post(route('admin.contact-center.messages.store'), $payload)
            ->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertSame(5, ContactCenterMessage::query()->count());

        $this->travel(61)->seconds();
        $this->post(route('admin.contact-center.messages.store'), $payload)
            ->assertRedirect(route('admin.contact-center.index'));
        $this->assertSame(6, ContactCenterMessage::query()->count());
    }

    public function test_work_and_release_mutations_share_one_limit_across_endpoints(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->actingAs(User::query()->where('email', 'superadmin@rfc.local')->firstOrFail());
        $initialCount = WorkCategory::query()->count();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('admin.work-release-lookups.work-categories.store'), [
                'code' => 'replay_'.$attempt, 'name_en' => 'Replay '.$attempt,
                'name_ar' => 'اختبار '.$attempt, 'work_summary_min_words' => 10,
            ])->assertRedirect(route('admin.work-release-lookups.index'));
        }

        $this->post(route('admin.work-release-lookups.release-methods.store'), [
            'code' => 'blocked_release', 'name_en' => 'Blocked', 'name_ar' => 'اختبار',
        ])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertSame($initialCount + 5, WorkCategory::query()->count());
        $this->assertDatabaseMissing('release_methods', ['code' => 'blocked_release']);
    }

    private function studentPayload(string $nationalId): array
    {
        return [
            'registration_type' => 'student', 'email' => 'student@example.com',
            'national_id' => $nationalId, 'birth_date' => '1999-01-15',
            'phone' => '0799990001', 'address' => 'Amman', 'student_lookup_verified' => '1',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ];
    }
}
