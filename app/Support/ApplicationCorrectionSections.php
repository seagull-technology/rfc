<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ApplicationCorrectionSections
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::keys())
            ->mapWithKeys(fn (string $key): array => [$key => __('app.authority_change_requests.sections.'.$key)])
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    public static function keys(): Collection
    {
        return collect([
            'producer_information',
            'director_information',
            'work_category',
            'release_method',
            'schedule',
            'estimated_crew',
            'budget',
            'international_project',
            'work_content_summary',
            'cast_crew',
            'filming_locations',
            'safety_guidelines',
            'production_terms',
            'ministry_interior_personal_details',
            'imported_equipment',
            'airport_filming',
            'governmental_scenes',
            'other',
        ]);
    }

    public static function label(string $key): string
    {
        return self::options()[$key] ?? __('app.authority_change_requests.sections.other');
    }
}
