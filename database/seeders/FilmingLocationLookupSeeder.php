<?php

namespace Database\Seeders;

use App\Models\FilmingLocationType;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class FilmingLocationLookupSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [];

        foreach ($this->governorates() as $index => $definition) {
            $governorates[$definition['code']] = Governorate::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name_en' => $definition['name_en'],
                    'name_ar' => $definition['name_ar'],
                    'is_active' => $definition['is_active'] ?? true,
                    'sort_order' => $definition['sort_order'] ?? (($index + 1) * 10),
                ],
            );
        }

        foreach ($this->locationTypes() as $index => $definition) {
            $locationType = FilmingLocationType::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name_en' => $definition['name_en'],
                    'name_ar' => $definition['name_ar'],
                    'is_active' => $definition['is_active'] ?? true,
                    'sort_order' => $definition['sort_order'] ?? (($index + 1) * 10),
                ],
            );

            $locationType->governorates()->sync(
                collect($definition['governorates'])
                    ->map(fn (string $code): int => $governorates[$code]->getKey())
                    ->all(),
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function governorates(): array
    {
        return [
            ['code' => 'amman', 'name_en' => 'Amman', 'name_ar' => 'عمان', 'sort_order' => 10],
            ['code' => 'irbid', 'name_en' => 'Irbid', 'name_ar' => 'إربد', 'sort_order' => 20],
            ['code' => 'zarqa', 'name_en' => 'Zarqa', 'name_ar' => 'الزرقاء', 'sort_order' => 30],
            ['code' => 'balqa', 'name_en' => 'Balqa', 'name_ar' => 'البلقاء', 'sort_order' => 40],
            ['code' => 'madaba', 'name_en' => 'Madaba', 'name_ar' => 'مادبا', 'sort_order' => 50],
            ['code' => 'karak', 'name_en' => 'Karak', 'name_ar' => 'الكرك', 'sort_order' => 60],
            ['code' => 'tafilah', 'name_en' => 'Tafilah', 'name_ar' => 'الطفيلة', 'sort_order' => 70],
            ['code' => 'maan', 'name_en' => 'Ma’an', 'name_ar' => 'معان', 'sort_order' => 80],
            ['code' => 'aqaba', 'name_en' => 'Aqaba', 'name_ar' => 'العقبة', 'sort_order' => 90],
            ['code' => 'mafraq', 'name_en' => 'Mafraq', 'name_ar' => 'المفرق', 'sort_order' => 100],
            ['code' => 'jerash', 'name_en' => 'Jerash', 'name_ar' => 'جرش', 'sort_order' => 110],
            ['code' => 'ajloun', 'name_en' => 'Ajloun', 'name_ar' => 'عجلون', 'sort_order' => 120],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function locationTypes(): array
    {
        $allGovernorates = ['amman', 'irbid', 'zarqa', 'balqa', 'madaba', 'karak', 'tafilah', 'maan', 'aqaba', 'mafraq', 'jerash', 'ajloun'];

        return [
            [
                'code' => 'public_locations',
                'name_en' => 'Public locations',
                'name_ar' => 'مواقع عامة',
                'governorates' => $allGovernorates,
            ],
            [
                'code' => 'private_location',
                'name_en' => 'Private location',
                'name_ar' => 'موقع خاص',
                'governorates' => $allGovernorates,
            ],
            [
                'code' => 'schools',
                'name_en' => 'Schools',
                'name_ar' => 'مدارس',
                'governorates' => $allGovernorates,
            ],
            [
                'code' => 'universities',
                'name_en' => 'Universities',
                'name_ar' => 'جامعات',
                'governorates' => $allGovernorates,
            ],
            [
                'code' => 'religious_sites',
                'name_en' => 'Religious sites',
                'name_ar' => 'مواقع دينية',
                'governorates' => $allGovernorates,
            ],
            [
                'code' => 'museums',
                'name_en' => 'Museums',
                'name_ar' => 'متاحف',
                'governorates' => ['amman', 'irbid', 'zarqa', 'balqa', 'madaba', 'karak', 'maan', 'aqaba', 'jerash', 'ajloun'],
            ],
            [
                'code' => 'archaeological_sites',
                'name_en' => 'Archaeological sites',
                'name_ar' => 'مواقع أثرية',
                'governorates' => ['amman', 'irbid', 'zarqa', 'balqa', 'madaba', 'karak', 'tafilah', 'maan', 'aqaba', 'mafraq', 'jerash', 'ajloun'],
            ],
            [
                'code' => 'border_areas',
                'name_en' => 'Border areas',
                'name_ar' => 'مناطق حدودية',
                'governorates' => ['irbid', 'mafraq', 'zarqa', 'maan', 'aqaba', 'karak', 'tafilah'],
            ],
            [
                'code' => 'syrian_refugee_camps',
                'name_en' => 'Syrian refugee camps',
                'name_ar' => 'مخيمات اللاجئين السوريين',
                'governorates' => ['amman', 'irbid', 'zarqa', 'mafraq'],
            ],
            [
                'code' => 'palestinian_refugee_camps',
                'name_en' => 'Palestinian refugee camps',
                'name_ar' => 'مخيمات اللاجئين الفلسطينيين',
                'governorates' => ['amman', 'irbid', 'zarqa', 'balqa', 'jerash'],
            ],
            [
                'code' => 'petra',
                'name_en' => 'Petra',
                'name_ar' => 'البترا',
                'governorates' => ['maan'],
            ],
            [
                'code' => 'reserves',
                'name_en' => 'Reserves',
                'name_ar' => 'المحميات',
                'governorates' => ['amman', 'zarqa', 'balqa', 'madaba', 'karak', 'tafilah', 'maan', 'aqaba', 'mafraq', 'ajloun'],
            ],
            [
                'code' => 'valleys',
                'name_en' => 'Valleys',
                'name_ar' => 'الوديان',
                'governorates' => ['balqa', 'madaba', 'karak', 'tafilah', 'maan', 'aqaba', 'ajloun'],
            ],
        ];
    }
}
