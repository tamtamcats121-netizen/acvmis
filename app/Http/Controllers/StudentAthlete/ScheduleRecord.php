<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Team;
use App\Models\Student;
use App\Models\TeamSchedule;
use App\Models\ScheduleAttendance;
use Carbon\Carbon;

class ScheduleRecord extends Controller
{
    public function mySchedules(Request $request)
    {
        $student = Student::where('user_id', Auth::id())->first();

        if (!$student) {
            return Inertia::render('StudentAthletes/MySchedules', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'schedules' => [],
            ]);
        }

        $teams = Team::with('sport')
            ->whereHas('players', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->orderBy('team_name')
            ->get();

        if ($teams->isEmpty()) {
            return Inertia::render('StudentAthletes/MySchedules', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'schedules' => [],
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = $teams->firstWhere('id', $selectedTeamId);
        if (!$team) {
            return Inertia::render('StudentAthletes/MySchedules', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'schedules' => [],
            ]);
        }

        $attendanceBySchedule = ScheduleAttendance::where('student_id', $student->id)
            ->get()
            ->keyBy('schedule_id');

        $schedules = TeamSchedule::where('team_id', $team->id)
            ->orderBy('start_time')
            ->get()
            ->map(function ($schedule) use ($attendanceBySchedule, $team) {
                $attendance = $attendanceBySchedule->get($schedule->id);

                return [
                    'id' => $schedule->id,
                    'title' => $schedule->title,
                    'type' => $schedule->type,
                    'venue' => $schedule->venue,
                    'notes' => $schedule->notes,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                    'start' => Carbon::parse($schedule->start_time)->toIso8601String(),
                    'end' => Carbon::parse($schedule->end_time)->toIso8601String(),
                    'attendance_status' => optional($attendance)->status,
                    'attendance_notes' => optional($attendance)->notes,
                ];
            });

        return Inertia::render('StudentAthletes/MySchedules', [
            'team' => [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
            ],
            'teams' => $teams->map(function (Team $team) {
                return [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ];
            })->values(),
            'selectedTeamId' => $selectedTeamId,
            'schedules' => $schedules,
        ]);
    }
}
