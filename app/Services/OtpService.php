<?php

namespace App\Services;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const RESEND_COOLDOWN_SECONDS = 300;

    public function __construct(
        private readonly SmsService $smsService,
        private readonly NotificationLogService $notificationLogService,
    ) {}

    /**
     * @return array{otp: LoginOtp, code: string, phone: string, sms: array<string, mixed>}
     */
    public function issueLoginOtp(User $user, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        return $this->issueOtp(
            user: $user,
            purpose: self::PURPOSE_LOGIN,
            notificationType: 'login_otp',
            notificationTitle: __('app.admin.notification_center.login_otp_title'),
            notificationBody: __('app.admin.notification_center.login_otp_body'),
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    /**
     * @return array{otp: LoginOtp, code: string, phone: string, sms: array<string, mixed>}
     */
    public function issuePasswordResetOtp(User $user, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        return $this->issueOtp(
            user: $user,
            purpose: self::PURPOSE_PASSWORD_RESET,
            notificationType: 'password_reset_otp',
            notificationTitle: __('app.admin.notification_center.password_reset_otp_title'),
            notificationBody: __('app.admin.notification_center.password_reset_otp_body'),
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    /**
     * @return array{otp: LoginOtp, code: string, phone: string, sms: array<string, mixed>}
     */
    private function issueOtp(
        User $user,
        string $purpose,
        string $notificationType,
        string $notificationTitle,
        string $notificationBody,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $phone = $user->phone ?? '';
        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        LoginOtp::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->delete();

        $otp = LoginOtp::query()->create([
            'user_id' => $user->getKey(),
            'phone' => $phone,
            'purpose' => $purpose,
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

        $this->notificationLogService->recordManual([
            'notifiable' => $user,
            'notification_type' => $notificationType,
            'type_key' => $notificationType,
            'channel' => 'sms',
            'title' => $notificationTitle,
            'body' => $notificationBody,
            'recipient_phone' => $sms['msisdn'] ?? $phone,
            'response' => $sms,
        ]);

        return [
            'otp' => $otp,
            'code' => $code,
            'phone' => $sms['msisdn'] ?? $phone,
            'sms' => $sms,
        ];
    }

    public function resendAvailableIn(User $user, string $purpose = self::PURPOSE_LOGIN): int
    {
        $otp = LoginOtp::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first(['sent_at']);

        if (! $otp?->sent_at) {
            return 0;
        }

        return max(
            0,
            $otp->sent_at
                ->copy()
                ->addSeconds(self::RESEND_COOLDOWN_SECONDS)
                ->timestamp - now()->timestamp,
        );
    }

    public function verifyLoginOtp(User $user, string $code): bool
    {
        return $this->verifyOtp($user, $code, self::PURPOSE_LOGIN);
    }

    public function verifyPasswordResetOtp(User $user, string $code): bool
    {
        return $this->verifyOtp($user, $code, self::PURPOSE_PASSWORD_RESET);
    }

    private function verifyOtp(User $user, string $code, string $purpose): bool
    {
        $otp = LoginOtp::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
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
