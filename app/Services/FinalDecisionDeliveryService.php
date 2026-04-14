<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Permit;
use App\Models\PermitAudit;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FinalDecisionDeliveryService
{
    public function __construct(
        private readonly SmsService $smsService,
    ) {
    }

    public function logIssuance(Permit $permit, Application $application, ?User $actor, string $action): void
    {
        $this->createAudit(
            permit: $permit,
            application: $application,
            actor: $actor,
            action: $action,
            channel: 'system',
            status: 'logged',
            message: __('app.permit_audits.messages.issuance_logged'),
            metadata: [
                'permit_number' => $permit->permit_number,
                'decision_status' => $application->final_decision_status,
            ],
        );
    }

    public function deliver(Application $application, ?Permit $permit, ?User $actor): void
    {
        if (! $permit) {
            return;
        }

        $targetEmail = $application->entity?->email ?: $application->submittedBy?->email;
        $targetPhone = $application->entity?->phone ?: $application->submittedBy?->phone;

        if (filled($targetEmail)) {
            try {
                Mail::raw($this->emailMessage($application, $permit), function ($message) use ($targetEmail, $application): void {
                    $message
                        ->to($targetEmail)
                        ->subject(__('app.final_decision.email_subject', ['code' => $application->code]));
                });

                $this->createAudit($permit, $application, $actor, 'delivered', 'email', 'success', __('app.permit_audits.messages.email_sent'), [
                    'target' => $targetEmail,
                ]);
            } catch (Throwable $exception) {
                Log::error('Final decision email delivery failed', ['application_id' => $application->getKey(), 'message' => $exception->getMessage()]);

                $this->createAudit($permit, $application, $actor, 'delivered', 'email', 'failed', $exception->getMessage(), [
                    'target' => $targetEmail,
                ]);
            }
        } else {
            $this->createAudit($permit, $application, $actor, 'delivered', 'email', 'skipped', __('app.permit_audits.messages.email_skipped'), []);
        }

        if (filled($targetPhone)) {
            $sms = $this->smsService->send($this->smsMessage($application, $permit), $targetPhone);

            $this->createAudit(
                permit: $permit,
                application: $application,
                actor: $actor,
                action: 'delivered',
                channel: 'sms',
                status: ($sms['ok'] ?? false) ? 'success' : 'failed',
                message: ($sms['ok'] ?? false) ? __('app.permit_audits.messages.sms_sent') : (($sms['stage'] ?? 'failed')),
                metadata: [
                    'target' => $targetPhone,
                    'http' => $sms['http'] ?? null,
                    'stage' => $sms['stage'] ?? null,
                    'msisdn' => $sms['msisdn'] ?? null,
                ],
            );
        } else {
            $this->createAudit($permit, $application, $actor, 'delivered', 'sms', 'skipped', __('app.permit_audits.messages.sms_skipped'), []);
        }
    }

    private function emailMessage(Application $application, Permit $permit): string
    {
        return __('app.final_decision.email_body', [
            'project' => $application->project_name,
            'code' => $application->code,
            'decision' => __('app.statuses.'.$application->final_decision_status),
            'permit' => $permit->permit_number,
        ]);
    }

    private function smsMessage(Application $application, Permit $permit): string
    {
        return __('app.final_decision.sms_body', [
            'project' => $application->project_name,
            'permit' => $permit->permit_number,
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function createAudit(Permit $permit, Application $application, ?User $actor, string $action, string $channel, string $status, ?string $message, array $metadata): void
    {
        PermitAudit::query()->create([
            'permit_id' => $permit->getKey(),
            'application_id' => $application->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'channel' => $channel,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata,
            'happened_at' => now(),
        ]);
    }
}
