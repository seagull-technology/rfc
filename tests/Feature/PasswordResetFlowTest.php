<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SmsService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_producer_invitation_email_is_read_only(): void
    {
        $this->refreshApplicationWithLocale('en');

        $response = $this->get(route('password.reset', [
            'token' => 'invitation-token',
            'email' => 'foreign.producer@example.com',
            'invitation' => 1,
        ]));

        $response
            ->assertOk()
            ->assertSee('value="foreign.producer@example.com"', false)
            ->assertSee('readonly', false)
            ->assertSee('aria-readonly="true"', false)
            ->assertSee('data-password-reset-form', false)
            ->assertSee('data-password-reset-submit', false);
    }

    public function test_user_can_request_password_reset_otp_with_national_id(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('id="password-reset-request-form"', false)
            ->assertDontSee('class="submit"', false);

        $user = User::query()->create([
            'name' => 'Reset User',
            'username' => 'reset-user',
            'email' => 'reset@example.com',
            'national_id' => '8877665544',
            'phone' => '0799990000',
            'status' => 'active',
            'registration_type' => 'student',
            'password' => Hash::make('password123'),
            'must_change_password' => true,
        ]);

        $response = $this->post(route('password.otp.send'), [
            'identifier' => $user->national_id,
        ]);

        $response
            ->assertRedirect(route('password.otp.create'))
            ->assertSessionHas('status', __('app.auth.password_reset_otp_sent'));

        $this->assertTrue(DB::table('login_otps')
            ->where('user_id', $user->getKey())
            ->where('purpose', 'password_reset')
            ->exists());
        $this->assertFalse(DB::table('password_reset_tokens')->where('email', $user->email)->exists());
        $this->assertSame(5, strlen((string) session('password_reset_otp_debug_code')));
    }

    public function test_failed_password_reset_sms_uses_the_neutral_response_and_does_not_leave_a_stale_otp(): void
    {
        $this->refreshApplicationWithLocale('en');
        config()->set('services.otp_debug_fallback', false);

        app()->instance(SmsService::class, new class extends SmsService
        {
            public function send(string $text, string $to): array
            {
                return [
                    'ok' => false,
                    'stage' => 'auth_failed',
                    'http' => null,
                    'raw' => null,
                    'msisdn' => '962799992222',
                ];
            }
        });

        $user = User::query()->create([
            'name' => 'Failed Reset SMS User',
            'username' => 'failed-reset-sms',
            'email' => 'failed-reset-sms@example.com',
            'national_id' => '1122446688',
            'phone' => '0799992222',
            'status' => 'active',
            'registration_type' => 'student',
            'password' => Hash::make('password123'),
        ]);

        $this->from(route('password.request'))->post(route('password.otp.send'), [
            'identifier' => $user->national_id,
        ])->assertRedirect(route('password.otp.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('app.auth.password_reset_otp_sent'));

        $this->assertSame(0, DB::table('login_otps')
            ->where('user_id', $user->getKey())
            ->where('purpose', 'password_reset')
            ->count());
    }

    public function test_password_reset_does_not_reveal_whether_an_identifier_exists(): void
    {
        $this->refreshApplicationWithLocale('en');

        $user = User::query()->create([
            'name' => 'Enumeration Test User',
            'username' => 'enumeration-test-user',
            'email' => 'enumeration@example.com',
            'national_id' => '1010101010',
            'phone' => '0799993333',
            'status' => 'active',
            'registration_type' => 'student',
            'password' => Hash::make('Password@123'),
        ]);

        $existingResponse = $this->post(route('password.otp.send'), [
            'identifier' => $user->national_id,
        ]);

        $existingResponse
            ->assertRedirect(route('password.otp.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('app.auth.password_reset_otp_sent'));

        $missingResponse = $this->post(route('password.otp.send'), [
            'identifier' => '9090909090',
        ]);

        $missingResponse
            ->assertStatus($existingResponse->getStatusCode())
            ->assertRedirect(route('password.otp.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('app.auth.password_reset_otp_sent'));

        $this->get(route('password.otp.create'))
            ->assertOk()
            ->assertSeeText(__('app.auth.password_reset_verify_intro'));

        $this->post(route('password.otp.store'), ['code' => '12345'])
            ->assertSessionHasErrors('code');
    }

    public function test_user_can_reset_password_after_verifying_otp(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $user = User::query()->create([
            'name' => 'OTP Reset User',
            'username' => 'otp-reset-user',
            'email' => 'otp-reset@example.com',
            'national_id' => '9988776655',
            'phone' => '0799991111',
            'status' => 'active',
            'registration_type' => 'student',
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('password.otp.send'), [
            'identifier' => $user->national_id,
        ])->assertRedirect(route('password.otp.create'));

        $otpCode = (string) session('password_reset_otp_debug_code');

        $verifyResponse = $this->post(route('password.otp.store'), [
            'code' => $otpCode,
        ]);

        $token = (string) session('verified_password_reset_token');

        $verifyResponse->assertRedirect(route('password.reset', ['token' => $token]));
        $this->assertNotSame('', $token);
        $this->assertTrue(DB::table('login_otps')
            ->where('user_id', $user->getKey())
            ->where('purpose', 'password_reset')
            ->whereNotNull('consumed_at')
            ->exists());

        $this->get(route('password.reset', ['token' => $token]))
            ->assertOk()
            ->assertSee('type="hidden" name="email"', false)
            ->assertDontSee('id="email"', false);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', __('app.auth.password_reset_success'));

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword@123', $user->password));
        $this->assertNull(session('verified_password_reset_token'));
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $user = User::query()->create([
            'name' => 'Reset User',
            'username' => 'reset-user',
            'email' => 'reset@example.com',
            'phone' => '0799990000',
            'status' => 'active',
            'registration_type' => 'student',
            'password' => Hash::make('password123'),
            'must_change_password' => true,
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('app.auth.password_reset_success'));

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword@123', $user->password));
        $this->assertFalse($user->requiresPasswordSetup());
        $this->assertNotNull($user->password_changed_at);
    }
}
