<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendPasswordResetOtp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $backoff = 15;

    public bool $failOnTimeout = true;

    public readonly int $requestedAt;

    public function __construct(
        public readonly int $userId,
        public readonly ?string $ipAddress,
        public readonly string $userAgent,
        ?int $requestedAt = null,
    ) {
        $this->requestedAt = $requestedAt ?? now()->timestamp;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('password-reset-otp:'.$this->userId))
            ->releaseAfter(15)->expireAfter(120)];
    }

    public function handle(OtpService $otpService): void
    {
        // A delayed worker must not deliver an obsolete recovery request.
        if (now()->timestamp - $this->requestedAt >= OtpService::RESEND_COOLDOWN_SECONDS) {
            return;
        }

        $user = User::query()->find($this->userId);

        if (! $user || blank($user->phone)
            || $otpService->resendAvailableIn($user, OtpService::PURPOSE_PASSWORD_RESET) > 0) {
            return;
        }

        $issuedOtp = $otpService->issuePasswordResetOtp(
            user: $user,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
        );

        if (! $issuedOtp['sms']['ok']) {
            $issuedOtp['otp']->delete();
        }
    }
}
