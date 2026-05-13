<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\ScheduleAttendance;
use App\Models\Sport;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OperationsWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $filters['tab'] = 'calendar';

        return Inertia::render('Admin/OperationsWorkspace', [
            'filters' => [
                'selected' => $filters,
                'options' => $this->filterOptions(),
            ],
            'tabs' => [
                'active' => 'calendar',
                'available' => ['calendar'],
            ],
            'calendarSchedules' => $this->calendarSchedules($filters),
            'attendanceRecords' => $this->paginatedRecords($filters, false),
            'exceptionsRecords' => $this->paginatedRecords($filters, true),
            'kpis' => $this->kpis($filters),
        ]);
    }

    public function attendanceRecords(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $exceptionOnly = $request->boolean('exception_only', false) || $filters['tab'] === 'exceptions';

        return response()->json($this->paginatedRecords($filters, $exceptionOnly));
    }

    public function scheduleDrilldown(int $scheduleId): JsonResponse
    {
        $schedule = DB::table('team_schedules as ts')
            ->join('teams as t', 't.id', '=', 'ts.team_id')
            ->leftJoin('sports as sp', 'sp.id', '=', 't.sport_id')
            ->where('ts.id', $scheduleId)
            ->select([
                'ts.id',
                'ts.title',
                'ts.type',
                'ts.start_time',
                'ts.end_time',
                'ts.notes',
                't.id as team_id',
                't.team_name',
                DB::raw("COALESCE(sp.name, 'Unknown') as sport_name"),
            ])
            ->first();

        abort_unless($schedule, 404, 'Schedule not found.');

        $roster = DB::table('team_players as tp')
            ->join('students as st', 'st.id', '=', 'tp.student_id')
            ->join('users as su', 'su.id', '=', 'st.user_id')
            ->leftJoin('schedule_attendances as sa', function ($join) use ($scheduleId) {
                $join->on('sa.student_id', '=', 'st.id')
                    ->where('sa.schedule_id', '=', $scheduleId);
            })
            ->where('tp.team_id', $schedule->team_id)
            ->select([
                'st.id as student_id',
                'st.student_id_number',
                'su.first_name',
                'su.middle_name',
                'su.last_name',
                DB::raw("COALESCE(sa.status, 'no_response') as attendance_status"),
                'sa.recorded_at',
                'sa.notes',
                'sa.override_reason',
            ])
            ->orderBy('su.last_name')
            ->orderBy('su.first_name')
            ->get()
            ->map(fn ($row) => [
                'student_id' => (int) $row->student_id,
                'student_id_number' => $row->student_id_number,
                'student_name' => trim(($row->last_name ?? '') . ', ' . ($row->first_name ?? '') . ' ' . ($row->middle_name ?? '')),
                'attendance_status' => $row->attendance_status,
                'recorded_at' => $row->recorded_at,
                'notes' => $row->notes,
                'override_reason' => $row->override_reason,
            ])
            ->values();

        $counts = [
            'total' => $roster->count(),
            'present' => $roster->where('attendance_status', 'present')->count(),
            'absent' => $roster->where('attendance_status', 'absent')->count(),
            'late' => $roster->where('attendance_status', 'late')->count(),
            'excused' => $roster->where('attendance_status', 'excused')->count(),
            'no_response' => $roster->where('attendance_status', 'no_response')->count(),
        ];

        return response()->json([
            'schedule' => [
                'id' => (int) $schedule->id,
                'title' => $schedule->title,
                'type' => $schedule->type,
                'team_name' => $schedule->team_name,
                'sport_name' => $schedule->sport_name,
                'start_time' => Carbon::parse($schedule->start_time)->toIso8601String(),
                'end_time' => Carbon::parse($schedule->end_time)->toIso8601String(),
                'notes' => $schedule->notes,
            ],
            'counts' => $counts,
            'roster' => $roster,
        ]);
    }

    public function updateAttendance(Request $request, int $scheduleId, int $studentId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:present,absent,late,excused',
            'reason' => 'required|string|max:1000',
            'note' => 'nullable|string|max:1000',
        ]);

        $exists = DB::table('team_schedules as ts')
            ->join('team_players as tp', 'tp.team_id', '=', 'ts.team_id')
            ->where('ts.id', $scheduleId)
            ->where('tp.student_id', $studentId)
            ->exists();

        abort_unless($exists, 404, 'Student is not part of this schedule roster.');

        ScheduleAttendance::query()->updateOrCreate(
            [
                'schedule_id' => $scheduleId,
                'student_id' => $studentId,
            ],
            [
                'status' => $validated['status'],
                'verification_method' => 'manual_override',
                'recorded_by' => $request->user()->id,
                'recorded_at' => now(),
                'verified_at' => now(),
                'notes' => $validated['note'] ?? null,
                'override_reason' => $validated['reason'],
            ]
        );

        return response()->json([
            'message' => 'Attendance has been updated successfully.',
        ]);
    }

    public function printAttendance(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $exceptionOnly = $filters['tab'] === 'exceptions';

        $query = $this->recordsQuery($filters, $exceptionOnly)
            ->orderBy('ts.start_time', 'desc')
            ->orderBy('t.team_name')
            ->orderBy('su.last_name')
            ->orderBy('su.first_name');

        $records = $query->get()->map(function ($row) {
            return [
                'schedule_date' => $row->schedule_date,
                'schedule_title' => $row->schedule_title,
                'schedule_type' => $row->schedule_type,
                'team_name' => $row->team_name,
                'sport_name' => $row->sport_name,
                'student_id_number' => $row->student_id_number,
                'student_name' => trim(($row->student_last_name ?? '') . ', ' . ($row->student_first_name ?? '') . ' ' . ($row->student_middle_name ?? '')),
                'status' => $row->attendance_status,
                'recorded_at' => $row->recorded_at,
                'attendance_note' => $row->attendance_note,
                'override_reason' => $row->override_reason,
            ];
        })->values();

        $counts = $this->recordTotals($filters, $exceptionOnly);

        $teamName = $filters['team_id']
            ? (Team::query()->where('id', $filters['team_id'])->value('team_name') ?? 'Unknown')
            : 'All Teams';
        $scheduleTitle = $filters['schedule_id']
            ? (DB::table('team_schedules')->where('id', $filters['schedule_id'])->value('title') ?? 'Selected Schedule')
            : 'All Schedules';
        $statusLabel = $filters['status'] ? ucfirst(str_replace('_', ' ', $filters['status'])) : 'All Statuses';

        $rangeLabel = $filters['start_date'] && $filters['end_date']
            ? "{$filters['start_date']} to {$filters['end_date']}"
            : 'All Dates';

        return view('print.attendance-register', [
            'records' => $records,
            'counts' => $counts,
            'filtersSummary' => [
                'team' => $teamName,
                'schedule' => $scheduleTitle,
                'status' => $statusLabel,
            ],
            'rangeLabel' => $rangeLabel,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    public function printSchedules(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $sportKey = $this->normalizeSportKey($request->input('sport'));

        $query = DB::table('team_schedules as ts')
            ->join('teams as t', 't.id', '=', 'ts.team_id')
            ->leftJoin('sports as sp', 'sp.id', '=', 't.sport_id')
            ->select([
                'ts.title',
                'ts.type',
                'ts.venue',
                'ts.start_time',
                'ts.end_time',
                'ts.notes',
                't.team_name',
                DB::raw("COALESCE(sp.name, 'Unknown') as sport_name"),
            ])
            ->orderBy('ts.start_time');

        if (!empty($filters['team_id'])) {
            $query->where('t.id', (int) $filters['team_id']);
        }

        if (!empty($filters['schedule_type'])) {
            $query->where('ts.type', $filters['schedule_type']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('ts.start_time', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (!empty($filters['end_date'])) {
            $query->where('ts.start_time', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if ($sportKey) {
            $query->whereRaw("LOWER(REPLACE(REPLACE(sp.name, '-', ' '), '_', ' ')) = ?", [$sportKey]);
        }

        $schedules = $query->get()->map(function ($row) {
            return [
                'title' => $row->title,
                'type' => $row->type,
                'venue' => $row->venue,
                'start' => optional(Carbon::parse($row->start_time))->format('M j, Y g:i A'),
                'end' => optional(Carbon::parse($row->end_time))->format('M j, Y g:i A'),
                'notes' => $row->notes,
                'team_name' => $row->team_name,
                'sport_name' => $row->sport_name,
            ];
        })->values();

        $teamName = $filters['team_id']
            ? (Team::query()->where('id', $filters['team_id'])->value('team_name') ?? 'Unknown')
            : 'All Teams';

        $sportName = $sportKey
            ? (Sport::query()->whereRaw("LOWER(REPLACE(REPLACE(name, '-', ' '), '_', ' ')) = ?", [$sportKey])->value('name') ?? ucwords($sportKey))
            : 'All Sports';

        $rangeLabel = ($filters['start_date'] && $filters['end_date'])
            ? "{$filters['start_date']} to {$filters['end_date']}"
            : 'All Dates';

        return view('print.operations-schedules', [
            'schedules' => $schedules,
            'filtersSummary' => [
                'team' => $teamName,
                'sport' => $sportName,
                'type' => $filters['schedule_type'] ?? 'All Types',
            ],
            'rangeLabel' => $rangeLabel,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    private function paginatedRecords(array $filters, bool $exceptionOnly): array
    {
        $sortMap = [
            'schedule_start' => 'ts.start_time',
            'team_name' => 't.team_name',
            'student_name' => 'su.last_name',
            'status' => 'attendance_status',
        ];

        $sort = $sortMap[$filters['sort']] ?? 'ts.start_time';
        $direction = $filters['direction'] === 'asc' ? 'asc' : 'desc';
        $perPage = max(5, min(100, (int) $filters['per_page']));

        $query = $this->recordsQuery($filters, $exceptionOnly);

        if ($sort === 'attendance_status') {
            $query->orderByRaw("COALESCE(sa.status, 'no_response') {$direction}");
        } else {
            $query->orderBy($sort, $direction);
        }

        $query->orderBy('t.team_name')->orderBy('su.last_name')->orderBy('su.first_name');

        $pagination = $query->paginate($perPage, ['*'], 'page', max(1, (int) $filters['page']));
        $totals = $this->recordTotals($filters, $exceptionOnly);

        return [
            'data' => collect($pagination->items())->map(function ($row) {
                return [
                    'schedule_id' => (int) $row->schedule_id,
                    'schedule_title' => $row->schedule_title,
                    'schedule_type' => $row->schedule_type,
                    'schedule_start' => $row->schedule_start,
                    'team_id' => (int) $row->team_id,
                    'team_name' => $row->team_name,
                    'sport_name' => $row->sport_name,
                    'student_id' => (int) $row->student_id,
                    'student_id_number' => $row->student_id_number,
                    'student_name' => trim(($row->student_last_name ?? '') . ', ' . ($row->student_first_name ?? '') . ' ' . ($row->student_middle_name ?? '')),
                    'status' => $row->attendance_status,
                    'verification_method' => $row->verification_method,
                    'recorded_at' => $row->recorded_at,
                    'override_reason' => $row->override_reason,
                ];
            })->values(),
            'meta' => [
                'current_page' => $pagination->currentPage(),
                'last_page' => $pagination->lastPage(),
                'per_page' => $pagination->perPage(),
                'total' => $pagination->total(),
                'from' => $pagination->firstItem(),
                'to' => $pagination->lastItem(),
            ],
            'links' => [
                'next' => $pagination->nextPageUrl(),
                'prev' => $pagination->previousPageUrl(),
            ],
            'totals' => $totals,
        ];
    }

    private function kpis(array $filters): array
    {
        $scopeFilters = $filters;
        $scopeFilters['status'] = null;

        $summary = $this->recordTotals($scopeFilters, false);

        $lateCurrent = $summary['late'];
        $latePrevious = $this->previousLateCount($scopeFilters);

        $atRiskTeams = $this->recordsBaseQuery($scopeFilters)
            ->select('t.id')
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupBy('t.id')
            ->get()
            ->filter(function ($row) {
                $signals = (int) $row->absent_count + (int) $row->late_count + (int) $row->no_response_count;
                $total = max(1, (int) $row->total_rows);
                return ($signals / $total) >= 0.25;
            })
            ->count();

        return [
            'summary' => [
                'total_records' => $summary['total_records'],
                'attendance_rate_percent' => $summary['total_records'] > 0
                    ? round(($summary['present'] / $summary['total_records']) * 100, 2)
                    : 0,
                'response_rate_percent' => $summary['total_records'] > 0
                    ? round((($summary['total_records'] - $summary['no_response']) / $summary['total_records']) * 100, 2)
                    : 0,
                'counts' => [
                    'present' => $summary['present'],
                    'absent' => $summary['absent'],
                    'late' => $summary['late'],
                    'excused' => $summary['excused'],
                    'no_response' => $summary['no_response'],
                ],
            ],
            'needs_attention' => [
                'no_response' => $summary['no_response'],
                'late_spike_delta' => max(0, $lateCurrent - $latePrevious),
                'at_risk_teams' => $atRiskTeams,
            ],
        ];
    }

    private function previousLateCount(array $filters): int
    {
        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            return 0;
        }

        $start = Carbon::parse($filters['start_date'])->startOfDay();
        $end = Carbon::parse($filters['end_date'])->endOfDay();
        $days = $start->diffInDays($end) + 1;

        $previousFilters = $filters;
        $previousFilters['start_date'] = $start->copy()->subDays($days)->toDateString();
        $previousFilters['end_date'] = $end->copy()->subDays($days)->toDateString();

        return $this->recordTotals($previousFilters, false)['late'];
    }

    private function recordTotals(array $filters, bool $exceptionOnly): array
    {
        $row = $this->recordsBaseQuery($filters)
            ->when($exceptionOnly, function ($query) {
                $query->where(function ($builder) {
                    $builder->whereNull('sa.id')
                        ->orWhereIn('sa.status', ['absent', 'late']);
                });
            })
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'excused' THEN 1 ELSE 0 END) as excused_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->first();

        return [
            'total_records' => (int) ($row->total_records ?? 0),
            'present' => (int) ($row->present_count ?? 0),
            'absent' => (int) ($row->absent_count ?? 0),
            'late' => (int) ($row->late_count ?? 0),
            'excused' => (int) ($row->excused_count ?? 0),
            'no_response' => (int) ($row->no_response_count ?? 0),
        ];
    }

    private function recordsQuery(array $filters, bool $exceptionOnly)
    {
        return $this->recordsBaseQuery($filters)
            ->when($exceptionOnly, function ($query) {
                $query->where(function ($builder) {
                    $builder->whereNull('sa.id')
                        ->orWhereIn('sa.status', ['absent', 'late']);
                });
            })
            ->select([
                'ts.id as schedule_id',
                'ts.title as schedule_title',
                'ts.type as schedule_type',
                'ts.start_time as schedule_start',
                't.id as team_id',
                't.team_name',
                DB::raw("COALESCE(sp.name, 'Unknown') as sport_name"),
                'st.id as student_id',
                'st.student_id_number',
                'su.first_name as student_first_name',
                'su.middle_name as student_middle_name',
                'su.last_name as student_last_name',
                DB::raw("COALESCE(sa.status, 'no_response') as attendance_status"),
                'sa.verification_method',
                'sa.recorded_at',
                'sa.notes as attendance_note',
                'sa.override_reason',
                DB::raw('DATE(ts.start_time) as schedule_date'),
            ]);
    }

    private function recordsBaseQuery(array $filters)
    {
        $query = DB::table('team_schedules as ts')
            ->join('teams as t', 't.id', '=', 'ts.team_id')
            ->leftJoin('sports as sp', 'sp.id', '=', 't.sport_id')
            ->join('team_players as tp', 'tp.team_id', '=', 't.id')
            ->join('students as st', 'st.id', '=', 'tp.student_id')
            ->join('users as su', 'su.id', '=', 'st.user_id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'st.id');
            });

        $this->applyCommonFilters($query, $filters);

        if (!empty($filters['status']) && $filters['status'] === 'no_response') {
            $query->whereNull('sa.id');
        } elseif (!empty($filters['status'])) {
            $query->where('sa.status', $filters['status']);
        }

        return $query;
    }

    private function calendarSchedules(array $filters)
    {
        $calendarFilters = $filters;
        $calendarFilters['schedule_id'] = null;

        $query = DB::table('team_schedules as ts')
            ->join('teams as t', 't.id', '=', 'ts.team_id')
            ->leftJoin('sports as sp', 'sp.id', '=', 't.sport_id')
            ->leftJoin('team_staff_assignments as tsa', function ($join) {
                $join->on('tsa.team_id', '=', 't.id')
                    ->where('tsa.role', '=', 'head')
                    ->whereNull('tsa.ends_at');
            })
            ->leftJoin('coaches as hc', 'hc.id', '=', 'tsa.coach_id')
            ->leftJoin('users as cu', 'cu.id', '=', 'hc.user_id')
            ->leftJoin('team_players as tp', 'tp.team_id', '=', 't.id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->select([
                'ts.id',
                'ts.title',
                'ts.type',
                'ts.venue',
                'ts.notes',
                't.team_name',
                DB::raw("COALESCE(sp.name, 'Unknown') as sport_name"),
                DB::raw("TRIM(CONCAT(COALESCE(cu.first_name, ''), ' ', COALESCE(cu.last_name, ''))) as coach_name"),
                'ts.start_time',
                'ts.end_time',
            ])
            ->selectRaw('COUNT(tp.student_id) as roster_total')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'excused' THEN 1 ELSE 0 END) as excused_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupBy([
                'ts.id',
                'ts.title',
                'ts.type',
                'ts.venue',
                'ts.notes',
                't.team_name',
                'sp.name',
                'cu.first_name',
                'cu.last_name',
                'ts.start_time',
                'ts.end_time',
            ])
            ->orderBy('ts.start_time');

        $this->applyCommonFilters($query, $calendarFilters);

        if (!empty($calendarFilters['status']) && $calendarFilters['status'] === 'no_response') {
            $query->havingRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) > 0');
        } elseif (!empty($calendarFilters['status'])) {
            $query->havingRaw("SUM(CASE WHEN sa.status = ? THEN 1 ELSE 0 END) > 0", [$calendarFilters['status']]);
        }

        return $query->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'type' => $row->type,
                'venue' => $row->venue,
                'notes' => $row->notes,
                'team_name' => $row->team_name,
                'sport' => $row->sport_name,
                'coach_name' => trim((string) ($row->coach_name ?? '')) ?: 'Unassigned',
                'start' => Carbon::parse($row->start_time)->toIso8601String(),
                'end' => Carbon::parse($row->end_time)->toIso8601String(),
                'counts' => [
                    'roster_total' => (int) $row->roster_total,
                    'present' => (int) $row->present_count,
                    'absent' => (int) $row->absent_count,
                    'late' => (int) $row->late_count,
                    'excused' => (int) $row->excused_count,
                    'no_response' => (int) $row->no_response_count,
                ],
            ])
            ->values();
    }

    private function filterOptions(): array
    {
        return [
            'sports' => Sport::supported()->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->with('sport:id,name')
                ->whereNull('archived_at')
                ->orderBy('team_name')
                ->get(['id', 'team_name', 'sport_id'])
                ->map(fn ($team) => [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport_name' => $team->sport?->name ?? 'Unknown',
                ])
                ->values(),
            'coaches' => Coach::query()
                ->join('users', 'users.id', '=', 'coaches.user_id')
                ->where('users.role', 'coach')
                ->orderBy('users.last_name')
                ->orderBy('users.first_name')
                ->get(['coaches.id', 'users.first_name', 'users.last_name'])
                ->map(fn ($coach) => [
                    'coach_id' => $coach->id,
                    'name' => trim(($coach->first_name ?? '') . ' ' . ($coach->last_name ?? '')),
                ])
                ->values(),
            'schedule_types' => DB::table('team_schedules')
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->filter()
                ->values(),
            'statuses' => [
                ['value' => 'present', 'label' => 'Present'],
                ['value' => 'absent', 'label' => 'Absent'],
                ['value' => 'late', 'label' => 'Late'],
                ['value' => 'excused', 'label' => 'Excused'],
                ['value' => 'no_response', 'label' => 'No Response'],
            ],
        ];
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'tab' => 'nullable|in:calendar,attendance,exceptions',
            'search' => 'nullable|string|max:150',
            'sport_id' => ['nullable', 'integer', Rule::exists('sports', 'id')->where(fn ($query) => $query->whereIn('name', Sport::supportedNames()))],
            'team_id' => 'nullable|integer|exists:teams,id',
            'coach_id' => 'nullable|integer|exists:coaches,id',
            'schedule_id' => 'nullable|integer|exists:team_schedules,id',
            'schedule_type' => 'nullable|string|max:50',
            'status' => 'nullable|in:present,absent,late,excused,no_response',
            'period' => 'nullable|in:today,week,month',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort' => 'nullable|in:schedule_start,team_name,student_name,status',
            'direction' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
            'sport' => 'nullable|string|max:50',
        ]);

        $filters = [
            'tab' => $validated['tab'] ?? 'calendar',
            'search' => $validated['search'] ?? null,
            'sport_id' => isset($validated['sport_id']) ? (int) $validated['sport_id'] : null,
            'team_id' => isset($validated['team_id']) ? (int) $validated['team_id'] : null,
            'coach_id' => isset($validated['coach_id']) ? (int) $validated['coach_id'] : null,
            'schedule_id' => isset($validated['schedule_id']) ? (int) $validated['schedule_id'] : null,
            'schedule_type' => $validated['schedule_type'] ?? null,
            'status' => $validated['status'] ?? null,
            'period' => $validated['period'] ?? 'month',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'sort' => $validated['sort'] ?? 'schedule_start',
            'direction' => $validated['direction'] ?? 'desc',
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 15),
        ];

        if (!$filters['start_date'] && !$filters['end_date']) {
            $range = $this->periodRange((string) $filters['period']);
            $filters['start_date'] = $range['start_date'];
            $filters['end_date'] = $range['end_date'];
        }

        return $filters;
    }

    private function periodRange(string $period): array
    {
        $today = now();

        if ($period === 'today') {
            return [
                'start_date' => $today->copy()->startOfDay()->toDateString(),
                'end_date' => $today->copy()->endOfDay()->toDateString(),
            ];
        }

        if ($period === 'week') {
            return [
                'start_date' => $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                'end_date' => $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ];
        }

        return [
            'start_date' => $today->copy()->startOfMonth()->toDateString(),
            'end_date' => $today->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function normalizeSportKey(?string $sport): ?string
    {
        if ($sport === null) {
            return null;
        }

        $normalized = strtolower(trim($sport));
        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        $normalized = preg_replace('/[_-]+/', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }

    private function applyCommonFilters($query, array $filters, bool $includeSearch = true): void
    {
        if (!empty($filters['sport_id'])) {
            $query->where('t.sport_id', (int) $filters['sport_id']);
        }

        if (!empty($filters['team_id'])) {
            $query->where('t.id', (int) $filters['team_id']);
        }

        if (!empty($filters['coach_id'])) {
            $coachId = (int) $filters['coach_id'];
            $query->whereExists(function ($subQuery) use ($coachId) {
                $subQuery->selectRaw('1')
                    ->from('team_staff_assignments as tsa')
                    ->whereColumn('tsa.team_id', 't.id')
                    ->whereNull('tsa.ends_at')
                    ->where('tsa.coach_id', $coachId);
            });
        }

        if (!empty($filters['schedule_id'])) {
            $query->where('ts.id', (int) $filters['schedule_id']);
        }

        if (!empty($filters['schedule_type'])) {
            $query->where('ts.type', $filters['schedule_type']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('ts.start_time', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (!empty($filters['end_date'])) {
            $query->where('ts.start_time', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if ($includeSearch && !empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $normalizedStatus = strtolower(str_replace(' ', '_', $search));

            $query->where(function ($builder) use ($search, $normalizedStatus) {
                $builder->where('ts.title', 'like', "%{$search}%")
                    ->orWhere('ts.venue', 'like', "%{$search}%")
                    ->orWhere('ts.type', 'like', "%{$search}%")
                    ->orWhere('t.team_name', 'like', "%{$search}%")
                    ->orWhere('sp.name', 'like', "%{$search}%")
                    ->orWhereRaw("DATE_FORMAT(ts.start_time, '%Y-%m-%d') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("DATE_FORMAT(ts.start_time, '%b %e, %Y') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("DATE_FORMAT(ts.start_time, '%M %e, %Y') LIKE ?", ["%{$search}%"])
                    ->orWhereExists(function ($subQuery) use ($search) {
                        $subQuery->selectRaw('1')
                            ->from('team_staff_assignments as tsa')
                            ->join('coaches as c', 'c.id', '=', 'tsa.coach_id')
                            ->join('users as cu', 'cu.id', '=', 'c.user_id')
                            ->whereColumn('tsa.team_id', 't.id')
                            ->whereNull('tsa.ends_at')
                            ->where(function ($coachQuery) use ($search) {
                                $coachQuery->where('cu.first_name', 'like', "%{$search}%")
                                    ->orWhere('cu.last_name', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereExists(function ($subQuery) use ($search) {
                        $subQuery->selectRaw('1')
                            ->from('team_players as search_tp')
                            ->join('students as search_st', 'search_st.id', '=', 'search_tp.student_id')
                            ->join('users as search_su', 'search_su.id', '=', 'search_st.user_id')
                            ->whereColumn('search_tp.team_id', 't.id')
                            ->where(function ($studentQuery) use ($search) {
                                $studentQuery->where('search_su.first_name', 'like', "%{$search}%")
                                    ->orWhere('search_su.last_name', 'like', "%{$search}%")
                                    ->orWhere('search_st.student_id_number', 'like', "%{$search}%");
                            });
                    });

                if (in_array($normalizedStatus, ['present', 'absent', 'late', 'excused', 'no_response'], true)) {
                    if ($normalizedStatus === 'no_response') {
                        $builder->orWhereNull('sa.id');
                    } else {
                        $builder->orWhere('sa.status', $normalizedStatus);
                    }
                }
            });
        }
    }
}
