<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Nationality extends Model
{
    public const USAGE_PROJECT = 'project';
    public const USAGE_DIRECTOR = 'director';
    public const USAGE_INTERNATIONAL_PRODUCER = 'international_producer';

    /**
     * @var array<int, string>
     */
    private const PROJECT_FALLBACK_CODES = ['jordanian', 'international'];

    /**
     * @var array<int, string>
     */
    private const PERSON_FALLBACK_CODES = ['jordanian', 'non_jordanian'];

    protected $fillable = [
        'code',
        'name_en',
        'name_ar',
        'is_active',
        'available_for_project',
        'available_for_director',
        'available_for_international_producer',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'available_for_project' => 'boolean',
        'available_for_director' => 'boolean',
        'available_for_international_producer' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProject(Builder $query): Builder
    {
        return $query->where('available_for_project', true);
    }

    public function scopeForDirector(Builder $query): Builder
    {
        return $query->where('available_for_director', true);
    }

    public function scopeForInternationalProducer(Builder $query): Builder
    {
        return $query->where('available_for_international_producer', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->orderBy('id');
    }

    public function scopeForUsage(Builder $query, string $usage): Builder
    {
        return match ($usage) {
            self::USAGE_PROJECT => $query->forProject(),
            self::USAGE_DIRECTOR => $query->forDirector(),
            self::USAGE_INTERNATIONAL_PRODUCER => $query->forInternationalProducer(),
            default => $query,
        };
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * @return array<int, string>
     */
    public static function activeCodesFor(string $usage): array
    {
        if (! Schema::hasTable('nationalities')) {
            return self::fallbackCodesFor($usage);
        }

        $codes = static::query()
            ->active()
            ->forUsage($usage)
            ->ordered()
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        return $codes ?: self::fallbackCodesFor($usage);
    }

    public static function labelFor(?string $code): string
    {
        if (! filled($code)) {
            return __('app.dashboard.not_available');
        }

        $code = (string) $code;

        if (Schema::hasTable('nationalities')) {
            $nationality = static::query()
                ->where('code', $code)
                ->first();

            if ($nationality) {
                return $nationality->displayName();
            }
        }

        foreach ([
            'app.applications.project_nationalities.'.$code,
            'app.applications.nationality_types.'.$code,
        ] as $translationKey) {
            $translation = __($translationKey);

            if ($translation !== $translationKey) {
                return $translation;
            }
        }

        return Str::of($code)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    /**
     * @return array<int, string>
     */
    private static function fallbackCodesFor(string $usage): array
    {
        return match ($usage) {
            self::USAGE_PROJECT => self::PROJECT_FALLBACK_CODES,
            self::USAGE_DIRECTOR, self::USAGE_INTERNATIONAL_PRODUCER => self::PERSON_FALLBACK_CODES,
            default => [],
        };
    }
}
