<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
