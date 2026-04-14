<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scouting_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('entity_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('project_name');
            $table->string('project_nationality');
            $table->date('scout_start_date')->nullable();
            $table->date('scout_end_date')->nullable();
            $table->date('production_start_date')->nullable();
            $table->date('production_end_date')->nullable();
            $table->text('project_summary')->nullable();
            $table->text('story_text')->nullable();
            $table->string('story_file_path')->nullable();
            $table->string('story_file_name')->nullable();
            $table->string('story_file_mime_type')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'status']);
            $table->index(['submitted_by_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scouting_requests');
    }
};
