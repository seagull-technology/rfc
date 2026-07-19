<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAuthorityChangeRequest extends Model
{
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_RESUBMITTED = 'resubmitted';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'application_id',
        'application_authority_approval_id',
        'section_key',
        'section_label',
        'details',
        'attachment_path',
        'attachment_name',
        'attachment_mime_type',
        'attachment_size',
        'status',
        'requested_by_user_id',
        'requested_at',
        'resubmitted_by_user_id',
        'resubmitted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'resubmitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ApplicationAuthorityApproval::class, 'application_authority_approval_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function resubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resubmitted_by_user_id');
    }
}
