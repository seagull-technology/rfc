<?php

namespace App\Notifications;

use App\Models\Entity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Entity $entity,
        private readonly ?string $note = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type_key' => 'registration_approved',
            'title' => __('app.notifications.registration_approved_title'),
            'body' => __('app.notifications.registration_approved_body', [
                'entity' => $this->entity->displayName(),
            ]),
            'route_name' => 'dashboard',
            'route_parameters' => [],
            'entity_id' => $this->entity->getKey(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('app.notifications.registration_approved_mail_subject'))
            ->line(__('app.notifications.registration_approved_mail_intro', [
                'entity' => $this->entity->displayName(),
            ]));

        if (filled($this->note)) {
            $message->line(__('app.notifications.registration_approved_mail_note', [
                'note' => $this->note,
            ]));
        }

        return $message
            ->action(__('app.notifications.registration_approved_mail_action'), route('login'))
            ->line(__('app.notifications.registration_approved_mail_outro'));
    }
}
