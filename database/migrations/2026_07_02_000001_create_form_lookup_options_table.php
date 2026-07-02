<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('form_lookup_options')) {
            Schema::create('form_lookup_options', function (Blueprint $table): void {
                $table->id();
                $table->string('type');
                $table->string('code');
                $table->string('name_en');
                $table->string('name_ar');
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['type', 'code'], 'form_lookup_options_type_code_unique');
                $table->index(['type', 'is_active', 'sort_order'], 'form_lookup_options_type_active_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_lookup_options');
    }
};
