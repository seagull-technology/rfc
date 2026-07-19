<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entity_form_lookup_option')) {
            Schema::create('entity_form_lookup_option', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('form_lookup_option_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(
                    ['entity_id', 'form_lookup_option_id'],
                    'entity_form_lookup_option_unique',
                );
            });
        }

        $this->seedExistingSupportRequirementAssignments();
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_form_lookup_option');
    }

    private function seedExistingSupportRequirementAssignments(): void
    {
        if (! Schema::hasTable('entities') || ! Schema::hasTable('form_lookup_options')) {
            return;
        }

        $entityIds = DB::table('entities')
            ->whereIn('code', ['public-security-directorate', 'military-media-directorate'])
            ->pluck('id', 'code');

        $requirementIds = DB::table('form_lookup_options')
            ->where('type', 'special_location_requirement')
            ->pluck('id', 'code');

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

        $now = now();

        foreach ($assignments as $entityCode => $requirementCodes) {
            $entityId = $entityIds->get($entityCode);

            if (! $entityId) {
                continue;
            }

            foreach ($requirementCodes as $requirementCode) {
                $optionId = $requirementIds->get($requirementCode);

                if (! $optionId) {
                    continue;
                }

                DB::table('entity_form_lookup_option')->updateOrInsert(
                    [
                        'entity_id' => $entityId,
                        'form_lookup_option_id' => $optionId,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }
};
