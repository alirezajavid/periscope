<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 100)->change();
            $table->string('email', 191)->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->string('phone', 20)->nullable()->unique()->after('email_verified_at');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->boolean('notify_new_episode')->default(true)->after('password');
            $table->enum('role', ['user', 'admin'])->default('user')->after('notify_new_episode');
            $table->enum('status', ['active', 'blocked'])->default('active')->after('role');
            $table->enum('preferred_mirror', ['ir', 'intl'])->default('ir')->after('status');
            $table->char('watermark_payload', 32)->unique()->after('preferred_mirror');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropUnique(['watermark_payload']);
            $table->dropColumn([
                'phone', 'phone_verified_at', 'notify_new_episode', 'role', 'status',
                'preferred_mirror', 'watermark_payload', 'last_login_at',
            ]);
        });
    }
};
