<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nationalities', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->boolean('is_active')->default(true);
            $table->boolean('available_for_project')->default(true);
            $table->boolean('available_for_director')->default(true);
            $table->boolean('available_for_international_producer')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'available_for_project', 'sort_order'], 'nationalities_project_idx');
            $table->index(['is_active', 'available_for_director', 'sort_order'], 'nationalities_director_idx');
            $table->index(['is_active', 'available_for_international_producer', 'sort_order'], 'nationalities_intl_producer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nationalities');
    }
};
