<?php

namespace App\Notifications;

use App\Models\Entity;
use App\Notifications\Channels\SmsNotificationChannel;
use App\Notifications\Concerns\QueuesRegistrationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class RegistrationApprovedNotification extends Notification implements ShouldQueue
{
    use QueuesRegistrationDelivery;

    private readonly string $loginUrl;

    public function __construct(
        Entity $entity,
        private readonly ?string $note = null,
    ) {
        $this->initializeDelivery($entity);
        $this->loginUrl = route('login');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SmsNotificationChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type_key' => 'registration_approved',
            'title' => __('app.notifications.registration_approved_title'),
            'body' => __('app.notifications.registration_approved_body', [
                'entity' => $this->registrationDisplayName(),
            ]),
            'route_name' => 'dashboard',
            'route_parameters' => [],
            'entity_id' => $this->registrationSnapshot['id'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('app.notifications.registration_approved_mail_subject'))
            ->line(__('app.notifications.registration_approved_mail_intro', [
                'entity' => $this->registrationDisplayName(),
            ]));

        if (filled($this->note)) {
            $message->line(__('app.notifications.registration_approved_mail_note', [
                'note' => $this->note,
            ]));
        }

        return $message
            ->action(__('app.notifications.registration_approved_mail_action'), $this->loginUrl)
            ->line(__('app.notifications.registration_approved_mail_outro'));
    }

    public function toSms(object $notifiable): string
    {
        return Str::limit($this->auditTitle($notifiable).' - '.$this->auditBody($notifiable).' '.$this->loginUrl, 480, '');
    }

    public function auditTypeKey(object $notifiable): string
    {
        return 'registration_approved';
    }

    public function auditTitle(object $notifiable): string
    {
        return __('app.notifications.registration_approved_title');
    }

    public function auditBody(object $notifiable): string
    {
        return __('app.notifications.registration_approved_body', [
            'entity' => $this->registrationDisplayName(),
        ]);
    }

    public function auditUrl(object $notifiable): string
    {
        return $this->loginUrl;
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
}
