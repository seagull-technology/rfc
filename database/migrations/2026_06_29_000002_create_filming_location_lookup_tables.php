<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('governorates')) {
            Schema::create('governorates', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name_en');
                $table->string('name_ar');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'governorates_active_order_idx');
            });
        }

        if (! Schema::hasTable('filming_location_types')) {
            Schema::create('filming_location_types', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name_en');
                $table->string('name_ar');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'filming_location_types_active_order_idx');
            });
        }

        if (! Schema::hasTable('filming_location_type_governorate')) {
            Schema::create('filming_location_type_governorate', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('governorate_id');
                $table->unsignedBigInteger('filming_location_type_id');
                $table->timestamps();

                $table->foreign('governorate_id', 'fltg_governorate_fk')
                    ->references('id')
                    ->on('governorates')
                    ->cascadeOnDelete();
                $table->foreign('filming_location_type_id', 'fltg_location_type_fk')
                    ->references('id')
                    ->on('filming_location_types')
                    ->cascadeOnDelete();
                $table->unique(['governorate_id', 'filming_location_type_id'], 'fl_type_governorate_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('filming_location_type_governorate');
        Schema::dropIfExists('filming_location_types');
        Schema::dropIfExists('governorates');
    }
};
