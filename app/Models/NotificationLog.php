<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'notification_id',
        'database_notification_id',
        'notification_type',
        'type_key',
        'channel',
        'status',
        'notifiable_type',
        'notifiable_id',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'title',
        'body',
        'context_type',
        'context_id',
        'route_name',
        'route_parameters',
        'url',
        'response',
        'error',
        'attempted_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'route_parameters' => 'array',
            'response' => 'array',
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function localizedChannel(): string
    {
        return __('app.admin.notification_center.channels.'.($this->channel ?: 'unknown'));
    }

    public function localizedStatus(): string
    {
        return __('app.admin.notification_center.statuses.'.($this->status ?: 'pending'));
    }
}
