<?php

namespace App\Models;

use App\Support\ApplicationWorkflowRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Entity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'parent_entity_id',
        'code',
        'name_en',
        'name_ar',
        'registration_no',
        'national_id',
        'email',
        'phone',
        'registration_type',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_entity_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_entity_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'entity_user')
            ->withPivot([
                'job_title',
                'is_primary',
                'status',
                'joined_at',
                'left_at',
            ])
            ->withTimestamps();
    }

    /**
     * @return Collection<int, User>
     */
    public function activeMembers(): Collection
    {
        return $this->users()
            ->where('users.status', 'active')
            ->wherePivot('status', 'active')
            ->orderByDesc('entity_user.is_primary')
            ->orderBy('users.name')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    public function authorityDelegationMap(): array
    {
        return collect((array) data_get($this->metadata ?? [], 'authority_delegation.approval_user_map', []))
            ->mapWithKeys(fn (mixed $userId, mixed $approvalCode): array => [(string) $approvalCode => (int) $userId])
            ->filter(fn (int $userId): bool => $userId > 0)
            ->all();
    }

    public function authorityDelegatedUserIdFor(string $approvalCode): ?int
    {
        $userId = $this->authorityDelegationMap()[$approvalCode] ?? null;

        return $userId > 0 ? $userId : null;
    }

    /**
     * @return array{response_time_days:?int,escalation_user_ids:array<int, int>,escalation_role_names:array<int, string>}
     */
    public function authoritySlaSettings(): array
    {
        return [
            'response_time_days' => $this->authorityResponseTimeDays(),
            'escalation_user_ids' => $this->authorityEscalationUserIds(),
            'escalation_role_names' => $this->authorityEscalationRoleNames(),
        ];
    }

    public function authorityResponseTimeDays(): ?int
    {
        $days = data_get($this->metadata ?? [], 'authority_sla.response_time_days');

        return is_numeric($days) && (int) $days > 0
            ? (int) $days
            : null;
    }

    /**
     * @return array<int, int>
     */
    public function authorityEscalationUserIds(): array
    {
        return collect((array) data_get($this->metadata ?? [], 'authority_sla.escalation_user_ids', []))
            ->map(fn (mixed $userId): int => (int) $userId)
            ->filter(fn (int $userId): bool => $userId > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function authorityEscalationRoleNames(): array
    {
        return collect((array) data_get($this->metadata ?? [], 'authority_sla.escalation_role_names', []))
            ->filter(fn (mixed $roleName): bool => filled($roleName))
            ->map(fn (mixed $roleName): string => (string) $roleName)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function authorityApprovalCodes(): array
    {
        return ApplicationWorkflowRegistry::approvalCodesForEntity($this);
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar'
            ? ($this->name_ar ?: $this->name_en)
            : ($this->name_en ?: $this->name_ar);
    }

    public function localizedStatus(): string
    {
        return __('app.statuses.'.Str::lower($this->status ?: 'active'));
    }

    public function localizedRegistrationType(): string
    {
        if (! filled($this->registration_type)) {
            return __('app.dashboard.not_available');
        }

        return __('app.registration_types.'.Str::lower($this->registration_type));
    }

    public function isOperationallyActive(): bool
    {
        return ($this->status ?: 'active') === 'active';
    }

    public function isRegistrationReviewable(): bool
    {
        return in_array($this->registration_type, ['company', 'ngo', 'school'], true);
    }
}
