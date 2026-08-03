<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetOtpController extends Controller
{
    public function create(Request $request, OtpService $otpService): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $this->hasPendingRequest($request)) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-otp', [
            'pageTitle' => __('app.auth.password_reset_verify_title'),
            'heading' => __('app.auth.password_reset_verify_heading'),
            'intro' => __('app.auth.password_reset_verify_intro'),
            'submitLabel' => __('app.auth.password_reset_verify_submit'),
            'formAction' => route('password.otp.store'),
            'resendAction' => route('password.otp.resend'),
            'showRegistrationLink' => false,
            'debugCode' => $this->shouldShowDebugCode()
                ? $request->session()->get('password_reset_otp_debug_code')
                : null,
            'resendAvailableIn' => $this->resendAvailableIn($request, $user, $otpService),
        ]);
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:5'],
        ]);

        $user = $this->pendingUser($request);

        if (! $this->hasPendingRequest($request) || ! $user) {
            return back()->withErrors([
                'code' => __('app.auth.otp_invalid'),
            ]);
        }

        if (! $otpService->verifyPasswordResetOtp($user, $request->string('code')->toString())) {
            return back()->withErrors([
                'code' => __('app.auth.otp_invalid'),
            ]);
        }

        $token = Password::broker()->createToken($user);

        $request->session()->forget([
            'pending_password_reset_user_id',
            'pending_password_reset_identifier',
            'pending_password_reset_phone',
            'pending_password_reset_requested_at',
            'password_reset_otp_debug_code',
        ]);
        $request->session()->put([
            'verified_password_reset_token' => $token,
            'verified_password_reset_email' => $user->email,
        ]);

        return redirect()->route('password.reset', ['token' => $token]);
    }

    public function resend(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $this->hasPendingRequest($request)) {
            return redirect()->route('password.request');
        }

        $resendAvailableIn = $this->resendAvailableIn($request, $user, $otpService);

        if ($resendAvailableIn > 0) {
            return back()->withErrors([
                'resend' => __('app.auth.otp_resend_wait', [
                    'time' => $this->formatCountdown($resendAvailableIn),
                ]),
            ]);
        }

        $request->session()->put('pending_password_reset_requested_at', now()->timestamp);

        if ($user) {
            $issuedOtp = $otpService->issuePasswordResetOtp(
                user: $user,
                ipAddress: $request->ip(),
                userAgent: (string) $request->userAgent(),
            );

            if ($issuedOtp['sms']['ok'] || $this->shouldAllowDebugFallback()) {
                $request->session()->put('pending_password_reset_phone', $issuedOtp['phone']);
            }

            if (! $issuedOtp['sms']['ok'] && ! $this->shouldAllowDebugFallback()) {
                $issuedOtp['otp']->delete();
                $request->session()->forget('password_reset_otp_debug_code');
            }

            if ($this->shouldShowDebugCode()) {
                $request->session()->put('password_reset_otp_debug_code', $issuedOtp['code']);
            }
        }

        return back()->with('status', __('app.auth.otp_resent'));
    }

    private function pendingUser(Request $request): ?User
    {
        $pendingUserId = $request->session()->get('pending_password_reset_user_id');

        if (! $pendingUserId) {
            return null;
        }

        return User::query()->find($pendingUserId);
    }

    private function hasPendingRequest(Request $request): bool
    {
        return filled($request->session()->get('pending_password_reset_identifier'));
    }

    private function resendAvailableIn(Request $request, ?User $user, OtpService $otpService): int
    {
        $requestedAt = (int) $request->session()->get('pending_password_reset_requested_at', 0);
        $sessionCooldown = $requestedAt > 0
            ? max(0, ($requestedAt + OtpService::RESEND_COOLDOWN_SECONDS) - now()->timestamp)
            : 0;

        if (! $user) {
            return $sessionCooldown;
        }

        return max(
            $sessionCooldown,
            $otpService->resendAvailableIn($user, OtpService::PURPOSE_PASSWORD_RESET),
        );
    }

    private function shouldAllowDebugFallback(): bool
    {
        return (bool) config('services.otp_debug_fallback', false);
    }

    private function shouldShowDebugCode(): bool
    {
        return app()->environment(['local', 'testing']) || $this->shouldAllowDebugFallback();
    }

    private function formatCountdown(int $seconds): string
    {
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
