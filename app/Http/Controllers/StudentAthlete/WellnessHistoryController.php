<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\WellnessLog;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WellnessHistoryController extends Controller
{
    public function index()
    {
        $student = Student::where('user_id', Auth::id())->first();

        if (!$student) {
            return Inertia::render('StudentAthletes/WellnessHistory', [
                'student' => null,
                'logs' => [],
            ]);
        }

        $hasTeamAssignment = \App\Models\Team::query()
            ->whereHas('players', fn ($q) => $q->where('student_id', $student->id))
            ->exists();

        if (!$hasTeamAssignment) {
            return Inertia::render('StudentAthletes/WellnessHistory', [
                'student' => [
                    'id' => $student->id,
                    'student_id_number' => $student->student_id_number,
                    'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                ],
                'logs' => [],
                'noTeamAssigned' => true,
            ]);
        }

        $logs = WellnessLog::query()
            ->with(['schedule.team', 'logger'])
            ->where('student_id', $student->id)
            ->latest('log_date')
            ->get()
            ->map(function ($log) {
                $team = $log->schedule?->team;
                return [
                    'id' => $log->id,
                    'log_date' => optional($log->log_date)->toDateString(),
                    'team_name' => $team?->team_name,
                    'schedule_title' => $log->schedule?->title,
                    'schedule_type' => $log->schedule?->type,
                    'injury_observed' => (bool) $log->injury_observed,
                    'injury_notes' => $log->injury_notes,
                    'fatigue_level' => $log->fatigue_level,
                    'performance_condition' => $log->performance_condition,
                    'remarks' => $log->remarks,
                    'logged_by' => $log->logger?->name,
                    'created_at' => optional($log->created_at)->toDateTimeString(),
                ];
            })->values();

        return Inertia::render('StudentAthletes/WellnessHistory', [
            'student' => [
                'id' => $student->id,
                'student_id_number' => $student->student_id_number,
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
            ],
            'logs' => $logs,
        ]);
    }
}
