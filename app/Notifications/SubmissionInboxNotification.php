<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsNotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use LogicException;

class SubmissionInboxNotification extends InboxMessageNotification implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 90;

    public int $backoff = 15;

    public bool $retryFailedSmsDelivery = true;

    private readonly ?string $submissionUrl;

    public function __construct(
        string $typeKey,
        string $title,
        string $body,
        string $routeName,
        array $routeParameters = [],
        array $meta = [],
    ) {
        parent::__construct($typeKey, $title, $body, $routeName, $routeParameters, $meta);

        // Keep the request's message, locale and action URL when the worker runs.
        $this->submissionUrl = parent::auditUrl(new \stdClass);
        $this->locale(app()->getLocale());
        // Persist the jobs inside the same transaction as the submitted request.
        $this->beforeCommit();
    }

    public function viaConnections(): array
    {
        if (config('queue.connections.database.driver') !== 'database'
            || DB::connection(config('queue.connections.database.connection')) !== DB::connection()) {
            throw new LogicException('Submission delivery requires the database queue on the application database connection.');
        }

        return ['database' => 'sync', 'mail' => 'database', SmsNotificationChannel::class => 'database'];
    }

    public function auditUrl(object $notifiable): ?string
    {
        return $this->submissionUrl;
    }
}
