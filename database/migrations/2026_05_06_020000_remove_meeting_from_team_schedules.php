<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('team_schedules')
            ->where('type', 'meeting')
            ->update(['type' => 'practice']);

        DB::statement("ALTER TABLE team_schedules MODIFY COLUMN type ENUM('practice', 'game') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE team_schedules MODIFY COLUMN type ENUM('practice', 'game', 'meeting') NOT NULL");
    }
};
