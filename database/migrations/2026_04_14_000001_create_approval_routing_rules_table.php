<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_routing_rules')) {
            Schema::create('approval_routing_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('request_type')->default('application');
                $table->string('approval_code');
                $table->foreignId('target_entity_id')->constrained('entities')->cascadeOnDelete();
                $table->json('conditions')->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['request_type', 'approval_code', 'is_active', 'priority'], 'approval_rules_lookup_idx');
            });
        }

        if (Schema::hasTable('application_authority_approvals')) {
            if ($this->indexExists('application_authority_approvals', 'app_auth_approvals_unique')
                && ! $this->indexExists('application_authority_approvals', 'app_auth_approvals_application_idx')) {
                Schema::table('application_authority_approvals', function (Blueprint $table): void {
                    $table->index(['application_id'], 'app_auth_approvals_application_idx');
                });
            }

            if ($this->indexExists('application_authority_approvals', 'app_auth_approvals_unique')) {
                Schema::table('application_authority_approvals', function (Blueprint $table): void {
                    $table->dropUnique('app_auth_approvals_unique');
                });
            }

            Schema::table('application_authority_approvals', function (Blueprint $table): void {
                if (! Schema::hasColumn('application_authority_approvals', 'entity_id')) {
                    $table->foreignId('entity_id')->nullable()->after('authority_code')->constrained('entities')->nullOnDelete();
                }

                if (! Schema::hasColumn('application_authority_approvals', 'approval_routing_rule_id')) {
                    $table->foreignId('approval_routing_rule_id')->nullable()->after('entity_id')->constrained('approval_routing_rules')->nullOnDelete();
                }
            });

            if (! $this->indexExists('application_authority_approvals', 'app_auth_approvals_entity_unique')
                || ! $this->indexExists('application_authority_approvals', 'app_auth_approvals_entity_status_idx')) {
                Schema::table('application_authority_approvals', function (Blueprint $table): void {
                    if (! $this->indexExists('application_authority_approvals', 'app_auth_approvals_entity_unique')) {
                        $table->unique(['application_id', 'authority_code', 'entity_id'], 'app_auth_approvals_entity_unique');
                    }

                    if (! $this->indexExists('application_authority_approvals', 'app_auth_approvals_entity_status_idx')) {
                        $table->index(['entity_id', 'status'], 'app_auth_approvals_entity_status_idx');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('application_authority_approvals')) {
            Schema::table('application_authority_approvals', function (Blueprint $table): void {
                if ($this->indexExists('application_authority_approvals', 'app_auth_approvals_entity_unique')) {
                    $table->dropUnique('app_auth_approvals_entity_unique');
                }

                if ($this->indexExists('application_authority_approvals', 'app_auth_approvals_entity_status_idx')) {
                    $table->dropIndex('app_auth_approvals_entity_status_idx');
                }

                if (Schema::hasColumn('application_authority_approvals', 'approval_routing_rule_id')) {
                    $table->dropConstrainedForeignId('approval_routing_rule_id');
                }

                if (Schema::hasColumn('application_authority_approvals', 'entity_id')) {
                    $table->dropConstrainedForeignId('entity_id');
                }
            });

            Schema::table('application_authority_approvals', function (Blueprint $table): void {
                if (! $this->indexExists('application_authority_approvals', 'app_auth_approvals_unique')) {
                    $table->unique(['application_id', 'authority_code'], 'app_auth_approvals_unique');
                }
            });

            if ($this->indexExists('application_authority_approvals', 'app_auth_approvals_application_idx')
                && $this->indexExists('application_authority_approvals', 'app_auth_approvals_unique')) {
                Schema::table('application_authority_approvals', function (Blueprint $table): void {
                    $table->dropIndex('app_auth_approvals_application_idx');
                });
            }
        }

        Schema::dropIfExists('approval_routing_rules');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return match (DB::getDriverName()) {
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))->contains(fn ($index) => ($index->name ?? null) === $indexName),
            default => collect(DB::select("SHOW INDEX FROM `{$table}`"))->contains(fn ($index) => ($index->Key_name ?? null) === $indexName),
        };
    }
};
