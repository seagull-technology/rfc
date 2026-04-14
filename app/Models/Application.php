<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'entity_id',
        'submitted_by_user_id',
        'project_name',
        'project_nationality',
        'work_category',
        'release_method',
        'planned_start_date',
        'planned_end_date',
        'estimated_crew_count',
        'estimated_budget',
        'project_summary',
        'status',
        'current_stage',
        'review_note',
        'final_decision_status',
        'final_decision_note',
        'final_permit_number',
        'final_letter_path',
        'final_letter_name',
        'final_letter_mime_type',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'final_decision_issued_at',
        'final_decision_issued_by_user_id',
        'assigned_to_user_id',
        'assigned_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'final_decision_issued_at' => 'datetime',
            'assigned_at' => 'datetime',
            'estimated_budget' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function finalDecisionIssuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_decision_issued_by_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderByDesc('happened_at');
    }

    public function authorityApprovals(): HasMany
    {
        return $this->hasMany(ApplicationAuthorityApproval::class)->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->latest();
    }

    public function permit(): HasOne
    {
        return $this->hasOne(Permit::class);
    }

    public function correspondences(): HasMany
    {
        return $this->hasMany(ApplicationCorrespondence::class)->latest();
    }

    public function localizedStatus(): string
    {
        return __('app.statuses.'.Str::lower($this->status ?: 'draft'));
    }

    public function localizedStage(): string
    {
        return __('app.workflow.stages.'.Str::lower($this->current_stage ?: 'draft'));
    }

    public function canBeEditedByApplicant(): bool
    {
        return in_array($this->status, ['draft', 'needs_clarification'], true);
    }

    public function canBeSubmittedByApplicant(): bool
    {
        return $this->canBeEditedByApplicant();
    }

    public function canReceiveApplicantDocuments(): bool
    {
        return ! in_array($this->status, ['approved', 'rejected'], true);
    }

    public function finalDecisionIssued(): bool
    {
        return filled($this->final_decision_status) && $this->final_decision_issued_at !== null;
    }

    public function hasResolvedAuthorityApprovals(): bool
    {
        if (! $this->relationLoaded('authorityApprovals')) {
            return ! $this->authorityApprovals()
                ->whereIn('status', ['pending', 'in_review'])
                ->exists();
        }

        return ! $this->authorityApprovals->contains(fn (ApplicationAuthorityApproval $approval): bool => in_array($approval->status, ['pending', 'in_review'], true));
    }

    public function hasRejectedAuthorityApproval(): bool
    {
        if (! $this->relationLoaded('authorityApprovals')) {
            return $this->authorityApprovals()
                ->where('status', 'rejected')
                ->exists();
        }

        return $this->authorityApprovals->contains(fn (ApplicationAuthorityApproval $approval): bool => $approval->status === 'rejected');
    }

    public function canBeFinallyDecided(): bool
    {
        if (! in_array($this->status, ['submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'], true)) {
            return false;
        }

        return $this->hasResolvedAuthorityApprovals();
    }
}
