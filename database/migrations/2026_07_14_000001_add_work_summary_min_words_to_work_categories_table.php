<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_categories') && ! Schema::hasColumn('work_categories', 'work_summary_min_words')) {
            Schema::table('work_categories', function (Blueprint $table): void {
                $table->unsignedInteger('work_summary_min_words')
                    ->default(500)
                    ->after('name_ar');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_categories') && Schema::hasColumn('work_categories', 'work_summary_min_words')) {
            Schema::table('work_categories', function (Blueprint $table): void {
                $table->dropColumn('work_summary_min_words');
            });
        }
    }
};
