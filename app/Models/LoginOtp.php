<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginOtp extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'purpose',
        'code_hash',
        'expires_at',
        'consumed_at',
        'attempts',
        'ip_address',
        'user_agent',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
