<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_authority_approvals')) {
            return;
        }

        Schema::table('application_authority_approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('application_authority_approvals', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')
                    ->nullable()
                    ->after('approval_routing_rule_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('application_authority_approvals', 'assigned_at')) {
                $table->timestamp('assigned_at')
                    ->nullable()
                    ->after('assigned_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('application_authority_approvals')) {
            return;
        }

        Schema::table('application_authority_approvals', function (Blueprint $table): void {
            if (Schema::hasColumn('application_authority_approvals', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }

            if (Schema::hasColumn('application_authority_approvals', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
        });
    }
};
