<?php

namespace App\Notifications;

use App\Models\Application;
use App\Notifications\Channels\SmsNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ForeignProducerInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Application $application,
        private readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SmsNotificationChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type_key' => 'foreign_producer_invitation',
            'title' => __('app.notifications.foreign_producer_invitation_title'),
            'body' => $this->body(),
            'url' => route('login'),
            'application_id' => $this->application->getKey(),
            'application_code' => $this->application->code,
            'project_name' => $this->application->project_name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.notifications.foreign_producer_invitation_mail_subject'))
            ->greeting(__('app.notifications.foreign_producer_invitation_mail_greeting', [
                'name' => $notifiable->displayName(),
            ]))
            ->line(__('app.notifications.foreign_producer_invitation_mail_intro', [
                'project' => $this->application->project_name,
                'code' => $this->application->code,
            ]))
            ->line(__('app.notifications.foreign_producer_invitation_mail_username', [
                'username' => $notifiable->username,
            ]))
            ->line(__('app.notifications.foreign_producer_invitation_mail_security'))
            ->action(
                __('app.notifications.foreign_producer_invitation_mail_action'),
                $this->activationUrl($notifiable),
            )
            ->line(__('app.notifications.foreign_producer_invitation_mail_expiry', [
                'minutes' => (int) config('auth.passwords.users.expire', 60),
            ]));
    }

    public function toSms(object $notifiable): string
    {
        return Str::limit(
            $this->auditTitle($notifiable).' - '.$this->body().' '.$this->activationUrl($notifiable),
            480,
            '',
        );
    }

    public function auditTypeKey(object $notifiable): string
    {
        return 'foreign_producer_invitation';
    }

    public function auditTitle(object $notifiable): string
    {
        return __('app.notifications.foreign_producer_invitation_title');
    }

    public function auditBody(object $notifiable): string
    {
        return $this->body();
    }

    public function auditUrl(object $notifiable): string
    {
        return route('login');
    }

    /**
     * @return array{type: string, id: int|null}
     */
    public function auditContext(object $notifiable): array
    {
        return [
            'type' => 'application',
            'id' => $this->application->getKey(),
        ];
    }

    private function body(): string
    {
        return __('app.notifications.foreign_producer_invitation_body', [
            'project' => $this->application->project_name,
            'code' => $this->application->code,
        ]);
    }

    private function activationUrl(object $notifiable): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
            'invitation' => 1,
        ]);
    }
}
