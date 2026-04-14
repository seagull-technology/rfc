<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('channel')->nullable()->index();
            $table->string('status')->index();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_audits');
    }
};
