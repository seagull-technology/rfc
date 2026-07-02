<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_categories')) {
            Schema::create('work_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name_en');
                $table->string('name_ar');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'work_categories_active_order_idx');
            });
        }

        if (! Schema::hasTable('release_methods')) {
            Schema::create('release_methods', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name_en');
                $table->string('name_ar');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'release_methods_active_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('release_methods');
        Schema::dropIfExists('work_categories');
    }
};
