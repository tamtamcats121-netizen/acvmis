<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_TABLE = 'wellness_logs';
    private const NEW_TABLE = 'performance_logs';
    private const OLD_INDEX = 'wellness_logs_schedule_student_unique';
    private const NEW_INDEX = 'performance_logs_schedule_student_unique';

    public function up(): void
    {
        if (Schema::hasTable(self::OLD_TABLE) && !Schema::hasTable(self::NEW_TABLE)) {
            Schema::rename(self::OLD_TABLE, self::NEW_TABLE);
        }

        if (!Schema::hasTable(self::NEW_TABLE)) {
            return;
        }

        if ($this->hasIndex(self::NEW_TABLE, self::OLD_INDEX)) {
            Schema::table(self::NEW_TABLE, function (Blueprint $table) {
                $table->dropUnique(self::OLD_INDEX);
            });
        }

        if (!$this->hasIndex(self::NEW_TABLE, self::NEW_INDEX)) {
            Schema::table(self::NEW_TABLE, function (Blueprint $table) {
                $table->unique(['schedule_id', 'student_id'], self::NEW_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::NEW_TABLE)) {
            return;
        }

        if ($this->hasIndex(self::NEW_TABLE, self::NEW_INDEX)) {
            Schema::table(self::NEW_TABLE, function (Blueprint $table) {
                $table->dropUnique(self::NEW_INDEX);
            });
        }

        if (!Schema::hasTable(self::OLD_TABLE)) {
            Schema::rename(self::NEW_TABLE, self::OLD_TABLE);
        }

        if (Schema::hasTable(self::OLD_TABLE) && !$this->hasIndex(self::OLD_TABLE, self::OLD_INDEX)) {
            Schema::table(self::OLD_TABLE, function (Blueprint $table) {
                $table->unique(['schedule_id', 'student_id'], self::OLD_INDEX);
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->exists();
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
