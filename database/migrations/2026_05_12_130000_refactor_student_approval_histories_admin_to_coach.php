<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_approval_histories')) {
            return;
        }

        if (!Schema::hasColumn('student_approval_histories', 'coach_id')) {
            Schema::table('student_approval_histories', function (Blueprint $table) {
                $table->foreignId('coach_id')->nullable()->after('student_id')->constrained('coaches')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('student_approval_histories', 'admin_id')) {
            $driver = Schema::getConnection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("
                    UPDATE student_approval_histories sah
                    LEFT JOIN coaches c ON c.user_id = sah.admin_id
                    SET sah.coach_id = c.id
                    WHERE sah.coach_id IS NULL
                ");
            } elseif ($driver === 'pgsql') {
                DB::statement("
                    UPDATE student_approval_histories AS sah
                    SET coach_id = c.id
                    FROM coaches AS c
                    WHERE c.user_id = sah.admin_id
                      AND sah.coach_id IS NULL
                ");
            } elseif ($driver === 'sqlite') {
                $rows = DB::table('student_approval_histories as sah')
                    ->leftJoin('coaches as c', 'c.user_id', '=', 'sah.admin_id')
                    ->whereNull('sah.coach_id')
                    ->select('sah.id', 'c.id as coach_id')
                    ->get();

                foreach ($rows as $row) {
                    DB::table('student_approval_histories')
                        ->where('id', $row->id)
                        ->update(['coach_id' => $row->coach_id]);
                }
            }

            Schema::table('student_approval_histories', function (Blueprint $table) {
                $table->dropConstrainedForeignId('admin_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('student_approval_histories')) {
            return;
        }

        if (!Schema::hasColumn('student_approval_histories', 'admin_id')) {
            Schema::table('student_approval_histories', function (Blueprint $table) {
                $table->foreignId('admin_id')->nullable()->after('student_id')->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('student_approval_histories', 'coach_id')) {
            $driver = Schema::getConnection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("
                    UPDATE student_approval_histories sah
                    LEFT JOIN coaches c ON c.id = sah.coach_id
                    SET sah.admin_id = c.user_id
                    WHERE sah.admin_id IS NULL
                ");
            } elseif ($driver === 'pgsql') {
                DB::statement("
                    UPDATE student_approval_histories AS sah
                    SET admin_id = c.user_id
                    FROM coaches AS c
                    WHERE c.id = sah.coach_id
                      AND sah.admin_id IS NULL
                ");
            } elseif ($driver === 'sqlite') {
                $rows = DB::table('student_approval_histories as sah')
                    ->leftJoin('coaches as c', 'c.id', '=', 'sah.coach_id')
                    ->whereNull('sah.admin_id')
                    ->select('sah.id', 'c.user_id as admin_id')
                    ->get();

                foreach ($rows as $row) {
                    DB::table('student_approval_histories')
                        ->where('id', $row->id)
                        ->update(['admin_id' => $row->admin_id]);
                }
            }

            Schema::table('student_approval_histories', function (Blueprint $table) {
                $table->dropConstrainedForeignId('coach_id');
            });
        }
    }
};
