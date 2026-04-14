<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InboxMessageNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $routeParameters
     */
    public function __construct(
        private readonly string $typeKey,
        private readonly string $title,
        private readonly string $body,
        private readonly string $routeName,
        private readonly array $routeParameters = [],
        private readonly array $meta = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type_key' => $this->typeKey,
            'title' => $this->title,
            'body' => $this->body,
            'route_name' => $this->routeName,
            'route_parameters' => $this->routeParameters,
            ...$this->meta,
        ];
    }
}
