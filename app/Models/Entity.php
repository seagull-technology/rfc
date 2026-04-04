<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
