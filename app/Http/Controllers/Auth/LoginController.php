<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    private function shouldAllowDebugFallback(): bool
    {
        return filter_var(env('OTP_DEBUG_FALLBACK', false), FILTER_VALIDATE_BOOL);
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $identifier = trim((string) $credentials['identifier']);

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->orWhere('national_id', $identifier)
            ->orWhereHas('entities', function ($query) use ($identifier): void {
                $query->where('registration_no', $identifier);
            })
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'identifier' => __('app.auth.invalid_credentials'),
                ]);
        }

        if (! $user->canSignIn()) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'identifier' => $user->requiresAdminApprovalBeforeLogin()
                        ? __('app.auth.approval_required_before_login')
                        : __('app.auth.invalid_credentials'),
                ]);
        }

        $matchedEntity = $user->availableEntities()
            ->first(function ($entity) use ($identifier): bool {
                return in_array($identifier, array_filter([
                    $entity->registration_no,
                    $entity->email,
                    $entity->national_id,
                ]), true);
            });

        $issuedOtp = $otpService->issueLoginOtp(
            user: $user,
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        $request->session()->put([
            'pending_auth_user_id' => $user->getKey(),
            'pending_auth_identifier' => $identifier,
            'pending_auth_phone' => $issuedOtp['phone'],
            'pending_auth_entity_id' => $matchedEntity?->getKey() ?? $user->primaryEntity()?->getKey(),
        ]);

        if (app()->environment(['local', 'testing']) || $this->shouldAllowDebugFallback()) {
            $request->session()->put('otp_debug_code', $issuedOtp['code']);
        }

        if (! $issuedOtp['sms']['ok'] && ! $this->shouldAllowDebugFallback()) {
            $request->session()->forget([
                'pending_auth_user_id',
                'pending_auth_identifier',
                'pending_auth_phone',
                'pending_auth_entity_id',
                'otp_debug_code',
            ]);

            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'identifier' => __('app.auth.otp_send_failed'),
                ]);
        }

        return redirect()
            ->route('otp.create')
            ->with('status', ! $issuedOtp['sms']['ok'] && $this->shouldAllowDebugFallback()
                ? __('app.auth.otp_debug_fallback_status')
                : __('app.auth.otp_sent'));
    }
}
