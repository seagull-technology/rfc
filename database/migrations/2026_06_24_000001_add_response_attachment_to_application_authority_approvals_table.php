<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_authority_approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('application_authority_approvals', 'response_attachment_path')) {
                $table->string('response_attachment_path')->nullable()->after('note');
            }

            if (! Schema::hasColumn('application_authority_approvals', 'response_attachment_name')) {
                $table->string('response_attachment_name')->nullable()->after('response_attachment_path');
            }

            if (! Schema::hasColumn('application_authority_approvals', 'response_attachment_mime_type')) {
                $table->string('response_attachment_mime_type')->nullable()->after('response_attachment_name');
            }

            if (! Schema::hasColumn('application_authority_approvals', 'response_attachment_size')) {
                $table->unsignedBigInteger('response_attachment_size')->nullable()->after('response_attachment_mime_type');
            }

            if (! Schema::hasColumn('application_authority_approvals', 'response_attachment_uploaded_at')) {
                $table->timestamp('response_attachment_uploaded_at')->nullable()->after('response_attachment_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_authority_approvals', function (Blueprint $table): void {
            $columns = [
                'response_attachment_uploaded_at',
                'response_attachment_size',
                'response_attachment_mime_type',
                'response_attachment_name',
                'response_attachment_path',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('application_authority_approvals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
