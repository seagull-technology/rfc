<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRoutingRuleAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_routing_rule_id',
        'changed_by_user_id',
        'rule_name',
        'action',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRoutingRule::class, 'approval_routing_rule_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function localizedAction(): string
    {
        $key = 'app.admin.approval_routing.audit_actions.'.$this->action;
        $translated = __($key);

        return $translated === $key ? str($this->action)->replace('_', ' ')->title()->toString() : $translated;
    }
}
