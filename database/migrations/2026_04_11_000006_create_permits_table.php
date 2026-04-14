<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('permit_number')->unique();
            $table->string('status')->default('active')->index();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permits');
    }
};
