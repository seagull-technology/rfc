<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('entity_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('project_name');
            $table->string('project_nationality');
            $table->string('work_category')->nullable();
            $table->string('release_method')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->unsignedInteger('estimated_crew_count')->nullable();
            $table->decimal('estimated_budget', 12, 2)->nullable();
            $table->text('project_summary')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'status']);
            $table->index(['submitted_by_user_id', 'status']);
        });

        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_histories');
        Schema::dropIfExists('applications');
    }
};
