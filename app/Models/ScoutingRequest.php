<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ScoutingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'entity_id',
        'submitted_by_user_id',
        'project_name',
        'project_nationality',
        'scout_start_date',
        'scout_end_date',
        'production_start_date',
        'production_end_date',
        'project_summary',
        'story_text',
        'story_file_path',
        'story_file_name',
        'story_file_mime_type',
        'status',
        'current_stage',
        'review_note',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scout_start_date' => 'date',
            'scout_end_date' => 'date',
            'production_start_date' => 'date',
            'production_end_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ScoutingRequestStatusHistory::class)->orderByDesc('happened_at');
    }

    public function correspondences(): HasMany
    {
        return $this->hasMany(ScoutingRequestCorrespondence::class)->latest();
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

    public function canReceiveApplicantCorrespondence(): bool
    {
        return ! in_array($this->status, ['approved', 'rejected'], true);
    }
}
