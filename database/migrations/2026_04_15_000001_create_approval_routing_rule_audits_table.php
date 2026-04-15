<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_routing_rule_audits')) {
            Schema::create('approval_routing_rule_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('approval_routing_rule_id')->nullable()->constrained('approval_routing_rules')->nullOnDelete();
                $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('rule_name');
                $table->string('action');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamps();

                $table->index(['action', 'created_at'], 'approval_rule_audits_action_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_routing_rule_audits');
    }
};
