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
        $nameReplacements = [
            'name' => $notifiable->displayName(),
        ];
        $projectReplacements = [
            'project' => $this->application->project_name,
            'code' => $this->application->code,
        ];
        $usernameReplacements = [
            'username' => $notifiable->username,
        ];
        $expiryReplacements = [
            'minutes' => (int) config('auth.passwords.users.expire', 60),
        ];

        return (new MailMessage)
            ->subject($this->mailText('foreign_producer_invitation_mail_subject', [], 'ar')
                .' | '.$this->mailText('foreign_producer_invitation_mail_subject', [], 'en'))
            ->greeting($this->mailText('foreign_producer_invitation_mail_greeting', $nameReplacements, 'ar'))
            ->line($this->mailText('foreign_producer_invitation_mail_intro', $projectReplacements, 'ar'))
            ->line($this->mailText('foreign_producer_invitation_mail_username', $usernameReplacements, 'ar'))
            ->line($this->mailText('foreign_producer_invitation_mail_security', [], 'ar'))
            ->line($this->mailText('foreign_producer_invitation_mail_expiry', $expiryReplacements, 'ar'))
            ->line('English')
            ->line($this->mailText('foreign_producer_invitation_mail_greeting', $nameReplacements, 'en'))
            ->line($this->mailText('foreign_producer_invitation_mail_intro', $projectReplacements, 'en'))
            ->line($this->mailText('foreign_producer_invitation_mail_username', $usernameReplacements, 'en'))
            ->line($this->mailText('foreign_producer_invitation_mail_security', [], 'en'))
            ->line($this->mailText('foreign_producer_invitation_mail_expiry', $expiryReplacements, 'en'))
            ->action(
                $this->mailText('foreign_producer_invitation_mail_action', [], 'ar')
                    .' | '.$this->mailText('foreign_producer_invitation_mail_action', [], 'en'),
                $this->activationUrl($notifiable),
            );
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

    /**
     * @param  array<string, mixed>  $replacements
     */
    private function mailText(string $key, array $replacements, string $locale): string
    {
        return trans('app.notifications.'.$key, $replacements, $locale);
    }
}
