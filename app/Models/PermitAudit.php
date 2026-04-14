<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PermitAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'permit_id',
        'application_id',
        'user_id',
        'action',
        'channel',
        'status',
        'message',
        'metadata',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'happened_at' => 'datetime',
        ];
    }

    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function localizedStatus(): string
    {
        return __('app.permit_audits.statuses.'.Str::lower($this->status ?: 'logged'));
    }

    public function localizedAction(): string
    {
        return __('app.permit_audits.actions.'.Str::lower($this->action ?: 'issued'));
    }

    public function localizedChannel(): string
    {
        return __('app.permit_audits.channels.'.Str::lower($this->channel ?: 'system'));
    }
}
