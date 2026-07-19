<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_correspondences') || Schema::hasColumn('application_correspondences', 'recipient_type')) {
            return;
        }

        Schema::table('application_correspondences', function (Blueprint $table): void {
            $table->string('recipient_type')->default('all')->after('sender_name')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('application_correspondences') || ! Schema::hasColumn('application_correspondences', 'recipient_type')) {
            return;
        }

        Schema::table('application_correspondences', function (Blueprint $table): void {
            $table->dropIndex(['recipient_type']);
            $table->dropColumn('recipient_type');
        });
    }
};
