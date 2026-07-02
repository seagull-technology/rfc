<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_wrap_reports')) {
            Schema::create('application_wrap_reports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('submitted');
                $table->json('payload')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'submitted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_wrap_reports');
    }
};
