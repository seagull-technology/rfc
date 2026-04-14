<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'national_id',
        'phone',
        'status',
        'registration_type',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_user')
            ->withPivot([
                'job_title',
                'is_primary',
                'status',
                'joined_at',
                'left_at',
            ])
            ->withTimestamps();
    }

    public function primaryEntity(): ?Entity
    {
        return $this->entities()
            ->orderByDesc('entity_user.is_primary')
            ->orderBy('entities.name_en')
            ->first();
    }

    public function displayName(): string
    {
        return $this->name ?: $this->email;
    }

    /**
     * @return Collection<int, string>
     */
    public function roleNamesForEntity(?Entity $entity): Collection
    {
        if (! $entity) {
            return collect();
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            return $this->getRoleNames();
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    public function canAccessAdminPanel(?Entity $entity = null): bool
    {
        $entity ??= $this->primaryEntity();

        if (! $entity) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            return $this->can('access.admin-panel');
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
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

    public function requiresAdminApprovalBeforeLogin(): bool
    {
        return in_array($this->registration_type, ['ngo', 'school'], true);
    }

    public function canSignIn(): bool
    {
        if ($this->requiresAdminApprovalBeforeLogin()) {
            return ($this->status ?: 'active') === 'active';
        }

        return in_array($this->status ?: 'active', [
            'active',
            'pending_review',
            'needs_completion',
            'rejected',
        ], true);
    }
}
