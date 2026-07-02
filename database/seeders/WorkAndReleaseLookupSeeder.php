<?php

namespace Database\Seeders;

use App\Models\ReleaseMethod;
use App\Models\WorkCategory;
use Illuminate\Database\Seeder;

class WorkAndReleaseLookupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->workCategories() as $index => $definition) {
            WorkCategory::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name_en' => $definition['name_en'],
                    'name_ar' => $definition['name_ar'],
                    'is_active' => $definition['is_active'] ?? true,
                    'sort_order' => $definition['sort_order'] ?? (($index + 1) * 10),
                ],
            );
        }

        foreach ($this->releaseMethods() as $index => $definition) {
            ReleaseMethod::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name_en' => $definition['name_en'],
                    'name_ar' => $definition['name_ar'],
                    'is_active' => $definition['is_active'] ?? true,
                    'sort_order' => $definition['sort_order'] ?? (($index + 1) * 10),
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workCategories(): array
    {
        return [
            ['code' => 'reality_program', 'name_en' => 'Reality program', 'name_ar' => 'برنامج واقعي', 'sort_order' => 10],
            ['code' => 'animation', 'name_en' => 'Animation', 'name_ar' => 'صور متحركة', 'sort_order' => 20],
            ['code' => 'music_video', 'name_en' => 'Music video', 'name_ar' => 'فيديو موسيقي', 'sort_order' => 30],
            ['code' => 'commercial', 'name_en' => 'Commercial', 'name_ar' => 'إعلان', 'sort_order' => 40],
            ['code' => 'short_film', 'name_en' => 'Short film', 'name_ar' => 'فيلم قصير', 'sort_order' => 50],
            ['code' => 'feature_film', 'name_en' => 'Feature film', 'name_ar' => 'فيلم طويل', 'sort_order' => 60],
            ['code' => 'tv_program', 'name_en' => 'TV program', 'name_ar' => 'برنامج تلفزيوني', 'sort_order' => 70],
            ['code' => 'series', 'name_en' => 'Series', 'name_ar' => 'مسلسل', 'sort_order' => 80],
            ['code' => 'documentary', 'name_en' => 'Documentary', 'name_ar' => 'فيلم وثائقي', 'sort_order' => 90],
            ['code' => 'student_project', 'name_en' => 'Student project', 'name_ar' => 'فيلم طلابي', 'sort_order' => 100],
            ['code' => 'other', 'name_en' => 'Other', 'name_ar' => 'أخرى', 'sort_order' => 110],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function releaseMethods(): array
    {
        return [
            ['code' => 'web', 'name_en' => 'Web', 'name_ar' => 'ويب', 'sort_order' => 10],
            ['code' => 'television', 'name_en' => 'Television', 'name_ar' => 'التلفزيون', 'sort_order' => 20],
            ['code' => 'streaming', 'name_en' => 'Streaming platform', 'name_ar' => 'منصات رقمية (VOD)', 'sort_order' => 30],
            ['code' => 'cinema', 'name_en' => 'Cinema', 'name_ar' => 'دور السينما', 'sort_order' => 40],
            ['code' => 'festival', 'name_en' => 'Festival circuit', 'name_ar' => 'مهرجانات', 'sort_order' => 50],
            ['code' => 'digital', 'name_en' => 'Digital release', 'name_ar' => 'عرض رقمي', 'sort_order' => 60],
            ['code' => 'other', 'name_en' => 'Other', 'name_ar' => 'أخرى', 'sort_order' => 70],
        ];
    }
}
