<?php

namespace App\Notifications\Concerns;

use App\Models\Entity;
use App\Notifications\Channels\SmsNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use LogicException;

trait QueuesRegistrationDelivery
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $backoff = 15;

    public bool $retryFailedSmsDelivery = true;

    /** @var array{id: int, name_en: ?string, name_ar: ?string, status: string} */
    private readonly array $registrationSnapshot;

    private function initializeDelivery(Entity $entity): void
    {
        // Preserve this decision without serializing identities, metadata or owners.
        $this->registrationSnapshot = $entity->only(['id', 'name_en', 'name_ar', 'status']);
        $this->locale(app()->getLocale());
        // Database queue inserts must share the registration transaction.
        $this->beforeCommit();
    }

    private function registrationDisplayName(): string
    {
        return (string) (app()->getLocale() === 'ar'
            ? ($this->registrationSnapshot['name_ar'] ?: $this->registrationSnapshot['name_en'])
            : ($this->registrationSnapshot['name_en'] ?: $this->registrationSnapshot['name_ar']));
    }

    public function viaConnections(): array
    {
        if (config('queue.connections.database.driver') !== 'database'
            || DB::connection(config('queue.connections.database.connection')) !== DB::connection()) {
            throw new LogicException('Registration delivery requires the database queue on the application database connection.');
        }

        // Keep the inbox immediate; each external channel retries independently.
        return ['database' => 'sync', 'mail' => 'database', SmsNotificationChannel::class => 'database'];
    }
}
