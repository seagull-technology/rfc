<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_authority_approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('application_authority_approvals', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('assigned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_authority_approvals', function (Blueprint $table): void {
            if (Schema::hasColumn('application_authority_approvals', 'escalated_at')) {
                $table->dropColumn('escalated_at');
            }
        });
    }
};
