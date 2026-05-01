<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicDocument;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Sport;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function attendance(Request $request)
    {
        $filters = $this->validatedFilters($request);

        return Inertia::render('Admin/Reports/Attendance', [
            'filters' => [
                'selected' => [
                    'team_id' => $filters['team_id'],
                    'status' => $filters['status'],
                    'start_date' => $filters['start_date'],
                    'end_date' => $filters['end_date'],
                ],
                'options' => [
                    'teams' => $this->teamOptions(),
                    'statuses' => $this->attendanceStatusOptions(),
                ],
            ],
            'attendanceReport' => [
                'summary' => $this->attendanceSummary($filters),
                'rows' => $this->attendanceRows($filters),
            ],
        ]);
    }

    public function roster(Request $request)
    {
        $filters = $this->validatedFilters($request);

        return Inertia::render('Admin/Reports/Roster', [
            'filters' => [
                'selected' => [
                    'sport_id' => $filters['sport_id'],
                    'team_id' => $filters['team_id'],
                    'player_status' => $filters['player_status'],
                    'year' => $filters['year'],
                ],
                'options' => [
                    'sports' => $this->sportOptions(),
                    'teams' => $this->teamOptions(),
                    'player_statuses' => $this->playerStatusOptions(),
                    'years' => $this->yearOptions(),
                ],
            ],
            'rosterReport' => [
                'summary' => $this->rosterSummary($filters),
                'rows' => $this->rosterRows($filters),
            ],
        ]);
    }

    public function academics(Request $request)
    {
        $filters = $this->validatedFilters($request);

        return Inertia::render('Admin/Reports/Academics', [
            'filters' => [
                'selected' => [
                    'period_id' => $filters['period_id'],
                    'team_id' => $filters['team_id'],
                    'academic_status' => $filters['academic_status'],
                    'start_date' => $filters['start_date'],
                    'end_date' => $filters['end_date'],
                ],
                'options' => [
                    'periods' => $this->periodOptions(),
                    'teams' => $this->teamOptions(),
                    'academic_statuses' => $this->academicStatusOptions(),
                ],
            ],
            'academicReport' => [
                'summary' => $this->academicSummary($filters),
                'rows' => $this->academicRows($filters),
            ],
        ]);
    }

    public function exportAttendanceCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $rows = $this->attendanceRows($filters);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Student',
                'Student ID',
                'Team',
                'Sport',
                'Sessions',
                'Present',
                'Absent',
                'Late',
                'Excused',
                'No Response',
                'Attendance Rate',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student_name'],
                    $row['student_id_number'],
                    $row['team_name'],
                    $row['sport_name'],
                    $row['total_sessions'],
                    $row['present'],
                    $row['absent'],
                    $row['late'],
                    $row['excused'],
                    $row['no_response'],
                    $row['attendance_rate'] . '%',
                ]);
            }

            fclose($handle);
        }, 'attendance-summary-report.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function printAttendanceSummary(Request $request)
    {
        $filters = $this->validatedFilters($request);

        return view('print.attendance-summary-report', [
            'rows' => $this->attendanceRows($filters),
            'summary' => $this->attendanceSummary($filters),
            'filtersSummary' => $this->filtersSummary($filters),
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    public function exportRosterCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $rows = $this->rosterRows($filters);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Student',
                'Student ID',
                'Team',
                'Sport',
                'Year',
                'Jersey Number',
                'Position',
                'Player Status',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student_name'],
                    $row['student_id_number'],
                    $row['team_name'],
                    $row['sport_name'],
                    $row['year'],
                    $row['jersey_number'],
                    $row['athlete_position'],
                    $row['player_status'],
                ]);
            }

            fclose($handle);
        }, 'team-roster-report.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function printRosterSummary(Request $request)
    {
        $filters = $this->validatedFilters($request);

        return view('print.team-roster-report', [
            'rows' => $this->rosterRows($filters),
            'summary' => $this->rosterSummary($filters),
            'filtersSummary' => $this->filtersSummary($filters),
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    public function exportAcademicCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $rows = $this->academicRows($filters);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Student',
                'Student ID',
                'Team',
                'Period',
                'Document Type',
                'Uploaded At',
                'Academic Status',
                'GPA',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student_name'],
                    $row['student_id_number'],
                    $row['team_name'],
                    $row['period_label'],
                    $row['document_type'],
                    $row['uploaded_at'],
                    $row['academic_status'],
                    $row['gpa'],
                ]);
            }

            fclose($handle);
        }, 'academic-submission-status-report.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function printAcademicSummary(Request $request)
    {
        $filters = $this->validatedFilters($request);

        return view('print.academic-submission-report', [
            'rows' => $this->academicRows($filters),
            'summary' => $this->academicSummary($filters),
            'filtersSummary' => $this->filtersSummary($filters),
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    private function attendanceSummary(array $filters): array
    {
        $row = $this->attendanceBaseQuery($filters)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'excused' THEN 1 ELSE 0 END) as excused_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->first();

        $totalRecords = (int) ($row->total_records ?? 0);
        $present = (int) ($row->present_count ?? 0);
        $absent = (int) ($row->absent_count ?? 0);
        $late = (int) ($row->late_count ?? 0);
        $excused = (int) ($row->excused_count ?? 0);
        $noResponse = (int) ($row->no_response_count ?? 0);

        return [
            'total_records' => $totalRecords,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused,
            'no_response' => $noResponse,
            'attendance_rate' => $totalRecords > 0 ? round(($present / $totalRecords) * 100, 2) : 0,
        ];
    }

    private function attendanceRows(array $filters)
    {
        return $this->attendanceBaseQuery($filters)
            ->select([
                'st.id as student_id',
                'st.student_id_number',
                't.team_name',
                DB::raw("COALESCE(sp.name, 'Unknown') as sport_name"),
                'su.first_name',
                'su.middle_name',
                'su.last_name',
            ])
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'excused' THEN 1 ELSE 0 END) as excused_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupBy([
                'st.id',
                'st.student_id_number',
                't.team_name',
                'sp.name',
                'su.first_name',
                'su.middle_name',
                'su.last_name',
            ])
            ->orderBy('t.team_name')
            ->orderBy('su.last_name')
            ->orderBy('su.first_name')
            ->get()
            ->map(function ($row) {
                $totalSessions = (int) ($row->total_sessions ?? 0);
                $present = (int) ($row->present_count ?? 0);

                return [
                    'student_id' => (int) $row->student_id,
                    'student_id_number' => $row->student_id_number,
                    'student_name' => trim(($row->last_name ?? '') . ', ' . ($row->first_name ?? '') . ' ' . ($row->middle_name ?? '')),
                    'team_name' => $row->team_name,
                    'sport_name' => $row->sport_name,
                    'total_sessions' => $totalSessions,
                    'present' => $present,
                    'absent' => (int) ($row->absent_count ?? 0),
                    'late' => (int) ($row->late_count ?? 0),
                    'excused' => (int) ($row->excused_count ?? 0),
                    'no_response' => (int) ($row->no_response_count ?? 0),
                    'attendance_rate' => $totalSessions > 0 ? round(($present / $totalSessions) * 100, 2) : 0,
                ];
            })
            ->values();
    }

    private function attendanceBaseQuery(array $filters)
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

        if (!empty($filters['team_id'])) {
            $query->where('t.id', (int) $filters['team_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('ts.start_time', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (!empty($filters['end_date'])) {
            $query->where('ts.start_time', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (!empty($filters['status']) && $filters['status'] === 'no_response') {
            $query->whereNull('sa.id');
        } elseif (!empty($filters['status'])) {
            $query->where('sa.status', $filters['status']);
        }

        return $query;
    }

    private function rosterSummary(array $filters): array
    {
        $row = $this->rosterBaseQuery($filters)
            ->selectRaw('COUNT(*) as total_players')
            ->selectRaw("SUM(CASE WHEN COALESCE(tp.player_status, 'active') = 'active' THEN 1 ELSE 0 END) as active_count")
            ->selectRaw("SUM(CASE WHEN tp.player_status = 'injured' THEN 1 ELSE 0 END) as injured_count")
            ->selectRaw("SUM(CASE WHEN tp.player_status = 'suspended' THEN 1 ELSE 0 END) as suspended_count")
            ->selectRaw("SUM(CASE WHEN tp.player_status = 'inactive' THEN 1 ELSE 0 END) as inactive_count")
            ->selectRaw("SUM(CASE WHEN tp.jersey_number IS NULL OR tp.jersey_number = '' THEN 1 ELSE 0 END) as jersey_pending_count")
            ->selectRaw("SUM(CASE WHEN tp.athlete_position IS NULL OR tp.athlete_position = '' THEN 1 ELSE 0 END) as position_pending_count")
            ->first();

        return [
            'total_players' => (int) ($row->total_players ?? 0),
            'active' => (int) ($row->active_count ?? 0),
            'injured' => (int) ($row->injured_count ?? 0),
            'suspended' => (int) ($row->suspended_count ?? 0),
            'inactive' => (int) ($row->inactive_count ?? 0),
            'jersey_pending' => (int) ($row->jersey_pending_count ?? 0),
            'position_pending' => (int) ($row->position_pending_count ?? 0),
        ];
    }

    private function rosterRows(array $filters)
    {
        return $this->rosterBaseQuery($filters)
            ->select([
                'st.id as student_id',
                'st.student_id_number',
                't.team_name',
                't.year',
                DB::raw("COALESCE(sp.name, 'Unknown') as sport_name"),
                'su.first_name',
                'su.middle_name',
                'su.last_name',
                'tp.jersey_number',
                'tp.athlete_position',
                DB::raw("COALESCE(tp.player_status, 'active') as player_status"),
            ])
            ->orderBy('t.team_name')
            ->orderBy('su.last_name')
            ->orderBy('su.first_name')
            ->get()
            ->map(fn ($row) => [
                'student_id' => (int) $row->student_id,
                'student_id_number' => $row->student_id_number,
                'student_name' => trim(($row->last_name ?? '') . ', ' . ($row->first_name ?? '') . ' ' . ($row->middle_name ?? '')),
                'team_name' => $row->team_name,
                'sport_name' => $row->sport_name,
                'year' => $row->year,
                'jersey_number' => $row->jersey_number ?: 'Pending',
                'athlete_position' => $row->athlete_position ?: 'Pending',
                'player_status' => ucfirst((string) $row->player_status),
            ])
            ->values();
    }

    private function rosterBaseQuery(array $filters)
    {
        $query = DB::table('team_players as tp')
            ->join('teams as t', 't.id', '=', 'tp.team_id')
            ->leftJoin('sports as sp', 'sp.id', '=', 't.sport_id')
            ->join('students as st', 'st.id', '=', 'tp.student_id')
            ->join('users as su', 'su.id', '=', 'st.user_id');

        if (!empty($filters['team_id'])) {
            $query->where('t.id', (int) $filters['team_id']);
        }

        if (!empty($filters['sport_id'])) {
            $query->where('t.sport_id', (int) $filters['sport_id']);
        }

        if (!empty($filters['year'])) {
            $query->where('t.year', $filters['year']);
        }

        if (!empty($filters['player_status'])) {
            $query->where(DB::raw("COALESCE(tp.player_status, 'active')"), $filters['player_status']);
        }

        return $query;
    }

    private function academicSummary(array $filters): array
    {
        $rows = $this->academicRows($filters);

        return [
            'total_submissions' => $rows->count(),
            'eligible' => $rows->where('academic_status', 'Eligible')->count(),
            'pending_review' => $rows->where('academic_status', 'Pending review')->count(),
            'ineligible' => $rows->where('academic_status', 'Ineligible')->count(),
            'pending' => $rows->where('academic_status', 'Pending')->count(),
        ];
    }

    private function academicRows(array $filters)
    {
        $query = DB::table('academic_documents as d')
            ->join('academic_document_types as dt', 'dt.id', '=', 'd.document_type_id')
            ->join('students as s', 's.id', '=', 'd.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->leftJoin('academic_periods as p', 'p.id', '=', 'd.academic_period_id')
            ->leftJoin('academic_eligibility_evaluations as e', function ($join) {
                $join->on('e.student_id', '=', 'd.student_id')
                    ->on('e.academic_period_id', '=', 'd.academic_period_id');
            })
            ->leftJoin('teams as t', function ($join) {
                $join->on('t.id', '=', DB::raw('(SELECT tp.team_id FROM team_players tp WHERE tp.student_id = d.student_id ORDER BY tp.id ASC LIMIT 1)'));
            })
            ->select([
                'd.id as document_id',
                's.id as student_id',
                's.student_id_number',
                'su.first_name',
                'su.last_name',
                't.team_name',
                'dt.code as document_type',
                'd.uploaded_at',
                'p.school_year',
                'p.term',
                'e.gpa',
                'e.final_status',
            ]);

        if (!empty($filters['period_id'])) {
            $query->where('d.academic_period_id', (int) $filters['period_id']);
        }
        if (!empty($filters['team_id'])) {
            $query->whereExists(function ($sq) use ($filters) {
                $sq->selectRaw('1')
                    ->from('team_players as tp')
                    ->whereColumn('tp.student_id', 's.id')
                    ->where('tp.team_id', (int) $filters['team_id']);
            });
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('d.uploaded_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('d.uploaded_at', '<=', $filters['end_date']);
        }
        if (!empty($filters['academic_status'])) {
            if ($filters['academic_status'] === 'pending') {
                $query->where(function ($q) {
                    $q->whereNull('e.id')
                        ->orWhereNull('e.gpa');
                });
            } elseif ($filters['academic_status'] === 'eligible') {
                $query->where('e.final_status', 'eligible');
            } elseif ($filters['academic_status'] === 'pending_review') {
                $query->where('e.final_status', 'pending_review');
            } elseif ($filters['academic_status'] === 'ineligible') {
                $query->where('e.final_status', 'ineligible');
            }
        }

        return $query
            ->orderByDesc('d.uploaded_at')
            ->get()
            ->map(function ($row) {
                return [
                    'document_id' => (int) $row->document_id,
                    'student_id' => (int) $row->student_id,
                    'student_name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                    'student_id_number' => $row->student_id_number,
                    'team_name' => $row->team_name ?? 'No team',
                    'document_type' => $row->document_type,
                    'uploaded_at' => $row->uploaded_at,
                    'period_label' => trim(($row->school_year ?? '') . ' ' . $this->termLabel((string) ($row->term ?? ''))),
                    'academic_status' => ucfirst(str_replace('_', ' ', (string) (
                        $row->final_status ?: AcademicEligibilityEvaluation::statusForGpa(
                            $row->gpa !== null ? (float) $row->gpa : null
                        ) ?? 'pending'
                    ))),
                    'gpa' => $row->gpa !== null ? number_format((float) $row->gpa, 2) : 'N/A',
                ];
            })
            ->values();
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'period_id' => 'nullable|integer|exists:academic_periods,id',
            'sport_id' => ['nullable', 'integer', Rule::exists('sports', 'id')->where(fn ($query) => $query->whereIn('name', Sport::supportedNames()))],
            'team_id' => 'nullable|integer|exists:teams,id',
            'status' => 'nullable|in:present,absent,late,excused,no_response',
            'academic_status' => 'nullable|in:eligible,pending_review,ineligible,pending',
            'player_status' => 'nullable|in:active,injured,suspended,inactive',
            'year' => 'nullable|digits:4',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        return [
            'period_id' => !empty($validated['period_id']) ? (int) $validated['period_id'] : null,
            'sport_id' => !empty($validated['sport_id']) ? (int) $validated['sport_id'] : null,
            'team_id' => !empty($validated['team_id']) ? (int) $validated['team_id'] : null,
            'status' => $validated['status'] ?? null,
            'academic_status' => $validated['academic_status'] ?? null,
            'player_status' => $validated['player_status'] ?? null,
            'year' => $validated['year'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];
    }

    private function filtersSummary(array $filters): array
    {
        $selectedPeriod = !empty($filters['period_id'])
            ? AcademicPeriod::query()->where('id', $filters['period_id'])->first()
            : null;

        return [
            'period' => $filters['period_id']
                ? ($selectedPeriod
                    ? trim(($selectedPeriod->school_year ?? '') . ' ' . $this->termLabel((string) ($selectedPeriod->term ?? '')))
                    : 'Selected Period')
                : 'All Periods',
            'team' => $filters['team_id']
                ? (Team::query()->where('id', $filters['team_id'])->value('team_name') ?? 'Unknown')
                : 'All Teams',
            'sport' => $filters['sport_id']
                ? (Sport::query()->where('id', $filters['sport_id'])->value('name') ?? 'Unknown')
                : 'All Sports',
            'status' => $filters['status'] ? ucfirst(str_replace('_', ' ', $filters['status'])) : 'All Statuses',
            'academic_status' => $filters['academic_status'] ? ucfirst(str_replace('_', ' ', $filters['academic_status'])) : 'All Academic Statuses',
            'player_status' => $filters['player_status'] ? ucfirst(str_replace('_', ' ', $filters['player_status'])) : 'All Player Statuses',
            'year' => $filters['year'] ?? 'All Years',
            'date_range' => ($filters['start_date'] && $filters['end_date'])
                ? "{$filters['start_date']} to {$filters['end_date']}"
                : 'All Dates',
        ];
    }

    private function periodOptions()
    {
        return AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->get(['id', 'school_year', 'term'])
            ->map(fn (AcademicPeriod $period) => [
                'id' => $period->id,
                'label' => trim($period->school_year . ' ' . $this->termLabel((string) $period->term)),
            ])
            ->values();
    }

    private function sportOptions()
    {
        return Sport::query()
            ->supported()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();
    }

    private function teamOptions()
    {
        return Team::query()
            ->with('sport:id,name')
            ->orderBy('team_name')
            ->get(['id', 'team_name', 'sport_id'])
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'sport_name' => $team->sport?->name ?? 'Unknown',
            ])
            ->values();
    }

    private function yearOptions()
    {
        return Team::query()
            ->select('year')
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->values();
    }

    private function attendanceStatusOptions(): array
    {
        return [
            ['value' => 'present', 'label' => 'Present'],
            ['value' => 'absent', 'label' => 'Absent'],
            ['value' => 'late', 'label' => 'Late'],
            ['value' => 'excused', 'label' => 'Excused'],
            ['value' => 'no_response', 'label' => 'No Response'],
        ];
    }

    private function academicStatusOptions(): array
    {
        return [
            ['value' => 'eligible', 'label' => 'Eligible'],
            ['value' => 'pending_review', 'label' => 'Pending Review'],
            ['value' => 'ineligible', 'label' => 'Ineligible'],
            ['value' => 'pending', 'label' => 'Pending'],
        ];
    }

    private function playerStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'injured', 'label' => 'Injured'],
            ['value' => 'suspended', 'label' => 'Suspended'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ];
    }

    private function termLabel(string $term): string
    {
        return match ($term) {
            '1st_sem' => '1st Sem',
            '2nd_sem' => '2nd Sem',
            'summer' => 'Summer',
            default => $term !== '' ? $term : 'No Term',
        };
    }
}
