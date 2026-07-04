<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_authority_approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('application_authority_approvals', 'sla_warning_notified_at')) {
                $table->timestamp('sla_warning_notified_at')->nullable()->after('escalated_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_authority_approvals', function (Blueprint $table): void {
            if (Schema::hasColumn('application_authority_approvals', 'sla_warning_notified_at')) {
                $table->dropColumn('sla_warning_notified_at');
            }
        });
    }
};
