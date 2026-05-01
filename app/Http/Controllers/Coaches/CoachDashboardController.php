<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSchedule;
use App\Models\WellnessLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CoachDashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $coach = $request->user()?->coach;

            if (!$coach) {
                return Inertia::render('Coaches/CoachDashboard', $this->emptyPayload());
            }

            $team = Team::with('sport')
                ->forCoach($coach->id)
                ->first();

            if (!$team) {
                return Inertia::render('Coaches/CoachDashboard', $this->emptyPayload());
            }

            $teamId = $team->id;
            $now = Carbon::now(config('app.timezone'));
            $trendStart = $now->copy()->subDays(13)->startOfDay();
            $trendEnd = $now->copy()->endOfDay();

            $rosterTotal = TeamPlayer::where('team_id', $teamId)->count();
            $upcomingSessions = TeamSchedule::query()
                ->where('team_id', $teamId)
                ->where('start_time', '>=', $now)
                ->count();

            $pastSchedules = TeamSchedule::query()
                ->where('team_id', $teamId)
                ->where('end_time', '<', $now)
                ->withCount(['attendances', 'wellnessLogs'])
                ->orderByDesc('end_time')
                ->get();

            $attendanceNeedsReview = $pastSchedules->where('attendances_count', 0)->count();
            $nextAttendanceAction = $pastSchedules
                ->first(function ($schedule) {
                    return (int) ($schedule->attendances_count ?? 0) === 0;
                });
            $wellnessPending = $pastSchedules->filter(function ($s) {
                $type = strtolower((string) $s->type);
                return in_array($type, ['practice', 'game'], true) && $s->attendances_count > 0 && $s->wellness_logs_count === 0;
            })->count();

            return Inertia::render('Coaches/CoachDashboard', [
                'team' => [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ],
                'metrics' => [
                    'upcoming_sessions' => $upcomingSessions,
                    'attendance_needs_review' => $attendanceNeedsReview,
                    'wellness_pending' => $wellnessPending,
                    'roster_total' => $rosterTotal,
                ],
                'actions' => [
                    'attendance_pending_schedule' => $nextAttendanceAction ? [
                        'id' => $nextAttendanceAction->id,
                        'title' => $nextAttendanceAction->title,
                        'type' => $nextAttendanceAction->type,
                        'venue' => $nextAttendanceAction->venue,
                        'end_time' => optional($nextAttendanceAction->end_time)->toIso8601String(),
                    ] : null,
                ],
                'trends' => [
                    'attendance' => $this->attendanceTrend($teamId, $trendStart, $trendEnd),
                ],
                'wellness' => $this->wellnessSnapshot($teamId, $trendStart, $trendEnd),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Coach dashboard load failed.', [
                'user_id' => $request->user()?->id,
                'coach_id' => $request->user()?->coach?->id,
                'message' => $e->getMessage(),
            ]);

            return Inertia::render('Coaches/CoachDashboard', $this->emptyPayload());
        }
    }

    private function emptyPayload(): array
    {
        return [
            'team' => null,
            'metrics' => [
                'upcoming_sessions' => 0,
                'attendance_needs_review' => 0,
                'wellness_pending' => 0,
                'roster_total' => 0,
            ],
            'actions' => [
                'attendance_pending_schedule' => null,
            ],
            'trends' => [
                'attendance' => [
                    'labels' => [],
                    'series' => [
                        'present' => [],
                        'late' => [],
                        'absent' => [],
                        'excused' => [],
                    ],
                ],
            ],
            'wellness' => [
                'injury_observed_count' => 0,
                'avg_fatigue' => 0,
                'performance_breakdown' => [
                    'excellent' => 0,
                    'good' => 0,
                    'fair' => 0,
                    'poor' => 0,
                ],
                'recent_injury_notes' => [],
            ],
        ];
    }

    private function attendanceTrend(int $teamId, Carbon $start, Carbon $end): array
    {
        $rows = DB::table('team_schedules as ts')
            ->leftJoin('schedule_attendances as sa', 'sa.schedule_id', '=', 'ts.id')
            ->where('ts.team_id', $teamId)
            ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('DATE(ts.start_time) as schedule_date')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'excused' THEN 1 ELSE 0 END) as excused_count")
            ->groupByRaw('DATE(ts.start_time)')
            ->orderByRaw('DATE(ts.start_time)')
            ->get()
            ->keyBy('schedule_date');

        $labels = [];
        $series = [
            'present' => [],
            'late' => [],
            'absent' => [],
            'excused' => [],
        ];

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('M j');
            $series['present'][] = (int) ($rows[$key]->present_count ?? 0);
            $series['late'][] = (int) ($rows[$key]->late_count ?? 0);
            $series['absent'][] = (int) ($rows[$key]->absent_count ?? 0);
            $series['excused'][] = (int) ($rows[$key]->excused_count ?? 0);
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }

    private function wellnessSnapshot(int $teamId, Carbon $start, Carbon $end): array
    {
        $baseQuery = WellnessLog::query()
            ->whereHas('schedule', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()]);

        $stats = (clone $baseQuery)
            ->selectRaw('SUM(CASE WHEN injury_observed = 1 THEN 1 ELSE 0 END) as injury_observed_count')
            ->selectRaw('AVG(fatigue_level) as avg_fatigue')
            ->selectRaw("SUM(CASE WHEN performance_condition = 'excellent' THEN 1 ELSE 0 END) as excellent_count")
            ->selectRaw("SUM(CASE WHEN performance_condition = 'good' THEN 1 ELSE 0 END) as good_count")
            ->selectRaw("SUM(CASE WHEN performance_condition = 'fair' THEN 1 ELSE 0 END) as fair_count")
            ->selectRaw("SUM(CASE WHEN performance_condition = 'poor' THEN 1 ELSE 0 END) as poor_count")
            ->first();

        $recentInjuryNotes = (clone $baseQuery)
            ->with(['student.user:id,first_name,last_name'])
            ->where('injury_observed', true)
            ->whereNotNull('injury_notes')
            ->where('injury_notes', '!=', '')
            ->latest('log_date')
            ->limit(5)
            ->get()
            ->map(function (WellnessLog $log) {
                $studentUser = $log->student?->user;

                return [
                    'id' => $log->id,
                    'student_name' => trim(($studentUser?->first_name ?? '') . ' ' . ($studentUser?->last_name ?? '')) ?: 'Unknown athlete',
                    'student_id_number' => $log->student?->student_id_number,
                    'injury_notes' => $log->injury_notes,
                    'log_date' => optional($log->log_date)->toDateString(),
                ];
            })
            ->values()
            ->all();

        return [
            'injury_observed_count' => (int) ($stats->injury_observed_count ?? 0),
            'avg_fatigue' => round((float) ($stats->avg_fatigue ?? 0), 2),
            'performance_breakdown' => [
                'excellent' => (int) ($stats->excellent_count ?? 0),
                'good' => (int) ($stats->good_count ?? 0),
                'fair' => (int) ($stats->fair_count ?? 0),
                'poor' => (int) ($stats->poor_count ?? 0),
            ],
            'recent_injury_notes' => $recentInjuryNotes,
        ];
    }
}
