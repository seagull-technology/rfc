<?php

namespace App\Notifications;

use App\Models\Application;
use App\Notifications\Channels\SmsNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class FinalDecisionIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Application $application)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SmsNotificationChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->auditTitle($notifiable))
            ->line($this->auditBody($notifiable))
            ->action(__('app.notifications.open_action'), $this->auditUrl($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        return Str::limit($this->auditTitle($notifiable).' - '.$this->auditBody($notifiable).' '.$this->auditUrl($notifiable), 480, '');
    }

    public function toArray(object $notifiable): array
    {
        $routeName = $notifiable->canAccessAdminPanel($notifiable->primaryEntity())
            ? 'admin.applications.show'
            : 'applications.show';

        return [
            'type_key' => 'final_decision_issued',
            'application_id' => $this->application->getKey(),
            'route_name' => $routeName,
            'route_parameters' => [
                'application' => $this->application->getKey(),
            ],
            'project_name' => $this->application->project_name,
            'decision_status' => $this->application->final_decision_status,
            'permit_number' => $this->application->final_permit_number,
        ];
    }

    public function auditTypeKey(object $notifiable): string
    {
        return 'final_decision_issued';
    }

    public function auditTitle(object $notifiable): string
    {
        return __('app.notifications.final_decision_issued_title');
    }

    public function auditBody(object $notifiable): string
    {
        return __('app.notifications.final_decision_issued', [
            'decision' => __('app.statuses.'.$this->application->final_decision_status),
            'permit' => $this->application->final_permit_number ?? __('app.dashboard.not_available'),
        ]);
    }

    public function auditRouteName(object $notifiable): string
    {
        return $this->routeName($notifiable);
    }

    /**
     * @return array{application: int}
     */
    public function auditRouteParameters(object $notifiable): array
    {
        return [
            'application' => $this->application->getKey(),
        ];
    }

    public function auditUrl(object $notifiable): string
    {
        return route($this->routeName($notifiable), $this->auditRouteParameters($notifiable));
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

    private function routeName(object $notifiable): string
    {
        return $notifiable->canAccessAdminPanel($notifiable->primaryEntity())
            ? 'admin.applications.show'
            : 'applications.show';
    }
}
