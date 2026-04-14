<?php

namespace App\Services;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function __construct(
        private readonly SmsService $smsService,
    ) {
    }

    /**
     * @return array{otp: \App\Models\LoginOtp, code: string, phone: string, sms: array<string, mixed>}
     */
    public function issueLoginOtp(User $user, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $phone = $user->phone ?? '';
        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        LoginOtp::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', 'login')
            ->whereNull('consumed_at')
            ->delete();

        $otp = LoginOtp::query()->create([
            'user_id' => $user->getKey(),
            'phone' => $phone,
            'purpose' => 'login',
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'sent_at' => now(),
        ]);

        $sms = $this->smsService->send(
            text: __('app.auth.sms_otp_message', ['code' => $code]),
            to: $phone,
        );

        return [
            'otp' => $otp,
            'code' => $code,
            'phone' => $sms['msisdn'] ?? $phone,
            'sms' => $sms,
        ];
    }

    public function verifyLoginOtp(User $user, string $code): bool
    {
        $otp = LoginOtp::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', 'login')
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            if ($otp->attempts >= 5) {
                $otp->forceFill([
                    'consumed_at' => now(),
                ])->save();
            }

            return false;
        }

        $otp->forceFill([
            'consumed_at' => now(),
        ])->save();

        return true;
    }
}
