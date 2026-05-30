<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleAssignmentAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entity_id',
        'changed_by_user_id',
        'role_name',
        'action',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function localizedAction(): string
    {
        $key = 'app.admin.users.role_audit_actions.'.$this->action;
        $translated = __($key);

        return $translated === $key ? str($this->action)->replace('_', ' ')->title()->toString() : $translated;
    }
}
