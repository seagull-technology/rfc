<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);
        $identifier = trim((string) $data['identifier']);

        $user = User::query()
            ->where('national_id', $identifier)
            ->orWhereHas('entities', function ($query) use ($identifier): void {
                $query->where('registration_no', $identifier);
            })
            ->first();

        $request->session()->forget([
            'pending_password_reset_user_id',
            'pending_password_reset_phone',
            'password_reset_otp_debug_code',
        ]);
        $request->session()->put([
            'pending_password_reset_user_id' => $user && filled($user->phone) ? $user->getKey() : 0,
            'pending_password_reset_identifier' => $identifier,
            'pending_password_reset_requested_at' => now()->timestamp,
        ]);

        if ($user && filled($user->phone)) {
            $issuedOtp = $otpService->issuePasswordResetOtp(
                user: $user,
                ipAddress: $request->ip(),
                userAgent: (string) $request->userAgent(),
            );

            if ($issuedOtp['sms']['ok'] || $this->shouldAllowDebugFallback()) {
                $request->session()->put('pending_password_reset_phone', $issuedOtp['phone']);
            }

            if (app()->environment(['local', 'testing']) || $this->shouldAllowDebugFallback()) {
                $request->session()->put('password_reset_otp_debug_code', $issuedOtp['code']);
            }

            if (! $issuedOtp['sms']['ok'] && ! $this->shouldAllowDebugFallback()) {
                $issuedOtp['otp']->delete();
                $request->session()->forget('password_reset_otp_debug_code');
            }
        }

        return redirect()
            ->route('password.otp.create')
            ->with('status', __('app.auth.password_reset_otp_sent'));
    }

    private function shouldAllowDebugFallback(): bool
    {
        return (bool) config('services.otp_debug_fallback', false);
    }
}
