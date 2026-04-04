<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('parent_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('code')->nullable()->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('registration_no')->nullable();
            $table->string('national_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group_id', 'status']);
        });

        Schema::create('entity_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('job_title')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('group_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_role');
        Schema::dropIfExists('entity_user');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('groups');
    }
};
