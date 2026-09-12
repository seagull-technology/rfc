<?php

namespace App\Notifications;

use App\Models\Entity;
use App\Notifications\Channels\SmsNotificationChannel;
use App\Notifications\Concerns\QueuesRegistrationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class RegistrationCompletionRequestedNotification extends Notification implements ShouldQueue
{
    use QueuesRegistrationDelivery;

    private readonly string $completionUrl;

    public function __construct(
        Entity $entity,
        private readonly string $decision,
        private readonly ?string $note = null,
    ) {
        $this->initializeDelivery($entity);
        $this->completionUrl = URL::temporarySignedRoute('registration.completion.link.edit', now()->addDays(7), [
            'entity' => $this->registrationSnapshot['id'],
        ]);
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SmsNotificationChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $translationPrefix = $this->decision === 'reject'
            ? 'registration_rejected'
            : 'registration_completion_requested';

        return [
            'type_key' => $translationPrefix,
            'title' => __('app.notifications.'.$translationPrefix.'_title'),
            'body' => __('app.notifications.'.$translationPrefix.'_body', [
                'entity' => $this->registrationDisplayName(),
                'status' => __('app.statuses.'.$this->registrationSnapshot['status']),
            ]),
            'url' => $this->signedUrl(),
            'entity_id' => $this->registrationSnapshot['id'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $translationPrefix = $this->mailTranslationPrefix();

        $message = (new MailMessage)
            ->subject(__('app.notifications.'.$translationPrefix.'_mail_subject'))
            ->line(__('app.notifications.'.$translationPrefix.'_mail_intro', [
                'entity' => $this->registrationDisplayName(),
            ]))
            ->line(__('app.notifications.'.$translationPrefix.'_mail_status', [
                'status' => __('app.statuses.'.$this->registrationSnapshot['status']),
            ]));

        if (filled($this->note)) {
            $message->line(__('app.notifications.'.$translationPrefix.'_mail_note', [
                'note' => $this->note,
            ]));
        }

        return $message
            ->action(__('app.notifications.'.$translationPrefix.'_mail_action'), $this->signedUrl())
            ->line(__('app.notifications.'.$translationPrefix.'_mail_expiry'));
    }

    public function toSms(object $notifiable): string
    {
        return Str::limit($this->auditTitle($notifiable).' - '.$this->auditBody($notifiable).' '.$this->signedUrl(), 480, '');
    }

    public function auditTypeKey(object $notifiable): string
    {
        return $this->translationPrefix();
    }

    public function auditTitle(object $notifiable): string
    {
        return __('app.notifications.'.$this->translationPrefix().'_title');
    }

    public function auditBody(object $notifiable): string
    {
        return __('app.notifications.'.$this->translationPrefix().'_body', [
            'entity' => $this->registrationDisplayName(),
            'status' => __('app.statuses.'.$this->registrationSnapshot['status']),
        ]);
    }

    public function auditUrl(object $notifiable): string
    {
        return $this->signedUrl();
    }

    /**
     * @return array{type: string, id: int|null}
     */
    public function auditContext(object $notifiable): array
    {
        return [
            'type' => 'entity',
            'id' => $this->registrationSnapshot['id'],
        ];
    }

    private function signedUrl(): string
    {
        return $this->completionUrl;
    }

    private function translationPrefix(): string
    {
        return $this->decision === 'reject'
            ? 'registration_rejected'
            : 'registration_completion_requested';
    }

    private function mailTranslationPrefix(): string
    {
        return $this->decision === 'reject'
            ? 'registration_rejected'
            : 'registration_completion';
    }
}
