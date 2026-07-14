<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FormLookupOption extends Model
{
    public const TYPE_EQUIPMENT_CATEGORY = 'equipment_category';
    public const TYPE_EQUIPMENT_SHIPPING_METHOD = 'equipment_shipping_method';
    public const TYPE_EQUIPMENT_ENTRY_POINT = 'equipment_entry_point';
    public const TYPE_AIRPORT = 'airport';
    public const TYPE_SPECIAL_LOCATION_REQUIREMENT = 'special_location_requirement';
    public const TYPE_BUDGET_SPENDING_CATEGORY = 'budget_spending_category';
    public const TYPE_DRONE_REQUEST_TYPE = 'drone_request_type';

    /**
     * @var array<string, array<int, string>>
     */
    private const FALLBACK_CODES = [
        self::TYPE_EQUIPMENT_CATEGORY => ['camera_equipment', 'light_equipment', 'sound_equipment', 'aerial_drone', 'other'],
        self::TYPE_EQUIPMENT_SHIPPING_METHOD => ['shipping', 'luggage'],
        self::TYPE_EQUIPMENT_ENTRY_POINT => ['queen_alia_international_airport', 'amman_civil_airport', 'aqaba_seaport'],
        self::TYPE_AIRPORT => ['queen_alia_international_airport', 'amman_civil_airport', 'king_hussein_international_airport_aqaba'],
        self::TYPE_SPECIAL_LOCATION_REQUIREMENT => ['road_closures', 'police_presence', 'armed_forces', 'regular_aerial_filming', 'drone_filming', 'special_effects', 'construction_work', 'animals', 'weapons', 'other'],
        self::TYPE_BUDGET_SPENDING_CATEGORY => ['jordanian_actors', 'jordanian_crew', 'flights_travel', 'accommodation', 'transportation', 'equipment_costs', 'other_1', 'other_2', 'other_3'],
        self::TYPE_DRONE_REQUEST_TYPE => ['regular', 'urgent'],
    ];

    protected $fillable = [
        'type',
        'code',
        'name_en',
        'name_ar',
        'metadata',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
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
     * @return Collection<int, self>
     */
    public static function activeForType(string $type): Collection
    {
        if (! Schema::hasTable('form_lookup_options')) {
            return collect();
        }

        return static::query()
            ->ofType($type)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public static function activeCodesForType(string $type): array
    {
        if (! Schema::hasTable('form_lookup_options')) {
            return self::FALLBACK_CODES[$type] ?? [];
        }

        $codes = static::activeForType($type)
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->all();

        return $codes;
    }

    public static function labelFor(string $type, ?string $code): string
    {
        if (! filled($code)) {
            return __('app.dashboard.not_available');
        }

        $code = (string) $code;

        if (Schema::hasTable('form_lookup_options')) {
            $option = static::query()
                ->ofType($type)
                ->where('code', $code)
                ->first();

            if ($option) {
                return $option->displayName();
            }
        }

        return Str::of($code)->replace('_', ' ')->headline()->toString();
    }

    /**
     * @return array<int, string>
     */
    public static function codesForValue(string $type, mixed $value): array
    {
        if (! filled($value)) {
            return [];
        }

        $value = trim((string) $value);
        $codes = [$value];

        if (Schema::hasTable('form_lookup_options')) {
            static::query()
                ->ofType($type)
                ->where(function (Builder $query) use ($value): void {
                    $query
                        ->where('code', $value)
                        ->orWhere('name_en', $value)
                        ->orWhere('name_ar', $value);
                })
                ->pluck('code')
                ->each(function ($code) use (&$codes): void {
                    $codes[] = (string) $code;
                });
        }

        return collect($codes)
            ->filter(fn (string $code): bool => filled($code))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_EQUIPMENT_CATEGORY => __('app.admin.form_lookups.types.equipment_category'),
            self::TYPE_EQUIPMENT_SHIPPING_METHOD => __('app.admin.form_lookups.types.equipment_shipping_method'),
            self::TYPE_EQUIPMENT_ENTRY_POINT => __('app.admin.form_lookups.types.equipment_entry_point'),
            self::TYPE_AIRPORT => __('app.admin.form_lookups.types.airport'),
            self::TYPE_SPECIAL_LOCATION_REQUIREMENT => __('app.admin.form_lookups.types.special_location_requirement'),
            self::TYPE_BUDGET_SPENDING_CATEGORY => __('app.admin.form_lookups.types.budget_spending_category'),
            self::TYPE_DRONE_REQUEST_TYPE => __('app.admin.form_lookups.types.drone_request_type'),
        ];
    }
}
