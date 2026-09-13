<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsNotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProfileChangeInboxNotification extends InboxMessageNotification implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 90;

    public int $backoff = 15;

    public bool $retryFailedSmsDelivery = true;

    private readonly ?string $profileUrl;

    public function __construct(
        string $typeKey,
        string $title,
        string $body,
        string $routeName,
        int $entityId,
        string $requestKey,
    ) {
        parent::__construct(
            $typeKey,
            $title,
            $body,
            $routeName,
            $routeName === 'admin.entities.show' ? ['entity' => $entityId] : [],
            ['entity_id' => $entityId, 'profile_change_request_id' => $requestKey],
        );

        // Capture the message and link without retaining profile fields or models.
        $this->profileUrl = parent::auditUrl(new \stdClass);
        $this->locale(app()->getLocale());
        $this->beforeCommit();
    }

    public function viaConnections(): array
    {
        if (config('queue.connections.database.driver') !== 'database'
            || DB::connection(config('queue.connections.database.connection')) !== DB::connection()) {
            throw new LogicException('Profile change delivery requires the database queue on the application database connection.');
        }

        return ['database' => 'sync', 'mail' => 'database', SmsNotificationChannel::class => 'database'];
    }

    public function auditUrl(object $notifiable): ?string
    {
        return $this->profileUrl;
    }
}
