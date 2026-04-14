<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_link(): void
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
        ]);

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('status', __('app.auth.reset_link_sent'));

        $this->assertTrue(DB::table('password_reset_tokens')->where('email', $user->email)->exists());
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
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('app.auth.password_reset_success'));

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }
}
