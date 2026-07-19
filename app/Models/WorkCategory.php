<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WorkCategory extends Model
{
    public const DEFAULT_WORK_SUMMARY_MIN_WORDS = 500;

    /**
     * @var array<int, string>
     */
    private const FALLBACK_CODES = [
        'reality_program',
        'animation',
        'music_video',
        'commercial',
        'short_film',
        'feature_film',
        'tv_program',
        'series',
        'documentary',
        'student_project',
        'other',
    ];

    protected $fillable = [
        'code',
        'name_en',
        'name_ar',
        'work_summary_min_words',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'work_summary_min_words' => 'integer',
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

    public function workSummaryMinWords(): int
    {
        return max(1, (int) ($this->work_summary_min_words ?: self::DEFAULT_WORK_SUMMARY_MIN_WORDS));
    }

    public static function workSummaryMinWordsFor(?string $code): int
    {
        if (! filled($code) || ! Schema::hasTable('work_categories')) {
            return self::DEFAULT_WORK_SUMMARY_MIN_WORDS;
        }

        return static::query()
            ->where('code', (string) $code)
            ->first()?->workSummaryMinWords() ?? self::DEFAULT_WORK_SUMMARY_MIN_WORDS;
    }

    /**
     * @return array<int, string>
     */
    public static function activeCodes(): array
    {
        if (! Schema::hasTable('work_categories')) {
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

        return in_array('feature_film', $codes, true) ? 'feature_film' : ($codes[0] ?? 'feature_film');
    }

    public static function labelFor(?string $code): string
    {
        if (! filled($code)) {
            return __('app.dashboard.not_available');
        }

        $code = (string) $code;

        if (Schema::hasTable('work_categories')) {
            $workCategory = static::query()
                ->where('code', $code)
                ->first();

            if ($workCategory) {
                return $workCategory->displayName();
            }
        }

        $translationKey = 'app.applications.work_categories.'.$code;
        $translation = __($translationKey);

        if ($translation !== $translationKey) {
            return $translation;
        }

        return Str::of($code)->replace('_', ' ')->headline()->toString();
    }
}
