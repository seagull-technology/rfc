<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('notification_id')->nullable()->index();
            $table->uuid('database_notification_id')->nullable()->index();
            $table->string('notification_type')->index();
            $table->string('type_key')->nullable()->index();
            $table->string('channel', 40)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->nullableMorphs('notifiable');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('context_type')->nullable()->index();
            $table->unsignedBigInteger('context_id')->nullable()->index();
            $table->string('route_name')->nullable();
            $table->json('route_parameters')->nullable();
            $table->string('url')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('attempted_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['channel', 'status', 'created_at']);
            $table->index(['context_type', 'context_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
