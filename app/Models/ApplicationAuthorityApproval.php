<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApplicationAuthorityApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'authority_code',
        'entity_id',
        'approval_routing_rule_id',
        'assigned_user_id',
        'assigned_at',
        'escalated_at',
        'sla_warning_notified_at',
        'status',
        'note',
        'response_attachment_path',
        'response_attachment_name',
        'response_attachment_mime_type',
        'response_attachment_size',
        'response_attachment_uploaded_at',
        'reviewed_by_user_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'escalated_at' => 'datetime',
            'sla_warning_notified_at' => 'datetime',
            'decided_at' => 'datetime',
            'response_attachment_uploaded_at' => 'datetime',
        ];
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function routingRule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRoutingRule::class, 'approval_routing_rule_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ApplicationAuthorityChangeRequest::class)
            ->latest('requested_at')
            ->latest('id');
    }

    public function localizedStatus(): string
    {
        return __('app.approvals.statuses.'.Str::lower($this->status ?: 'pending'));
    }

    public function localizedAuthority(): string
    {
        $approvalName = __('app.applications.required_approval_options.'.$this->authority_code);

        if (! $this->entity) {
            return $approvalName;
        }

        if ($approvalName === 'app.applications.required_approval_options.'.$this->authority_code) {
            return $this->entity->displayName();
        }

        return $this->entity->displayName().' - '.$approvalName;
    }
}
