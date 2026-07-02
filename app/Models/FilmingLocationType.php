<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FilmingLocationType extends Model
{
    /**
     * @var array<int, string>
     */
    private const FALLBACK_CODES = ['public_locations', 'border_areas', 'archaeological_sites', 'religious_sites', 'schools', 'universities', 'museums', 'syrian_refugee_camps', 'palestinian_refugee_camps', 'petra', 'reserves', 'valleys', 'private_location'];

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

    public function governorates(): BelongsToMany
    {
        return $this->belongsToMany(Governorate::class, 'filming_location_type_governorate')
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
        if (! Schema::hasTable('filming_location_types')) {
            return self::FALLBACK_CODES;
        }

        $codes = static::query()
            ->active()
            ->ordered()
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        return $codes;
    }

    /**
     * @return array<int, string>
     */
    public static function activeCodesForGovernorate(?string $governorateCode): array
    {
        if (! filled($governorateCode) || ! Schema::hasTable('filming_location_types') || ! Schema::hasTable('governorates')) {
            return self::activeCodes();
        }

        $codes = static::query()
            ->active()
            ->whereHas('governorates', fn (Builder $query): Builder => $query
                ->active()
                ->where('code', (string) $governorateCode))
            ->ordered()
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        return $codes ?: self::activeCodes();
    }

    public static function labelFor(?string $code): string
    {
        if (! filled($code)) {
            return __('app.dashboard.not_available');
        }

        $code = (string) $code;

        if (Schema::hasTable('filming_location_types')) {
            $locationType = static::query()
                ->where('code', $code)
                ->first();

            if ($locationType) {
                return $locationType->displayName();
            }
        }

        $translationKey = 'app.applications.location_types.'.$code;
        $translation = __($translationKey);

        if ($translation !== $translationKey) {
            return $translation;
        }

        return Str::of($code)->replace('_', ' ')->headline()->toString();
    }
}
