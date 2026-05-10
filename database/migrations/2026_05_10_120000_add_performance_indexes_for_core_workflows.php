<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('users', 'users_account_state_role_created_at_idx', ['account_state', 'role', 'created_at']);
        $this->addIndexIfMissing('students', 'students_user_id_approval_status_idx', ['user_id', 'approval_status']);
        $this->addIndexIfMissing('coaches', 'coaches_user_id_status_idx', ['user_id', 'coach_status']);
        $this->addIndexIfMissing('team_players', 'team_players_team_status_student_idx', ['team_id', 'player_status', 'student_id']);
        $this->addIndexIfMissing('team_schedules', 'team_schedules_team_start_end_idx', ['team_id', 'start_time', 'end_time']);
        $this->addIndexIfMissing('schedule_attendances', 'schedule_attendances_schedule_status_idx', ['schedule_id', 'status']);
        $this->addIndexIfMissing('schedule_attendances', 'schedule_attendances_student_status_idx', ['student_id', 'status']);
        $this->addIndexIfMissing('student_documents', 'student_documents_student_uploaded_idx', ['student_id', 'uploaded_at']);
        $this->addIndexIfMissing('student_documents', 'student_documents_period_review_uploaded_idx', ['academic_period_id', 'review_status', 'uploaded_at']);
        $this->addIndexIfMissing('student_documents', 'student_documents_type_review_idx', ['document_type_id', 'review_status']);
        $this->addIndexIfMissing('academic_eligibility_evaluations', 'academic_eval_student_period_status_idx', ['student_id', 'academic_period_id', 'final_status']);
        $this->addIndexIfMissing('academic_eligibility_evaluations', 'academic_eval_period_evaluated_at_idx', ['academic_period_id', 'evaluated_at']);
        $this->addIndexIfMissing('announcement_recipients', 'announcement_recipients_user_read_at_idx', ['user_id', 'read_at']);
        $this->addIndexIfMissing('account_action_logs', 'account_action_logs_user_created_at_idx', ['user_id', 'created_at']);
        $this->addIndexIfMissing('student_approval_histories', 'student_approval_histories_student_created_at_idx', ['student_id', 'created_at']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('users', 'users_account_state_role_created_at_idx');
        $this->dropIndexIfExists('students', 'students_user_id_approval_status_idx');
        $this->dropIndexIfExists('coaches', 'coaches_user_id_status_idx');
        $this->dropIndexIfExists('team_players', 'team_players_team_status_student_idx');
        $this->dropIndexIfExists('team_schedules', 'team_schedules_team_start_end_idx');
        $this->dropIndexIfExists('schedule_attendances', 'schedule_attendances_schedule_status_idx');
        $this->dropIndexIfExists('schedule_attendances', 'schedule_attendances_student_status_idx');
        $this->dropIndexIfExists('student_documents', 'student_documents_student_uploaded_idx');
        $this->dropIndexIfExists('student_documents', 'student_documents_period_review_uploaded_idx');
        $this->dropIndexIfExists('student_documents', 'student_documents_type_review_idx');
        $this->dropIndexIfExists('academic_eligibility_evaluations', 'academic_eval_student_period_status_idx');
        $this->dropIndexIfExists('academic_eligibility_evaluations', 'academic_eval_period_evaluated_at_idx');
        $this->dropIndexIfExists('announcement_recipients', 'announcement_recipients_user_read_at_idx');
        $this->dropIndexIfExists('account_action_logs', 'account_action_logs_user_created_at_idx');
        $this->dropIndexIfExists('student_approval_histories', 'student_approval_histories_student_created_at_idx');
    }

    private function addIndexIfMissing(string $table, string $name, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->where('indexname', $name)
                ->exists();
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $database = DB::getDatabaseName();

            return DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', $table)
                ->where('index_name', $name)
                ->exists();
        }

        if ($driver === 'sqlite') {
            return DB::table('sqlite_master')
                ->where('type', 'index')
                ->where('tbl_name', $table)
                ->where('name', $name)
                ->exists();
        }

        return false;
    }
};
