<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_en',
        'name_ar',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'group_role')
            ->withTimestamps();
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar'
            ? ($this->name_ar ?: $this->name_en)
            : ($this->name_en ?: $this->name_ar);
    }
}
