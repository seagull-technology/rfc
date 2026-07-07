<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('filming_location_types') || Schema::hasColumn('filming_location_types', 'approval_days')) {
            return;
        }

        Schema::table('filming_location_types', function (Blueprint $table): void {
            $table->unsignedSmallInteger('approval_days')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('filming_location_types') || ! Schema::hasColumn('filming_location_types', 'approval_days')) {
            return;
        }

        Schema::table('filming_location_types', function (Blueprint $table): void {
            $table->dropColumn('approval_days');
        });
    }
};
