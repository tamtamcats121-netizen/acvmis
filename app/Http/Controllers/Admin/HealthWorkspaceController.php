<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\WellnessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class HealthWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        try {
            return Inertia::render('Admin/HealthWorkspace', [
                'wellness' => $this->wellnessPayload($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load wellness workspace.', [
                'admin_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'health_workspace' => 'Unable to load the wellness workspace right now. Please try again.',
            ]);
        }
    }

    private function wellnessPayload(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $injuryOnly = $request->boolean('injury_only', false);
        $fatigueMin = $request->filled('fatigue_min') ? (int) $request->query('fatigue_min') : null;
        $teamId = $request->filled('team_id') ? (int) $request->query('team_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $perPage = max(10, min(100, (int) $request->query('per_page', 15)));

        $applyFilters = function ($query) use ($search, $injuryOnly, $fatigueMin, $teamId, $startDate, $endDate) {
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('student', function ($sq) use ($search) {
                        $sq->where('student_id_number', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    })->orWhereHas('schedule.team', function ($tq) use ($search) {
                        $tq->where('team_name', 'like', "%{$search}%");
                    })->orWhereHas('schedule', function ($schq) use ($search) {
                        $schq->where('title', 'like', "%{$search}%");
                    });
                });
            }

            if ($injuryOnly) {
                $query->where('injury_observed', true);
            }

            if ($fatigueMin !== null && $fatigueMin >= 1 && $fatigueMin <= 5) {
                $query->where('fatigue_level', '>=', $fatigueMin);
            }

            if ($teamId) {
                $query->whereHas('schedule', function ($sq) use ($teamId) {
                    $sq->where('team_id', $teamId);
                });
            }

            if (!empty($startDate)) {
                $query->whereDate('log_date', '>=', $startDate);
            }

            if (!empty($endDate)) {
                $query->whereDate('log_date', '<=', $endDate);
            }
        };

        $baseQuery = WellnessLog::query();
        $applyFilters($baseQuery);

        $stats = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_logs')
            ->selectRaw('SUM(CASE WHEN injury_observed = 1 THEN 1 ELSE 0 END) as injury_observed_count')
            ->selectRaw('AVG(fatigue_level) as avg_fatigue')
            ->first();

        $uniqueAthletes = (clone $baseQuery)
            ->distinct('student_id')
            ->count('student_id');

        $injuryObservedCount = (int) ($stats->injury_observed_count ?? 0);
        $injuryRatePercent = $uniqueAthletes > 0
            ? round(($injuryObservedCount / max(1, $uniqueAthletes)) * 100, 2)
            : 0.0;

        $injurySeverity = $this->injurySeverity($injuryRatePercent);
        $avgFatigue = round((float) ($stats->avg_fatigue ?? 0), 2);
        $fatigueSeverity = $this->fatigueSeverity($avgFatigue);

        $paginator = WellnessLog::query()
            ->with([
                'student.user:id,first_name,last_name',
                'schedule.team:id,team_name',
                'logger:id,first_name,middle_name,last_name',
            ])
            ->tap($applyFilters)
            ->latest('log_date')
            ->paginate($perPage)
            ->withQueryString();

        $logs = collect($paginator->items())
            ->map(function ($log) {
                $student = $log->student;
                $studentUser = $student?->user;
                $team = $log->schedule?->team;

                return [
                    'id' => $log->id,
                    'log_date' => optional($log->log_date)->toDateString(),
                    'student_name' => trim(($studentUser?->first_name ?? '') . ' ' . ($studentUser?->last_name ?? '')) ?: 'Unknown athlete',
                    'student_id_number' => $student?->student_id_number,
                    'team_name' => $team?->team_name,
                    'team_id' => $team?->id,
                    'schedule_title' => $log->schedule?->title,
                    'schedule_type' => $log->schedule?->type,
                    'injury_observed' => (bool) $log->injury_observed,
                    'injury_notes' => $log->injury_notes,
                    'fatigue_level' => $log->fatigue_level,
                    'performance_condition' => $log->performance_condition,
                    'remarks' => $log->remarks,
                    'logged_by' => $log->logger?->name,
                ];
            })
            ->values();

        return [
            'logs' => [
                'data' => $logs,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'injury_only' => $injuryOnly,
                'fatigue_min' => $fatigueMin,
                'team_id' => $teamId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'per_page' => $perPage,
            ],
            'kpis' => [
                'total_logs' => (int) ($stats->total_logs ?? 0),
                'injury_observed_count' => $injuryObservedCount,
                'avg_fatigue' => $avgFatigue,
                'unique_athletes' => (int) $uniqueAthletes,
                'injury_severity' => $injurySeverity,
                'fatigue_severity' => $fatigueSeverity,
            ],
            'options' => [
                'teams' => Team::query()->orderBy('team_name')->get(['id', 'team_name']),
            ],
        ];
    }

    private function injurySeverity(float $injuryRatePercent): array
    {
        if ($injuryRatePercent >= 60) {
            return [
                'score' => (int) min(100, round($injuryRatePercent)),
                'label' => 'Critical',
                'description' => 'Injury observations are critically high versus active athletes. Immediate intervention recommended.',
            ];
        }

        if ($injuryRatePercent >= 40) {
            return [
                'score' => (int) min(100, round($injuryRatePercent)),
                'label' => 'High',
                'description' => 'Injury observations are high. Prioritize targeted recovery and workload adjustments.',
            ];
        }

        if ($injuryRatePercent >= 20) {
            return [
                'score' => (int) min(100, round($injuryRatePercent)),
                'label' => 'Moderate',
                'description' => 'Injury observations are moderate. Monitor athletes closely and review training load.',
            ];
        }

        return [
            'score' => (int) max(0, round($injuryRatePercent)),
            'label' => 'Low',
            'description' => 'Injury observations are currently low relative to active athletes.',
        ];
    }

    private function fatigueSeverity(float $avgFatigue): array
    {
        if ($avgFatigue >= 4.5) {
            return [
                'score' => 100,
                'label' => 'Critical',
                'description' => 'Average fatigue is at a critical level. Recovery blocks and intensity reduction are needed.',
                'scale' => '4.5 - 5.0',
            ];
        }

        if ($avgFatigue >= 3.5) {
            return [
                'score' => 80,
                'label' => 'High',
                'description' => 'Average fatigue is high. Schedule adjustments should be considered.',
                'scale' => '3.5 - 4.49',
            ];
        }

        if ($avgFatigue >= 2.5) {
            return [
                'score' => 55,
                'label' => 'Moderate',
                'description' => 'Average fatigue is moderate. Continue monitoring and individualize training loads.',
                'scale' => '2.5 - 3.49',
            ];
        }

        return [
            'score' => 30,
            'label' => 'Low',
            'description' => 'Average fatigue remains in a low and manageable range.',
            'scale' => '1.0 - 2.49',
        ];
    }
}
