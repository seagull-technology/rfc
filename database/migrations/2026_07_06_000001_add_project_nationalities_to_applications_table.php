<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applications', 'project_nationalities')) {
            Schema::table('applications', function (Blueprint $table): void {
                $table->json('project_nationalities')->nullable()->after('project_nationality');
            });
        }

        DB::table('applications')
            ->select(['id', 'project_nationality', 'project_nationalities'])
            ->whereNotNull('project_nationality')
            ->orderBy('id')
            ->get()
            ->each(function (object $application): void {
                if (filled($application->project_nationalities)) {
                    return;
                }

                DB::table('applications')
                    ->where('id', $application->id)
                    ->update([
                        'project_nationalities' => json_encode([(string) $application->project_nationality]),
                    ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('applications', 'project_nationalities')) {
            Schema::table('applications', function (Blueprint $table): void {
                $table->dropColumn('project_nationalities');
            });
        }
    }
};
