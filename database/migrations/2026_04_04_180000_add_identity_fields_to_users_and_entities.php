<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('national_id')->nullable()->unique()->after('email');
            $table->string('phone')->nullable()->unique()->after('national_id');
            $table->string('status')->default('active')->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->unique('national_id');
            $table->unique('registration_no');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
            $table->dropUnique(['registration_no']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['national_id']);
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'username',
                'national_id',
                'phone',
                'status',
                'phone_verified_at',
                'last_login_at',
            ]);
        });
    }
};
