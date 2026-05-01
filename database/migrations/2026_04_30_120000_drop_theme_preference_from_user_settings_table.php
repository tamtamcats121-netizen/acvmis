<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('user_settings') || ! Schema::hasColumn('user_settings', 'theme_preference')) {
            return;
        }

        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn('theme_preference');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_settings') || Schema::hasColumn('user_settings', 'theme_preference')) {
            return;
        }

        Schema::table('user_settings', function (Blueprint $table) {
            $table->string('theme_preference', 20)->default('light')->after('notification_in_app_enabled');
        });
    }
};
