<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_settings') || ! Schema::hasColumn('user_settings', 'timezone')) {
            return;
        }

        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_settings') || Schema::hasColumn('user_settings', 'timezone')) {
            return;
        }

        Schema::table('user_settings', function (Blueprint $table) {
            $table->string('timezone', 60)->default('Asia/Manila')->after('notification_email_enabled');
        });
    }
};
