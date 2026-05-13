<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'applied_sport_id')) {
                $table->foreignId('applied_sport_id')
                    ->nullable()
                    ->after('approval_status')
                    ->constrained('sports')
                    ->nullOnDelete();
            }
        });

        Schema::table('coaches', function (Blueprint $table) {
            if (!Schema::hasColumn('coaches', 'sport_id')) {
                $table->foreignId('sport_id')
                    ->nullable()
                    ->after('coach_status')
                    ->constrained('sports')
                    ->nullOnDelete();
            }
        });

        $studentAssignments = DB::table('team_players')
            ->join('teams', 'teams.id', '=', 'team_players.team_id')
            ->whereNull('teams.archived_at')
            ->select('team_players.student_id', DB::raw('MIN(teams.sport_id) as sport_id'))
            ->groupBy('team_players.student_id')
            ->get();

        foreach ($studentAssignments as $assignment) {
            if (!empty($assignment->sport_id)) {
                DB::table('students')
                    ->where('id', $assignment->student_id)
                    ->whereNull('applied_sport_id')
                    ->update(['applied_sport_id' => $assignment->sport_id]);
            }
        }

        $coachAssignments = DB::table('team_staff_assignments')
            ->join('teams', 'teams.id', '=', 'team_staff_assignments.team_id')
            ->whereNull('team_staff_assignments.ends_at')
            ->whereNull('teams.archived_at')
            ->select('team_staff_assignments.coach_id', DB::raw('MIN(teams.sport_id) as sport_id'))
            ->groupBy('team_staff_assignments.coach_id')
            ->get();

        foreach ($coachAssignments as $assignment) {
            if (!empty($assignment->sport_id)) {
                DB::table('coaches')
                    ->where('id', $assignment->coach_id)
                    ->whereNull('sport_id')
                    ->update(['sport_id' => $assignment->sport_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            if (Schema::hasColumn('coaches', 'sport_id')) {
                $table->dropConstrainedForeignId('sport_id');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'applied_sport_id')) {
                $table->dropConstrainedForeignId('applied_sport_id');
            }
        });
    }
};
