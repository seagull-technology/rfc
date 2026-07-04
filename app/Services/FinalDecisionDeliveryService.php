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
        private readonly NotificationLogService $notificationLogService,
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

                $this->recordDeliveryNotification($application, 'mail', 'sent', $targetEmail, null, null);
            } catch (Throwable $exception) {
                Log::error('Final decision email delivery failed', ['application_id' => $application->getKey(), 'message' => $exception->getMessage()]);

                $this->createAudit($permit, $application, $actor, 'delivered', 'email', 'failed', $exception->getMessage(), [
                    'target' => $targetEmail,
                ]);

                $this->recordDeliveryNotification($application, 'mail', 'failed', $targetEmail, null, $exception->getMessage());
            }
        } else {
            $this->createAudit($permit, $application, $actor, 'delivered', 'email', 'skipped', __('app.permit_audits.messages.email_skipped'), []);
            $this->recordDeliveryNotification($application, 'mail', 'skipped', null, null, __('app.permit_audits.messages.email_skipped'));
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

            $this->recordDeliveryNotification(
                application: $application,
                channel: 'sms',
                status: ($sms['ok'] ?? false) ? 'sent' : 'failed',
                email: null,
                phone: $sms['msisdn'] ?? $targetPhone,
                error: ($sms['ok'] ?? false) ? null : (($sms['stage'] ?? 'failed')),
                response: $sms,
            );
        } else {
            $this->createAudit($permit, $application, $actor, 'delivered', 'sms', 'skipped', __('app.permit_audits.messages.sms_skipped'), []);
            $this->recordDeliveryNotification($application, 'sms', 'skipped', null, null, __('app.permit_audits.messages.sms_skipped'));
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

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function recordDeliveryNotification(
        Application $application,
        string $channel,
        string $status,
        ?string $email,
        ?string $phone,
        ?string $error,
        ?array $response = null,
    ): void {
        $this->notificationLogService->recordManual([
            'notifiable' => $application->submittedBy,
            'notification_type' => 'final_decision_delivery',
            'type_key' => 'final_decision_delivery',
            'channel' => $channel,
            'status' => $status,
            'title' => __('app.notifications.final_decision_issued_title'),
            'body' => __('app.notifications.final_decision_issued', [
                'decision' => __('app.statuses.'.$application->final_decision_status),
                'permit' => $application->final_permit_number ?? __('app.dashboard.not_available'),
            ]),
            'recipient_email' => $email,
            'recipient_phone' => $phone,
            'context_type' => 'application',
            'context_id' => $application->getKey(),
            'route_name' => 'applications.show',
            'route_parameters' => ['application' => $application->getKey()],
            'url' => route('applications.show', $application),
            'response' => $response,
            'error' => $error,
        ]);
    }
}
