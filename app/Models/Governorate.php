<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Governorate extends Model
{
    /**
     * @var array<int, string>
     */
    private const FALLBACK_CODES = ['amman', 'irbid', 'zarqa', 'balqa', 'madaba', 'karak', 'tafilah', 'maan', 'aqaba', 'mafraq', 'jerash', 'ajloun'];

    protected $fillable = [
        'code',
        'name_en',
        'name_ar',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function filmingLocationTypes(): BelongsToMany
    {
        return $this->belongsToMany(FilmingLocationType::class, 'filming_location_type_governorate')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->orderBy('id');
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * @return array<int, string>
     */
    public static function activeCodes(): array
    {
        if (! Schema::hasTable('governorates')) {
            return self::FALLBACK_CODES;
        }

        $codes = static::query()
            ->active()
            ->ordered()
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        return $codes ?: self::FALLBACK_CODES;
    }

    public static function labelFor(?string $code): string
    {
        if (! filled($code)) {
            return __('app.dashboard.not_available');
        }

        $code = (string) $code;

        if (Schema::hasTable('governorates')) {
            $governorate = static::query()
                ->where('code', $code)
                ->first();

            if ($governorate) {
                return $governorate->displayName();
            }
        }

        $translationKey = 'app.scouting.governorate_options.'.$code;
        $translation = __($translationKey);

        if ($translation !== $translationKey) {
            return $translation;
        }

        return Str::of($code)->replace('_', ' ')->headline()->toString();
    }
}
