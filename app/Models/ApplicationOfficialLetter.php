<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApplicationOfficialLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'application_authority_approval_id',
        'target_entity_id',
        'recipient_type',
        'created_by_user_id',
        'updated_by_user_id',
        'letter_date',
        'serial_number',
        'recipient_prefix',
        'recipient_name',
        'subject',
        'body',
        'attachments',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'attachments' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function authorityApproval(): BelongsTo
    {
        return $this->belongsTo(ApplicationAuthorityApproval::class, 'application_authority_approval_id');
    }

    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'target_entity_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function localizedStatus(): string
    {
        return __('app.official_letters.statuses.'.Str::lower($this->status ?: 'draft'));
    }

    public function isApplicantLetter(): bool
    {
        return $this->recipient_type === 'applicant';
    }

    public function recipientDisplayName(): string
    {
        if ($this->isApplicantLetter()) {
            return $this->targetEntity?->displayName() ?? __('app.official_letters.applicant_recipient');
        }

        return $this->targetEntity?->displayName() ?? __('app.dashboard.not_available');
    }
}
