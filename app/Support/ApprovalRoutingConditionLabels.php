<?php

namespace App\Support;

use App\Models\FilmingLocationType;
use App\Models\FormLookupOption;
use App\Models\Governorate;
use App\Models\Nationality;
use App\Models\ReleaseMethod;
use App\Models\WorkCategory;
use Illuminate\Support\Str;

class ApprovalRoutingConditionLabels
{
    public static function label(string $type, string $value): string
    {
        return match ($type) {
            'project_nationalities' => Nationality::labelFor($value),
            'work_categories' => WorkCategory::labelFor($value),
            'release_methods' => ReleaseMethod::labelFor($value),
            'annex_flags' => self::annexFlagLabel($value),
            'governorates' => Governorate::labelFor($value),
            default => $value,
        };
    }

    public static function annexFlagLabel(string $value): string
    {
        foreach (self::lookupFlagPrefixes() as $prefix => $type) {
            $label = self::lookupFlagLabel($value, $prefix, $type);

            if ($label !== null) {
                return $label;
            }
        }

        if (Str::startsWith($value, 'location_type_')) {
            return FilmingLocationType::labelFor(Str::after($value, 'location_type_'));
        }

        if (Str::startsWith($value, 'special_requirement_')) {
            return FormLookupOption::labelFor(
                FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT,
                Str::after($value, 'special_requirement_'),
            );
        }

        $translationKey = 'app.admin.approval_routing.annex_flag_options.'.$value;
        $translation = __($translationKey);

        if ($translation !== $translationKey) {
            return $translation;
        }

        return Str::of($value)->replace('_', ' ')->headline()->toString();
    }

    /**
     * @return array<string, string>
     */
    private static function lookupFlagPrefixes(): array
    {
        return [
            'imported_equipment_category_' => FormLookupOption::TYPE_EQUIPMENT_CATEGORY,
            'imported_equipment_shipping_method_' => FormLookupOption::TYPE_EQUIPMENT_SHIPPING_METHOD,
            'imported_equipment_entry_point_' => FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT,
            'military_location_type_' => FormLookupOption::TYPE_MILITARY_BORDER_LOCATION_TYPE,
            'military_equipment_category_' => FormLookupOption::TYPE_EQUIPMENT_CATEGORY,
            'military_equipment_entry_point_' => FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT,
            'airport_filming_airport_' => FormLookupOption::TYPE_AIRPORT,
        ];
    }

    private static function lookupFlagLabel(string $value, string $prefix, string $type): ?string
    {
        if (! Str::startsWith($value, $prefix)) {
            return null;
        }

        return FormLookupOption::labelFor($type, Str::after($value, $prefix));
    }
}
