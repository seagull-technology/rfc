<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('application_official_letters')) {
            $this->repairExistingTable();

            return;
        }

        Schema::create('application_official_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id');
            $table->foreignId('application_authority_approval_id')->nullable();
            $table->foreignId('target_entity_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();
            $table->date('letter_date')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('recipient_prefix')->nullable();
            $table->string('recipient_name');
            $table->string('subject');
            $table->longText('body');
            $table->json('attachments')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status'], 'app_official_letters_app_status_idx');
            $table->foreign('application_id', 'app_official_letters_app_fk')->references('id')->on('applications')->cascadeOnDelete();
            $table->foreign('application_authority_approval_id', 'app_official_letters_approval_fk')->references('id')->on('application_authority_approvals')->nullOnDelete();
            $table->foreign('target_entity_id', 'app_official_letters_entity_fk')->references('id')->on('entities')->nullOnDelete();
            $table->foreign('created_by_user_id', 'app_official_letters_created_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id', 'app_official_letters_updated_by_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_official_letters');
    }

    private function repairExistingTable(): void
    {
        if (! $this->indexExists('application_official_letters', 'app_official_letters_app_status_idx')) {
            Schema::table('application_official_letters', function (Blueprint $table): void {
                $table->index(['application_id', 'status'], 'app_official_letters_app_status_idx');
            });
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->addForeignIfMissing('application_id', 'app_official_letters_app_fk', 'applications', 'id', 'cascade');
        $this->addForeignIfMissing('application_authority_approval_id', 'app_official_letters_approval_fk', 'application_authority_approvals', 'id', 'set null');
        $this->addForeignIfMissing('target_entity_id', 'app_official_letters_entity_fk', 'entities', 'id', 'set null');
        $this->addForeignIfMissing('created_by_user_id', 'app_official_letters_created_by_fk', 'users', 'id', 'set null');
        $this->addForeignIfMissing('updated_by_user_id', 'app_official_letters_updated_by_fk', 'users', 'id', 'set null');
    }

    private function addForeignIfMissing(string $column, string $name, string $targetTable, string $targetColumn, string $onDelete): void
    {
        if ($this->foreignKeyExists('application_official_letters', $name)) {
            return;
        }

        Schema::table('application_official_letters', function (Blueprint $table) use ($column, $name, $targetTable, $targetColumn, $onDelete): void {
            $definition = $table->foreign($column, $name)->references($targetColumn)->on($targetTable);

            if ($onDelete === 'cascade') {
                $definition->cascadeOnDelete();

                return;
            }

            $definition->nullOnDelete();
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return match (DB::getDriverName()) {
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))->contains(fn ($index) => ($index->name ?? null) === $indexName),
            default => collect(DB::select("SHOW INDEX FROM `{$table}`"))->contains(fn ($index) => ($index->Key_name ?? null) === $indexName),
        };
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        return collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $foreignKeyName, 'FOREIGN KEY'],
        ))->isNotEmpty();
    }
};
