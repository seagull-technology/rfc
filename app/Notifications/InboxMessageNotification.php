<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

class InboxMessageNotification extends Notification
{
    use Queueable;

    /**
     * Protected fields allow Laravel to restore queued subclasses on PHP 8.3.
     *
     * @param  array<string, mixed>  $routeParameters
     */
    public function __construct(
        protected string $typeKey,
        protected string $title,
        protected string $body,
        protected string $routeName,
        protected array $routeParameters = [],
        protected array $meta = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SmsNotificationChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title)
            ->line($this->body);

        if ($url = $this->auditUrl($notifiable)) {
            $message->action(__('app.notifications.open_action'), $url);
        }

        return $message;
    }

    public function toSms(object $notifiable): string
    {
        return Str::limit(trim(implode(' - ', array_filter([
            $this->title,
            $this->body,
            $this->auditUrl($notifiable),
        ]))), 480, '');
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

    public function auditTypeKey(object $notifiable): string
    {
        return $this->typeKey;
    }

    public function auditTitle(object $notifiable): string
    {
        return $this->title;
    }

    public function auditBody(object $notifiable): string
    {
        return $this->body;
    }

    public function auditRouteName(object $notifiable): string
    {
        return $this->routeName;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditRouteParameters(object $notifiable): array
    {
        return $this->routeParameters;
    }

    public function auditUrl(object $notifiable): ?string
    {
        if (! Route::has($this->routeName)) {
            return null;
        }

        try {
            return route($this->routeName, $this->routeParameters);
        } catch (Throwable) {
            return null;
        }
    }
}
