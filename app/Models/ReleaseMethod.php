<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReleaseMethod extends Model
{
    /**
     * @var array<int, string>
     */
    private const FALLBACK_CODES = [
        'web',
        'television',
        'streaming',
        'cinema',
        'festival',
        'digital',
        'other',
    ];

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
        if (! Schema::hasTable('release_methods')) {
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

    public static function defaultCode(): string
    {
        $codes = self::activeCodes();

        return in_array('cinema', $codes, true) ? 'cinema' : ($codes[0] ?? 'cinema');
    }

    public static function labelFor(?string $code): string
    {
        if (! filled($code)) {
            return __('app.dashboard.not_available');
        }

        $code = (string) $code;

        if (Schema::hasTable('release_methods')) {
            $releaseMethod = static::query()
                ->where('code', $code)
                ->first();

            if ($releaseMethod) {
                return $releaseMethod->displayName();
            }
        }

        $translationKey = 'app.applications.release_methods.'.$code;
        $translation = __($translationKey);

        if ($translation !== $translationKey) {
            return $translation;
        }

        return Str::of($code)->replace('_', ' ')->headline()->toString();
    }
}
