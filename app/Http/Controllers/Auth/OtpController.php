<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OtpController extends Controller
{
    private function shouldShowDebugCode(): bool
    {
        return app()->environment(['local', 'testing'])
            || (app()->environment('local') && filter_var(env('OTP_DEBUG_FALLBACK', false), FILTER_VALIDATE_BOOL));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('pending_auth_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp', [
            'maskedPhone' => $this->maskPhone((string) $request->session()->get('pending_auth_phone')),
            'debugCode' => $this->shouldShowDebugCode()
                ? $request->session()->get('otp_debug_code')
                : null,
        ]);
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:5'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $otpService->verifyLoginOtp($user, $request->string('code')->toString())) {
            return back()->withErrors([
                'code' => __('app.auth.otp_invalid'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('current_entity_id', $request->session()->pull('pending_auth_entity_id'));
        $request->session()->forget([
            'pending_auth_user_id',
            'pending_auth_identifier',
            'pending_auth_phone',
            'otp_debug_code',
        ]);

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->route('dashboard');
    }

    public function resend(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $issuedOtp = $otpService->issueLoginOtp(
            user: $user,
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        if (! $issuedOtp['sms']['ok']) {
            return back()->withErrors([
                'code' => __('app.auth.otp_resend_failed'),
            ]);
        }

        $request->session()->put('pending_auth_phone', $issuedOtp['phone']);

        if ($this->shouldShowDebugCode()) {
            $request->session()->put('otp_debug_code', $issuedOtp['code']);
        }

        return back()->with('status', ! $issuedOtp['sms']['ok'] && filter_var(env('OTP_DEBUG_FALLBACK', false), FILTER_VALIDATE_BOOL)
            ? __('app.auth.otp_debug_fallback_status')
            : __('app.auth.otp_resent'));
    }

    private function pendingUser(Request $request): ?User
    {
        $pendingUserId = $request->session()->get('pending_auth_user_id');

        if (! $pendingUserId) {
            return null;
        }

        return User::query()->find($pendingUserId);
    }

    private function maskPhone(string $phone): string
    {
        if ($phone === '') {
            return __('app.auth.registered_phone_fallback');
        }

        $length = strlen($phone);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max($length - 4, 0)).substr($phone, -4);
    }
}
