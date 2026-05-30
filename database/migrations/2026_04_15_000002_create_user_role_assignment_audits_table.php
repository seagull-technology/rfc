<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_role_assignment_audits')) {
            Schema::create('user_role_assignment_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('role_name');
                $table->string('action');
                $table->timestamps();

                $table->index(['user_id', 'created_at'], 'user_role_assignment_audits_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignment_audits');
    }
};
