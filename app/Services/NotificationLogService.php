<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Notifications\Channels\SmsNotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NotificationLogService
{
    public function recordSending(object $notifiable, object $notification, string $channel): NotificationLog
    {
        $channelName = $this->channelName($channel);
        $payload = array_merge($this->payload($notifiable, $notification, $channelName), [
            'status' => 'pending',
            'attempted_at' => now(),
            'sent_at' => null,
            'failed_at' => null,
            'error' => null,
        ]);

        $existing = $this->pendingLog($notifiable, $notification, $channelName);

        if ($existing) {
            $existing->forceFill($payload)->save();

            return $existing;
        }

        return NotificationLog::query()->create($payload);
    }

    public function recordSent(object $notifiable, object $notification, string $channel, mixed $response = null): NotificationLog
    {
        $channelName = $this->channelName($channel);
        $status = $this->statusForResponse($channelName, $response);
        $payload = array_merge($this->payload($notifiable, $notification, $channelName), [
            'status' => $status,
            'database_notification_id' => $this->databaseNotificationId($response),
            'response' => $this->responsePayload($response),
            'error' => $status === 'failed' ? $this->responseError($response) : null,
            'attempted_at' => now(),
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
        ]);

        $log = $this->pendingLog($notifiable, $notification, $channelName) ?? new NotificationLog();
        $log->forceFill($payload)->save();

        return $log;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordFailed(object $notifiable, object $notification, string $channel, array $data = []): NotificationLog
    {
        $channelName = $this->channelName($channel);
        $exception = $data['exception'] ?? null;
        $payload = array_merge($this->payload($notifiable, $notification, $channelName), [
            'status' => 'failed',
            'response' => $this->responsePayload($data),
            'error' => $exception instanceof Throwable ? $exception->getMessage() : ($data['message'] ?? null),
            'attempted_at' => now(),
            'sent_at' => null,
            'failed_at' => now(),
        ]);

        $log = $this->pendingLog($notifiable, $notification, $channelName) ?? new NotificationLog();
        $log->forceFill($payload)->save();

        return $log;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordManual(array $attributes): ?NotificationLog
    {
        $notifiable = $attributes['notifiable'] ?? null;
        $response = $attributes['response'] ?? null;
        $status = $attributes['status'] ?? $this->statusForResponse((string) ($attributes['channel'] ?? 'system'), $response);

        try {
            return NotificationLog::query()->create([
                'notification_id' => $attributes['notification_id'] ?? null,
                'database_notification_id' => $attributes['database_notification_id'] ?? null,
                'notification_type' => $attributes['notification_type'] ?? 'manual',
                'type_key' => $attributes['type_key'] ?? null,
                'channel' => $this->channelName((string) ($attributes['channel'] ?? 'system')),
                'status' => $status,
                ...$this->notifiablePayload($notifiable),
                'recipient_name' => $attributes['recipient_name'] ?? $this->recipientName($notifiable),
                'recipient_email' => $attributes['recipient_email'] ?? ($notifiable?->email ?? null),
                'recipient_phone' => $attributes['recipient_phone'] ?? ($notifiable?->phone ?? null),
                'title' => $attributes['title'] ?? null,
                'body' => $attributes['body'] ?? null,
                'context_type' => $attributes['context_type'] ?? null,
                'context_id' => $attributes['context_id'] ?? null,
                'route_name' => $attributes['route_name'] ?? null,
                'route_parameters' => $attributes['route_parameters'] ?? null,
                'url' => $attributes['url'] ?? null,
                'response' => $this->responsePayload($response),
                'error' => $attributes['error'] ?? ($status === 'failed' ? $this->responseError($response) : null),
                'attempted_at' => $attributes['attempted_at'] ?? now(),
                'sent_at' => $status === 'sent' ? ($attributes['sent_at'] ?? now()) : null,
                'failed_at' => $status === 'failed' ? ($attributes['failed_at'] ?? now()) : null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Manual notification audit logging failed', [
                'message' => $exception->getMessage(),
                'type_key' => $attributes['type_key'] ?? null,
                'channel' => $attributes['channel'] ?? null,
            ]);

            return null;
        }
    }

    private function pendingLog(object $notifiable, object $notification, string $channel): ?NotificationLog
    {
        $notificationId = $notification->id ?? null;

        return NotificationLog::query()
            ->when($notificationId, fn ($query) => $query->where('notification_id', $notificationId))
            ->where('channel', $channel)
            ->where('status', 'pending')
            ->where('notifiable_type', $this->notifiablePayload($notifiable)['notifiable_type'])
            ->where('notifiable_id', $this->notifiablePayload($notifiable)['notifiable_id'])
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(object $notifiable, object $notification, string $channel): array
    {
        $arrayPayload = $this->notificationArray($notifiable, $notification);
        $context = method_exists($notification, 'auditContext')
            ? (array) $notification->auditContext($notifiable)
            : $this->contextFromPayload($arrayPayload);

        $routeName = method_exists($notification, 'auditRouteName')
            ? $notification->auditRouteName($notifiable)
            : ($arrayPayload['route_name'] ?? null);
        $routeParameters = method_exists($notification, 'auditRouteParameters')
            ? $notification->auditRouteParameters($notifiable)
            : ($arrayPayload['route_parameters'] ?? null);

        return [
            'notification_id' => $notification->id ?? null,
            'notification_type' => $notification::class,
            'type_key' => method_exists($notification, 'auditTypeKey')
                ? $notification->auditTypeKey($notifiable)
                : ($arrayPayload['type_key'] ?? class_basename($notification)),
            'channel' => $channel,
            ...$this->notifiablePayload($notifiable),
            'recipient_name' => $this->recipientName($notifiable),
            'recipient_email' => $notifiable->email ?? null,
            'recipient_phone' => $notifiable->phone ?? null,
            'title' => method_exists($notification, 'auditTitle')
                ? $notification->auditTitle($notifiable)
                : ($arrayPayload['title'] ?? class_basename($notification)),
            'body' => method_exists($notification, 'auditBody')
                ? $notification->auditBody($notifiable)
                : ($arrayPayload['body'] ?? $arrayPayload['message'] ?? null),
            'context_type' => $context['type'] ?? null,
            'context_id' => $context['id'] ?? null,
            'route_name' => $routeName,
            'route_parameters' => $routeParameters,
            'url' => method_exists($notification, 'auditUrl')
                ? $notification->auditUrl($notifiable)
                : ($arrayPayload['url'] ?? $this->urlForRoute($routeName, $routeParameters)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notifiablePayload(mixed $notifiable): array
    {
        if (! $notifiable instanceof Model) {
            return [
                'notifiable_type' => null,
                'notifiable_id' => null,
            ];
        }

        return [
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationArray(object $notifiable, object $notification): array
    {
        if (! method_exists($notification, 'toArray')) {
            return [];
        }

        try {
            $payload = $notification->toArray($notifiable);

            return is_array($payload) ? $payload : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type?: string, id?: int|string|null}
     */
    private function contextFromPayload(array $payload): array
    {
        foreach (['application', 'scouting_request', 'entity', 'permit'] as $key) {
            $id = $payload[$key.'_id'] ?? $payload[$key] ?? null;

            if ($id !== null) {
                return [
                    'type' => $key,
                    'id' => $id,
                ];
            }
        }

        return [];
    }

    private function channelName(string $channel): string
    {
        if ($channel === SmsNotificationChannel::class || str_ends_with($channel, '\\SmsNotificationChannel')) {
            return 'sms';
        }

        return match ($channel) {
            'database', 'mail', 'sms', 'system' => $channel,
            default => Str::of(class_basename($channel))->snake()->replace('_notification_channel', '')->toString(),
        };
    }

    private function recipientName(mixed $notifiable): ?string
    {
        if (! is_object($notifiable)) {
            return null;
        }

        if (method_exists($notifiable, 'displayName')) {
            return $notifiable->displayName();
        }

        return $notifiable->name ?? null;
    }

    private function statusForResponse(string $channel, mixed $response): string
    {
        if ($channel === 'sms' && is_array($response) && array_key_exists('ok', $response)) {
            if ($response['ok'] === true) {
                return 'sent';
            }

            return in_array($response['stage'] ?? null, ['missing_phone', 'empty_message'], true)
                ? 'skipped'
                : 'failed';
        }

        return 'sent';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function responsePayload(mixed $response): ?array
    {
        if ($response === null) {
            return null;
        }

        if (is_array($response)) {
            return $this->safeArray($response);
        }

        if ($response instanceof Model) {
            return [
                'model' => $response::class,
                'id' => $response->getKey(),
            ];
        }

        if (is_object($response)) {
            return [
                'class' => $response::class,
                'summary' => method_exists($response, '__toString')
                    ? Str::limit((string) $response, 500)
                    : null,
            ];
        }

        return ['value' => $response];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safeArray(array $payload): array
    {
        return collect($payload)
            ->map(function (mixed $value): mixed {
                if ($value instanceof Throwable) {
                    return [
                        'class' => $value::class,
                        'message' => $value->getMessage(),
                    ];
                }

                if ($value instanceof Model) {
                    return [
                        'model' => $value::class,
                        'id' => $value->getKey(),
                    ];
                }

                if (is_object($value)) {
                    return [
                        'class' => $value::class,
                    ];
                }

                return $value;
            })
            ->all();
    }

    private function responseError(mixed $response): ?string
    {
        if (is_array($response)) {
            return $response['error'] ?? $response['stage'] ?? null;
        }

        return null;
    }

    private function databaseNotificationId(mixed $response): ?string
    {
        if ($response instanceof Model && $response->getTable() === 'notifications') {
            return (string) $response->getKey();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $parameters
     */
    private function urlForRoute(?string $routeName, ?array $parameters): ?string
    {
        if (! $routeName || ! Route::has($routeName)) {
            return null;
        }

        try {
            return route($routeName, $parameters ?? []);
        } catch (Throwable) {
            return null;
        }
    }
}
