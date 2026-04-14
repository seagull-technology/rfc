<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applications', 'current_stage')) {
            Schema::table('applications', function (Blueprint $table): void {
                $table->string('current_stage')->default('draft')->after('status');
            });
        }

        if (! Schema::hasColumn('applications', 'assigned_to_user_id')) {
            Schema::table('applications', function (Blueprint $table): void {
                $table->foreignId('assigned_to_user_id')->nullable()->after('reviewed_by_user_id')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('applications', 'assigned_at')) {
            Schema::table('applications', function (Blueprint $table): void {
                $table->timestamp('assigned_at')->nullable()->after('assigned_to_user_id');
            });
        }

        if (! Schema::hasTable('application_authority_approvals')) {
            Schema::create('application_authority_approvals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('application_id')->constrained()->cascadeOnDelete();
                $table->string('authority_code');
                $table->string('status')->default('pending')->index();
                $table->text('note')->nullable();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->unique(['application_id', 'authority_code'], 'app_auth_approvals_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_authority_approvals');

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropColumn(['current_stage', 'assigned_at']);
        });
    }
};
