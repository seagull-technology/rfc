<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRoutingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'request_type',
        'approval_code',
        'target_entity_id',
        'conditions',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'target_entity_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ApprovalRoutingRuleAudit::class)->latest();
    }

    public function authorityApprovals(): HasMany
    {
        return $this->hasMany(ApplicationAuthorityApproval::class, 'approval_routing_rule_id');
    }

    public function localizedApproval(): string
    {
        return __('app.applications.required_approval_options.'.$this->approval_code);
    }
}
