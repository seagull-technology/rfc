<?php

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class SmsNotificationChannel
{
    public function __construct(private readonly SmsService $smsService) {}

    /**
     * @return array<string, mixed>
     */
    public function send(object $notifiable, Notification $notification): array
    {
        $phone = $notifiable->routeNotificationFor('sms', $notification) ?? null;

        if (! filled($phone)) {
            return [
                'ok' => false,
                'stage' => 'missing_phone',
                'msisdn' => null,
            ];
        }

        $message = method_exists($notification, 'toSms')
            ? (string) $notification->toSms($notifiable)
            : $this->fallbackMessage($notifiable, $notification);

        if (! filled($message)) {
            return [
                'ok' => false,
                'stage' => 'empty_message',
                'msisdn' => $phone,
            ];
        }

        $result = $this->smsService->send(Str::limit($message, 480, ''), (string) $phone);

        if (($notification->retryFailedSmsDelivery ?? false) === true
            && ($result['ok'] ?? false) !== true
            && ($result['stage'] ?? null) !== 'invalid_msisdn') {
            // Only opted-in queued notifications retry; never expose provider data.
            throw new RuntimeException('Registration SMS delivery failed.');
        }

        return $result;
    }

    private function fallbackMessage(object $notifiable, Notification $notification): string
    {
        if (! method_exists($notification, 'toArray')) {
            return class_basename($notification);
        }

        $payload = $notification->toArray($notifiable);

        return trim(implode(' - ', array_filter([
            $payload['title'] ?? null,
            $payload['body'] ?? $payload['message'] ?? null,
        ]))) ?: class_basename($notification);
    }
}
