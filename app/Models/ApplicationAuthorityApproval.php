<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApplicationAuthorityApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'authority_code',
        'status',
        'note',
        'reviewed_by_user_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function localizedStatus(): string
    {
        return __('app.approvals.statuses.'.Str::lower($this->status ?: 'pending'));
    }

    public function localizedAuthority(): string
    {
        return __('app.applications.required_approval_options.'.$this->authority_code);
    }
}
