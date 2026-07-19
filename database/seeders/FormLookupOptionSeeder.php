<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\FormLookupOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class FormLookupOptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $type => $definitions) {
            foreach ($definitions as $index => $definition) {
                FormLookupOption::query()->updateOrCreate(
                    [
                        'type' => $type,
                        'code' => $definition['code'],
                    ],
                    [
                        'name_en' => $definition['name_en'],
                        'name_ar' => $definition['name_ar'],
                        'metadata' => $definition['metadata'] ?? null,
                        'is_active' => $definition['is_active'] ?? true,
                        'sort_order' => $definition['sort_order'] ?? (($index + 1) * 10),
                    ],
                );
            }
        }

        $this->seedLocationSupportRequirementAssignments();
    }

    private function seedLocationSupportRequirementAssignments(): void
    {
        if (! Schema::hasTable('entity_form_lookup_option')) {
            return;
        }

        $assignments = [
            'public-security-directorate' => [
                'road_closures',
                'police_presence',
                'regular_aerial_filming',
                'drone_filming',
                'special_effects',
                'construction_work',
                'animals',
                'weapons',
                'other',
            ],
            'military-media-directorate' => [
                'armed_forces',
                'regular_aerial_filming',
                'drone_filming',
                'special_effects',
                'weapons',
                'other',
            ],
        ];

        $entities = Entity::query()
            ->whereIn('code', array_keys($assignments))
            ->get()
            ->keyBy('code');
        $requirements = FormLookupOption::query()
            ->ofType(FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT)
            ->get()
            ->keyBy('code');

        foreach ($assignments as $entityCode => $requirementCodes) {
            $entity = $entities->get($entityCode);

            if (! $entity) {
                continue;
            }

            $entity->locationSupportRequirements()->syncWithoutDetaching(
                collect($requirementCodes)
                    ->map(fn (string $code): ?int => $requirements->get($code)?->getKey())
                    ->filter()
                    ->values()
                    ->all(),
            );
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function definitions(): array
    {
        return [
            FormLookupOption::TYPE_EQUIPMENT_CATEGORY => [
                ['code' => 'animals', 'name_en' => 'Animals', 'name_ar' => 'حيوانات'],
                ['code' => 'aerial_drone', 'name_en' => 'Aerial / drone', 'name_ar' => 'تصوير جوي / طائرات مسيرة'],
                ['code' => 'camera_equipment', 'name_en' => 'Camera equipment', 'name_ar' => 'معدات تصوير'],
                ['code' => 'caravans', 'name_en' => 'Caravans', 'name_ar' => 'كرافانات'],
                ['code' => 'chemicals', 'name_en' => 'Chemicals', 'name_ar' => 'مواد كيميائية'],
                ['code' => 'communication', 'name_en' => 'Communication', 'name_ar' => 'معدات اتصالات'],
                ['code' => 'consumables', 'name_en' => 'Consumables', 'name_ar' => 'مواد مستهلكة'],
                ['code' => 'electronics', 'name_en' => 'Electronics: PCs, laptops, editing stations, hard drives', 'name_ar' => 'إلكترونيات: حواسيب ومحطات مونتاج وأقراص تخزين'],
                ['code' => 'fake_blood', 'name_en' => 'Fake blood', 'name_ar' => 'دم صناعي'],
                ['code' => 'food_beverage', 'name_en' => 'Food & beverage', 'name_ar' => 'أطعمة ومشروبات'],
                ['code' => 'furniture', 'name_en' => 'Furniture', 'name_ar' => 'أثاث'],
                ['code' => 'hazardous_material', 'name_en' => 'Hazardous material', 'name_ar' => 'مواد خطرة'],
                ['code' => 'honey_wagons', 'name_en' => 'Honey wagons', 'name_ar' => 'عربات خدمات صحية'],
                ['code' => 'light_equipment', 'name_en' => 'Light equipment', 'name_ar' => 'معدات إضاءة'],
                ['code' => 'makeup', 'name_en' => 'Makeup', 'name_ar' => 'مكياج'],
                ['code' => 'military_wardrobe', 'name_en' => 'Military wardrobe', 'name_ar' => 'ملابس عسكرية'],
                ['code' => 'office_equipment', 'name_en' => 'Office equipment', 'name_ar' => 'معدات مكتبية'],
                ['code' => 'picture_vehicles', 'name_en' => 'Picture vehicles', 'name_ar' => 'مركبات تصوير'],
                ['code' => 'props', 'name_en' => 'Props', 'name_ar' => 'إكسسوارات ومستلزمات مشاهد'],
                ['code' => 'pyrotechnics', 'name_en' => 'Pyrotechnics', 'name_ar' => 'مؤثرات نارية'],
                ['code' => 'smoke_machine', 'name_en' => 'Smoke machine', 'name_ar' => 'آلة دخان'],
                ['code' => 'sound_equipment', 'name_en' => 'Sound equipment', 'name_ar' => 'معدات صوت'],
                ['code' => 'walkies', 'name_en' => 'Walkies', 'name_ar' => 'أجهزة لاسلكية'],
                ['code' => 'wardrobe', 'name_en' => 'Wardrobe', 'name_ar' => 'ملابس'],
                ['code' => 'weapons', 'name_en' => 'Weapons', 'name_ar' => 'أسلحة'],
                ['code' => 'weapons_fake_deactivated', 'name_en' => 'Weapons (fake/deactivated)', 'name_ar' => 'أسلحة غير حقيقية / معطلة'],
                ['code' => 'wireless_equipment', 'name_en' => 'Wireless equipment', 'name_ar' => 'معدات لاسلكية'],
                ['code' => 'other', 'name_en' => 'Other', 'name_ar' => 'أخرى'],
            ],
            FormLookupOption::TYPE_EQUIPMENT_SHIPPING_METHOD => [
                ['code' => 'shipping', 'name_en' => 'Shipping', 'name_ar' => 'شحن'],
                ['code' => 'luggage', 'name_en' => 'Luggage', 'name_ar' => 'أمتعة'],
            ],
            FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT => [
                ['code' => 'al_karamah_border_crossing', 'name_en' => 'Al-Karamah Border Crossing', 'name_ar' => 'مركز حدود الكرامة', 'metadata' => ['kind' => 'border']],
                ['code' => 'amman_civil_airport', 'name_en' => 'Amman Civil Airport', 'name_ar' => 'مطار عمان المدني', 'metadata' => ['kind' => 'airport']],
                ['code' => 'aqaba_seaport', 'name_en' => 'Aqaba Seaport', 'name_ar' => 'ميناء العقبة', 'metadata' => ['kind' => 'seaport']],
                ['code' => 'durra_border_crossing', 'name_en' => 'Durra Border Crossing', 'name_ar' => 'مركز حدود الدرة', 'metadata' => ['kind' => 'border']],
                ['code' => 'jaber_border_crossing', 'name_en' => 'Jaber Border Crossing', 'name_ar' => 'مركز حدود جابر', 'metadata' => ['kind' => 'border']],
                ['code' => 'king_hussein_bridge_border_crossing', 'name_en' => 'King Hussein Bridge Border Crossing', 'name_ar' => 'مركز حدود جسر الملك حسين', 'metadata' => ['kind' => 'border']],
                ['code' => 'king_hussein_international_airport_aqaba', 'name_en' => 'King Hussein International Airport - Aqaba', 'name_ar' => 'مطار الملك حسين الدولي - العقبة', 'metadata' => ['kind' => 'airport']],
                ['code' => 'mudawara_border_crossing', 'name_en' => 'Mudawara Border Crossing', 'name_ar' => 'مركز حدود المدورة', 'metadata' => ['kind' => 'border']],
                ['code' => 'queen_alia_international_airport', 'name_en' => 'Queen Alia International Airport', 'name_ar' => 'مطار الملكة علياء الدولي', 'metadata' => ['kind' => 'airport']],
                ['code' => 'ramtha_border_crossing', 'name_en' => 'Ramtha Border Crossing', 'name_ar' => 'مركز حدود الرمثا', 'metadata' => ['kind' => 'border']],
                ['code' => 'sheikh_hussein_crossing', 'name_en' => 'Sheikh Hussein Crossing', 'name_ar' => 'مركز حدود الشيخ حسين', 'metadata' => ['kind' => 'border']],
                ['code' => 'umari_border_crossing', 'name_en' => 'Umari Border Crossing', 'name_ar' => 'مركز حدود العمري', 'metadata' => ['kind' => 'border']],
                ['code' => 'wadi_araba_crossing', 'name_en' => 'Wadi Araba Crossing', 'name_ar' => 'مركز حدود وادي عربة', 'metadata' => ['kind' => 'border']],
            ],
            FormLookupOption::TYPE_AIRPORT => [
                ['code' => 'queen_alia_international_airport', 'name_en' => 'Queen Alia International Airport', 'name_ar' => 'مطار الملكة علياء الدولي'],
                ['code' => 'amman_civil_airport', 'name_en' => 'Amman Civil Airport', 'name_ar' => 'مطار عمان المدني'],
                ['code' => 'king_hussein_international_airport_aqaba', 'name_en' => 'King Hussein International Airport - Aqaba', 'name_ar' => 'مطار الملك حسين الدولي - العقبة'],
            ],
            FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT => [
                ['code' => 'road_closures', 'name_en' => 'Road closures', 'name_ar' => 'إغلاق شوارع'],
                ['code' => 'police_presence', 'name_en' => 'Police presence', 'name_ar' => 'تواجد شرطة'],
                ['code' => 'armed_forces', 'name_en' => 'Army personnel and/or equipment presence', 'name_ar' => 'تواجد أفراد القوات المسلحة و/أو معدات عسكرية'],
                ['code' => 'regular_aerial_filming', 'name_en' => 'Aerial aviation', 'name_ar' => 'تصوير جوي باستخدام الطائرات العادية'],
                ['code' => 'drone_filming', 'name_en' => 'Drone', 'name_ar' => 'تصوير جوي باستخدام الطائرات اللاسلكية'],
                ['code' => 'special_effects', 'name_en' => 'Special effects: pyrotechnics, fires and explosions', 'name_ar' => 'استخدام مؤثرات خاصة مثل المتفجرات والحرائق'],
                ['code' => 'construction_work', 'name_en' => 'Constructions in public locations', 'name_ar' => 'القيام بأعمال إنشائية'],
                ['code' => 'animals', 'name_en' => 'Use of animals', 'name_ar' => 'استخدام الحيوانات'],
                ['code' => 'weapons', 'name_en' => 'Use weapons', 'name_ar' => 'استخدام أسلحة'],
                ['code' => 'other', 'name_en' => 'Other: please specify', 'name_ar' => 'أخرى: يرجى التوضيح'],
            ],
            FormLookupOption::TYPE_BUDGET_SPENDING_CATEGORY => [
                ['code' => 'jordanian_actors', 'name_en' => 'Total Jordanian cast', 'name_ar' => 'الممثلين الأردنيين فقط'],
                ['code' => 'jordanian_crew', 'name_en' => 'Total Jordanian crew', 'name_ar' => 'كادر العمل الأردني فقط'],
                ['code' => 'flights_travel', 'name_en' => 'Total travel & flights', 'name_ar' => 'مصاريف الرحلات الجوية والسفر'],
                ['code' => 'accommodation', 'name_en' => 'Total accommodation', 'name_ar' => 'مصاريف الإقامة'],
                ['code' => 'transportation', 'name_en' => 'Total transportation', 'name_ar' => 'مصاريف التنقلات'],
                ['code' => 'production_design', 'name_en' => 'Total production design', 'name_ar' => 'مصاريف تصميم الإنتاج'],
                ['code' => 'picture_vehicles', 'name_en' => 'Total picture vehicles', 'name_ar' => 'المركبات المستخدمة في التصوير'],
                ['code' => 'wardrobe', 'name_en' => 'Total wardrobe', 'name_ar' => 'الملابس'],
                ['code' => 'hair_makeup', 'name_en' => 'Total make up & hair', 'name_ar' => 'الكوافير والمكياج'],
                ['code' => 'catering', 'name_en' => 'Total catering', 'name_ar' => 'تزويد الطعام والشراب'],
                ['code' => 'equipment_costs', 'name_en' => 'Total equipment', 'name_ar' => 'تكاليف المعدات'],
                ['code' => 'location_fees', 'name_en' => 'Total location expenses', 'name_ar' => 'أجور مواقع تصوير'],
                ['code' => 'insurance', 'name_en' => 'Total insurance', 'name_ar' => 'تأمين'],
                ['code' => 'per_diems', 'name_en' => 'Total per diem', 'name_ar' => 'المياومات'],
                ['code' => 'health_safety', 'name_en' => 'Health & safety expenses', 'name_ar' => 'مصاريف الصحة والسلامة'],
                ['code' => 'other_1', 'name_en' => 'Other expenses', 'name_ar' => 'مصاريف أخرى'],
                ['code' => 'other_2', 'name_en' => 'Other expenses', 'name_ar' => 'مصاريف أخرى'],
                ['code' => 'other_3', 'name_en' => 'Other expenses', 'name_ar' => 'مصاريف أخرى'],
            ],
            FormLookupOption::TYPE_DRONE_REQUEST_TYPE => [
                ['code' => 'regular', 'name_en' => 'Regular', 'name_ar' => 'اعتيادي'],
                ['code' => 'urgent', 'name_en' => 'Urgent', 'name_ar' => 'طارئ'],
            ],
        ];
    }
}
