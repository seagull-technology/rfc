<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class Permit extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'entity_id',
        'permit_number',
        'status',
        'issued_at',
        'issued_by_user_id',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PermitAudit::class)->latest('happened_at');
    }

    public function localizedStatus(): string
    {
        return __('app.permits.statuses.'.Str::lower($this->status ?: 'active'));
    }

    public function verificationUrl(): string
    {
        return URL::signedRoute('permits.verify.signed', ['permit' => $this]);
    }
}
