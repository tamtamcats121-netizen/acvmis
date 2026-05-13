<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\AccountActionLog;
use App\Models\AcademicDocument;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSchedule;
use App\Models\Student;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class StudentAthleteController extends Controller
{
    public function dashboard()
    {
        $student = Student::where('user_id', Auth::id())->first();

        try {
            return Inertia::render('StudentAthletes/StudentAthleteDashboard', [
                'dashboard' => $this->buildDashboard($student),
            ]);
        } catch (\Throwable $e) {
            Log::error('Student dashboard load failed', [
                'user_id' => Auth::id(),
                'student_id' => $student?->id,
                'message' => $e->getMessage(),
            ]);

            return Inertia::render('StudentAthletes/StudentAthleteDashboard', [
                'dashboard' => $this->emptyDashboardPayload(),
            ]);
        }
    }

    public function index(Request $request)
    {
        // Get the logged-in student-athlete
        $student = Student::where('user_id', Auth::id())->first();

        if (!$student) {
            // If not a student-athlete, return empty team
            return Inertia::render('StudentAthletes/MyTeam', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'currentStudentId' => null,
            ]);
        }

        // Find teams where the student is a member
        $teams = Team::query()
            ->active()
            ->with('sport:id,name')
            ->whereHas('players', function($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->orderBy('team_name')
            ->get(['id', 'team_name', 'sport_id']);

        if ($teams->isEmpty()) {
            return Inertia::render('StudentAthletes/MyTeam', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'currentStudentId' => $student->id,
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = Team::query()
            ->active()
            ->with([
                'sport',
                'coach.user',
                'assistantCoach.user',
                'players.student.user',
            ])
            ->whereHas('players', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->find($selectedTeamId);

        if ($team) {
            $coach = $team->coach ? [
                'id' => $team->coach->id,
                'first_name' => $team->coach->first_name,
                'last_name' => $team->coach->last_name,
                'phone_number' => $team->coach->phone_number,
                'email' => $team->coach->user?->email,
            ] : null;

            $assistantCoach = $team->assistantCoach ? [
                'id' => $team->assistantCoach->id,
                'first_name' => $team->assistantCoach->first_name,
                'last_name' => $team->assistantCoach->last_name,
                'phone_number' => $team->assistantCoach->phone_number,
                'email' => $team->assistantCoach->user?->email,
            ] : null;

            $team = [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'team_avatar' => $team->team_avatar,
                'sport' => $team->sport,
                'year' => $team->year,
                'coach' => $coach,
                'assistantCoach' => $assistantCoach,
                'players' => $team->players,
            ];
        }
        return Inertia::render('StudentAthletes/MyTeam', [
            'team' => $team,
            'teams' => $teams->map(function (Team $team) {
                return [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ];
            })->values(),
            'selectedTeamId' => $selectedTeamId,
            'currentStudentId' => $student->id,
        ]);
    }

    public function updateDesiredJersey(Request $request, TeamPlayer $teamPlayer)
    {
        $student = Student::where('user_id', Auth::id())->first();
        if (!$student || $teamPlayer->student_id !== $student->id) {
            abort(403);
        }

        $validated = $request->validate([
            'jersey_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('team_players', 'jersey_number')
                    ->where(fn ($query) => $query->where('team_id', $teamPlayer->team_id))
                    ->ignore($teamPlayer->id),
            ],
        ]);

        $jerseyNumber = trim((string) ($validated['jersey_number'] ?? ''));
        $previousJerseyNumber = trim((string) ($teamPlayer->jersey_number ?? ''));
        $teamPlayer->update([
            'jersey_number' => $jerseyNumber === '' ? null : $jerseyNumber,
        ]);

        $teamPlayer->loadMissing('team');
        AccountActionLog::create([
            'user_id' => Auth::id(),
            'admin_id' => Auth::id(),
            'action' => 'desired_jersey_updated',
            'remarks' => sprintf(
                'Desired jersey number updated for %s in %s: %s -> %s.',
                $student->full_name ?: "Student #{$student->id}",
                $teamPlayer->team?->team_name ?? "Team #{$teamPlayer->team_id}",
                $previousJerseyNumber !== '' ? $previousJerseyNumber : 'none',
                $jerseyNumber !== '' ? $jerseyNumber : 'none'
            ),
        ]);

        return back()->with('success', 'Desired jersey number updated.');
    }

    private function buildDashboard(?Student $student): array
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $sevenDaysStart = $today->copy()->subDays(6);
        $nextWeekEnd = $today->copy()->addDays(6)->endOfDay();
        $lastThirtyDays = $now->copy()->subDays(30)->startOfDay();

        $teamIds = collect();
        if ($student) {
            $teamIds = TeamPlayer::query()
                ->where('student_id', $student->id)
                ->pluck('team_id');
        }

        $attendanceBreakdown = [
            'present' => 0,
            'absent' => 0,
            'excused' => 0,
            'no_response' => 0,
        ];

        if ($student && $teamIds->isNotEmpty()) {
            $attendanceRows = TeamSchedule::query()
                ->whereIn('team_id', $teamIds)
                ->whereBetween('start_time', [$lastThirtyDays, $now])
                ->leftJoin('schedule_attendances as sa', function ($join) use ($student) {
                    $join->on('sa.schedule_id', '=', 'team_schedules.id')
                        ->where('sa.student_id', $student->id);
                })
                ->selectRaw("COALESCE(sa.status, 'no_response') as status")
                ->get();

            $attendanceBreakdown['present'] = $attendanceRows->where('status', 'present')->count();
            $attendanceBreakdown['absent'] = $attendanceRows->where('status', 'absent')->count();
            $attendanceBreakdown['excused'] = $attendanceRows->where('status', 'excused')->count();
            $attendanceBreakdown['no_response'] = $attendanceRows->where('status', 'no_response')->count();
        }

        $attendanceTotal = array_sum($attendanceBreakdown);
        $attendanceRate = $attendanceTotal > 0
            ? (int) round((($attendanceBreakdown['present'] + $attendanceBreakdown['excused']) / $attendanceTotal) * 100)
            : null;

        $pendingResponses = 0;
        if ($student && $teamIds->isNotEmpty()) {
            $pendingResponses = TeamSchedule::query()
                ->whereIn('team_id', $teamIds)
                ->whereBetween('start_time', [$lastThirtyDays, $now])
                ->leftJoin('schedule_attendances as sa', function ($join) use ($student) {
                    $join->on('sa.schedule_id', '=', 'team_schedules.id')
                        ->where('sa.student_id', $student->id);
                })
                ->whereNull('sa.id')
                ->count();
        }

        $latestEvaluationStatus = null;
        if ($student) {
            $latestEvaluationStatus = AcademicEligibilityEvaluation::query()
                ->where('student_id', $student->id)
                ->orderByDesc('evaluated_at')
                ->orderByDesc('id')
                ->first()
                ?->status;
        }

        $upcomingSeries = [];
        $upcomingCounts = collect();
        if ($student && $teamIds->isNotEmpty()) {
            $upcomingCounts = TeamSchedule::query()
                ->whereIn('team_id', $teamIds)
                ->whereBetween('start_time', [$today, $nextWeekEnd])
                ->selectRaw('DATE(start_time) as day, COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
        }

        for ($i = 0; $i < 7; $i++) {
            $day = $today->copy()->addDays($i);
            $key = $day->toDateString();
            $upcomingSeries[] = [
                'label' => $day->format('M j'),
                'count' => (int) ($upcomingCounts[$key] ?? 0),
            ];
        }

        $openPeriodIds = AcademicPeriod::query()
            ->open()
            ->pluck('id');
        $openPeriodCount = $openPeriodIds->count();
        $submittedCount = 0;

        if ($student && $openPeriodCount > 0) {
            $submittedCount = AcademicDocument::query()
                ->periodSubmission()
                ->where('student_id', $student->id)
                ->whereIn('academic_period_id', $openPeriodIds)
                ->distinct('academic_period_id')
                ->count('academic_period_id');
        }

        $pendingCount = max($openPeriodCount - $submittedCount, 0);

        return [
            'kpis' => [
                'has_team_assignment' => $teamIds->isNotEmpty(),
                'attendance_rate' => $attendanceRate,
                'pending_responses' => $pendingResponses,
                'academic_status' => $latestEvaluationStatus,
            ],
            'charts' => [
                'upcoming_sessions' => $upcomingSeries,
                'attendance_breakdown' => $attendanceBreakdown,
                'academic_submissions' => [
                    'submitted' => $submittedCount,
                    'pending' => $pendingCount,
                ],
            ],
        ];
    }

    private function emptyDashboardPayload(): array
    {
        return [
            'kpis' => [
                'has_team_assignment' => false,
                'attendance_rate' => null,
                'pending_responses' => 0,
                'academic_status' => null,
            ],
            'charts' => [
                'upcoming_sessions' => [],
                'attendance_breakdown' => [
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                    'no_response' => 0,
                ],
                'academic_submissions' => [
                    'submitted' => 0,
                    'pending' => 0,
                ],
            ],
        ];
    }
}
