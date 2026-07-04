<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_official_letters') || Schema::hasColumn('application_official_letters', 'recipient_type')) {
            return;
        }

        Schema::table('application_official_letters', function (Blueprint $table): void {
            $table->string('recipient_type')->default('authority')->after('target_entity_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('application_official_letters') || ! Schema::hasColumn('application_official_letters', 'recipient_type')) {
            return;
        }

        Schema::table('application_official_letters', function (Blueprint $table): void {
            $table->dropColumn('recipient_type');
        });
    }
};
