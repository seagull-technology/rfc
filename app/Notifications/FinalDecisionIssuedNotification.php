<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FinalDecisionIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Application $application)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
