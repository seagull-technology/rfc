<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ScoutingRequestStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'scouting_request_id',
        'user_id',
        'status',
        'note',
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

    public function scoutingRequest(): BelongsTo
    {
        return $this->belongsTo(ScoutingRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function localizedStatus(): string
    {
        return __('app.statuses.'.Str::lower($this->status));
    }
}
