<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Timebox;
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

        return app(Timebox::class)->call(function () use ($request, $otpService, $identifier): RedirectResponse {

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

            if (! app()->environment(['local', 'testing'])) {
                // Queue every identifier, including unknown ones. SMS network latency
                // must never be part of this public HTTP response, including under IIS.
                SendPasswordResetOtp::dispatch(
                    $user && filled($user->phone) ? (int) $user->getKey() : 0,
                    $request->ip(),
                    (string) $request->userAgent(),
                );
            } elseif ($user && filled($user->phone)) {
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
        }, 200_000);
    }

    private function shouldAllowDebugFallback(): bool
    {
        return (bool) config('services.otp_debug_fallback', false);
    }
}
