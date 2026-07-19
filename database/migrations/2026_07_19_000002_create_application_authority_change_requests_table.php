<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('application_authority_change_requests')) {
            $this->repairPartiallyCreatedTable();

            return;
        }

        Schema::create('application_authority_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('application_authority_approval_id');
            $table->string('section_key');
            $table->string('section_label');
            $table->text('details');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime_type')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->string('status')->default('requested');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->timestamp('requested_at');
            $table->unsignedBigInteger('resubmitted_by_user_id')->nullable();
            $table->timestamp('resubmitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('application_id', 'authority_change_requests_application_fk')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();
            $table->foreign('application_authority_approval_id', 'authority_change_requests_approval_fk')
                ->references('id')
                ->on('application_authority_approvals')
                ->cascadeOnDelete();
            $table->foreign('requested_by_user_id', 'authority_change_requests_requested_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('resubmitted_by_user_id', 'authority_change_requests_resubmitted_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['application_authority_approval_id', 'status'], 'authority_change_requests_approval_status_index');
            $table->index(['application_id', 'status'], 'authority_change_requests_application_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_authority_change_requests');
    }

    private function repairPartiallyCreatedTable(): void
    {
        $tableName = 'application_authority_change_requests';

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! $this->hasForeignKeyForColumn($tableName, 'application_id')) {
                $table->foreign('application_id', 'authority_change_requests_application_fk')
                    ->references('id')
                    ->on('applications')
                    ->cascadeOnDelete();
            }

            if (! $this->hasForeignKeyForColumn($tableName, 'application_authority_approval_id')) {
                $table->foreign('application_authority_approval_id', 'authority_change_requests_approval_fk')
                    ->references('id')
                    ->on('application_authority_approvals')
                    ->cascadeOnDelete();
            }

            if (! $this->hasForeignKeyForColumn($tableName, 'requested_by_user_id')) {
                $table->foreign('requested_by_user_id', 'authority_change_requests_requested_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (! $this->hasForeignKeyForColumn($tableName, 'resubmitted_by_user_id')) {
                $table->foreign('resubmitted_by_user_id', 'authority_change_requests_resubmitted_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasIndex($tableName, 'authority_change_requests_approval_status_index')) {
                $table->index(
                    ['application_authority_approval_id', 'status'],
                    'authority_change_requests_approval_status_index'
                );
            }

            if (! Schema::hasIndex($tableName, 'authority_change_requests_application_status_index')) {
                $table->index(
                    ['application_id', 'status'],
                    'authority_change_requests_application_status_index'
                );
            }
        });
    }

    private function hasForeignKeyForColumn(string $tableName, string $columnName): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $columnName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
