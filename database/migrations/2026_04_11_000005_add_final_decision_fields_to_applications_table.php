<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('applications', 'final_decision_status')) {
                $table->string('final_decision_status')->nullable()->after('review_note')->index();
            }

            if (! Schema::hasColumn('applications', 'final_decision_note')) {
                $table->text('final_decision_note')->nullable()->after('final_decision_status');
            }

            if (! Schema::hasColumn('applications', 'final_decision_issued_at')) {
                $table->timestamp('final_decision_issued_at')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('applications', 'final_decision_issued_by_user_id')) {
                $table->foreignId('final_decision_issued_by_user_id')->nullable()->after('reviewed_by_user_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('applications', 'final_permit_number')) {
                $table->string('final_permit_number')->nullable()->after('final_decision_note')->index();
            }

            if (! Schema::hasColumn('applications', 'final_letter_path')) {
                $table->string('final_letter_path')->nullable()->after('final_permit_number');
            }

            if (! Schema::hasColumn('applications', 'final_letter_name')) {
                $table->string('final_letter_name')->nullable()->after('final_letter_path');
            }

            if (! Schema::hasColumn('applications', 'final_letter_mime_type')) {
                $table->string('final_letter_mime_type')->nullable()->after('final_letter_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            if (Schema::hasColumn('applications', 'final_decision_issued_by_user_id')) {
                $table->dropConstrainedForeignId('final_decision_issued_by_user_id');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('applications', 'final_decision_status') ? 'final_decision_status' : null,
                Schema::hasColumn('applications', 'final_decision_note') ? 'final_decision_note' : null,
                Schema::hasColumn('applications', 'final_decision_issued_at') ? 'final_decision_issued_at' : null,
                Schema::hasColumn('applications', 'final_permit_number') ? 'final_permit_number' : null,
                Schema::hasColumn('applications', 'final_letter_path') ? 'final_letter_path' : null,
                Schema::hasColumn('applications', 'final_letter_name') ? 'final_letter_name' : null,
                Schema::hasColumn('applications', 'final_letter_mime_type') ? 'final_letter_mime_type' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
