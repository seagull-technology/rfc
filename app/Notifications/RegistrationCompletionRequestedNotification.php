<?php

namespace App\Notifications;

use App\Models\Entity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class RegistrationCompletionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Entity $entity,
        private readonly string $decision,
        private readonly ?string $note = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $translationPrefix = $this->decision === 'reject'
            ? 'registration_rejected'
            : 'registration_completion_requested';

        return [
            'type_key' => 'registration_completion_requested',
            'title' => __('app.notifications.'.$translationPrefix.'_title'),
            'body' => __('app.notifications.'.$translationPrefix.'_body', [
                'entity' => $this->entity->displayName(),
                'status' => __('app.statuses.'.$this->entity->status),
            ]),
            'url' => $this->signedUrl(),
            'entity_id' => $this->entity->getKey(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $translationPrefix = $this->decision === 'reject'
            ? 'registration_rejected'
            : 'registration_completion_requested';

        $message = (new MailMessage)
            ->subject(__('app.notifications.'.$translationPrefix.'_mail_subject'))
            ->line(__('app.notifications.'.$translationPrefix.'_mail_intro', [
                'entity' => $this->entity->displayName(),
            ]))
            ->line(__('app.notifications.'.$translationPrefix.'_mail_status', [
                'status' => __('app.statuses.'.$this->entity->status),
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

    private function signedUrl(): string
    {
        return URL::temporarySignedRoute('registration.completion.link.edit', now()->addDays(7), [
            'entity' => $this->entity->getKey(),
        ]);
    }
}
