<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scouting_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('scouting_requests', 'current_stage')) {
                $table->string('current_stage')->default('draft')->after('status');
            }

            if (! Schema::hasColumn('scouting_requests', 'review_note')) {
                $table->text('review_note')->nullable()->after('current_stage');
            }

            if (! Schema::hasColumn('scouting_requests', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }

            if (! Schema::hasColumn('scouting_requests', 'reviewed_by_user_id')) {
                $table->foreignId('reviewed_by_user_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('scouting_request_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scouting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('happened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scouting_request_correspondences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scouting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type')->default('applicant')->index();
            $table->string('sender_name');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scouting_request_correspondences');
        Schema::dropIfExists('scouting_request_status_histories');

        Schema::table('scouting_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('scouting_requests', 'reviewed_by_user_id')) {
                $table->dropConstrainedForeignId('reviewed_by_user_id');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('scouting_requests', 'current_stage') ? 'current_stage' : null,
                Schema::hasColumn('scouting_requests', 'review_note') ? 'review_note' : null,
                Schema::hasColumn('scouting_requests', 'reviewed_at') ? 'reviewed_at' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
