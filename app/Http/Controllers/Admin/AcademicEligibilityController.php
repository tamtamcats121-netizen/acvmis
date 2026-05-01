<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicDocument;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\Team;
use App\Services\SystemNotificationService;
use App\Services\TeamPlayerStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AcademicEligibilityController extends Controller
{
    public function __construct(
        private SystemNotificationService $notifications,
        private TeamPlayerStatusService $teamPlayerStatuses,
    )
    {
    }

    public function index(Request $request)
    {
        $periods = AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->get();

        $selectedPeriodId = (int) $request->query('period_id', 0);
        if (!$selectedPeriodId) {
            $selectedPeriodId = (int) ($periods->first()->id ?? 0);
        }

        return Inertia::render('Admin/Academics', [
            'periods' => $periods->map(fn ($p) => [
                'id' => $p->id,
                'school_year' => $p->school_year,
                'term' => $p->term,
                'starts_on' => optional($p->starts_on)->toDateString(),
                'ends_on' => optional($p->ends_on)->toDateString(),
                'status' => $this->resolveStatus($p),
            ]),
            'selectedPeriodId' => $selectedPeriodId ?: null,
        ]);
    }

    public function submissions(Request $request)
    {
        $periods = AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->get();

        $selectedPeriodId = (int) $request->query('period_id', 0);
        if (!$selectedPeriodId) {
            $selectedPeriodId = (int) ($periods->first()->id ?? 0);
        }

        $docs = collect();
        if ($selectedPeriodId) {
            $docs = AcademicDocument::query()
                ->periodSubmission()
                ->with([
                    'student.user',
                    'latestOcrRun.parsedSummary',
                ])
                ->where('academic_period_id', $selectedPeriodId)
                ->latest('uploaded_at')
                ->get();
        }

        $evalByStudent = AcademicEligibilityEvaluation::query()
            ->when($selectedPeriodId, fn ($q) => $q->where('academic_period_id', $selectedPeriodId))
            ->get()
            ->keyBy('student_id');

        $rows = $docs->map(function ($doc) use ($evalByStudent) {
            $student = $doc->student;
            $evaluation = $evalByStudent->get($doc->student_id);

            return [
                'document_id' => $doc->id,
                'student_id' => $doc->student_id,
                'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_id_number' => $student->student_id_number ?? null,
                'uploaded_at' => optional($doc->uploaded_at)->toDateTimeString(),
                'document_type' => $doc->document_type,
                'notes' => $doc->notes,
                'file_url' => $doc->id ? route('files.academic', $doc->id) : null,
                'ocr' => $this->ocrPayload($doc->latestOcrRun),
                'evaluation' => $evaluation ? [
                    'id' => $evaluation->id,
                    'gpa' => $evaluation->gpa,
                    'status' => $evaluation->status,
                    'remarks' => $evaluation->remarks,
                    'evaluated_at' => optional($evaluation->evaluated_at)->toDateTimeString(),
                    'evaluation_source' => $evaluation->evaluation_source,
                    'review_required' => (bool) $evaluation->review_required,
                ] : null,
            ];
        })->values();

        return Inertia::render('Admin/AcademicSubmissions', [
            'periods' => $periods->map(fn ($p) => [
                'id' => $p->id,
                'school_year' => $p->school_year,
                'term' => $p->term,
                'starts_on' => optional($p->starts_on)->toDateString(),
                'ends_on' => optional($p->ends_on)->toDateString(),
                'status' => $this->resolveStatus($p),
            ]),
            'selectedPeriodId' => $selectedPeriodId ?: null,
            'rows' => $rows,
        ]);
    }

    public function pastPeriods()
    {
        $periods = AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->get();

        $active = $periods->first();
        $past = $periods->skip(1);
        $pastIds = $past->pluck('id')->filter()->all();
        $submissionCounts = !empty($pastIds)
            ? AcademicDocument::query()
                ->whereIn('academic_period_id', $pastIds)
                ->select('academic_period_id', DB::raw('count(*) as submissions_count'))
                ->groupBy('academic_period_id')
                ->pluck('submissions_count', 'academic_period_id')
            : collect();

        return Inertia::render('Admin/AcademicPastPeriods', [
            'activePeriod' => $active ? [
                'id' => $active->id,
                'school_year' => $active->school_year,
                'term' => $active->term,
            ] : null,
            'periods' => $past->map(fn ($p) => [
                'id' => $p->id,
                'school_year' => $p->school_year,
                'term' => $p->term,
                'starts_on' => optional($p->starts_on)->toDateString(),
                'ends_on' => optional($p->ends_on)->toDateString(),
                'status' => $this->resolveStatus($p),
                'submissions_count' => (int) ($submissionCounts[$p->id] ?? 0),
            ])->values(),
        ]);
    }

    public function storePeriod(Request $request)
    {
        $validated = $request->validate([
            'school_year' => 'required|string|max:9',
            'term' => 'required|in:1st_sem,2nd_sem,summer',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
        ]);

        AcademicPeriod::create([
            'school_year' => $validated['school_year'],
            'term' => $validated['term'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
        ]);
        $this->teamPlayerStatuses->syncAll();

        return back()->with('success', 'Academic period created.');
    }

    public function updateStatus(Request $request, AcademicPeriod $period)
    {
        $this->teamPlayerStatuses->syncAll();

        return back()->with('success', 'Academic period updated.');
    }

    public function destroyPeriod(AcademicPeriod $period)
    {
        $hasDocs = AcademicDocument::query()
            ->where('academic_period_id', $period->id)
            ->exists();

        $hasEvaluations = AcademicEligibilityEvaluation::query()
            ->where('academic_period_id', $period->id)
            ->exists();

        if ($hasDocs || $hasEvaluations) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => 'This period already has submissions or evaluations.',
                ], 422);
            }

            return back()->with('error', 'This period already has submissions or evaluations.');
        }

        $period->delete();
        $this->teamPlayerStatuses->syncAll();

        return back()->with('success', 'Academic period deleted.');
    }

    public function evaluate(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:academic_periods,id',
            'student_id' => 'required|exists:students,id',
            'document_id' => 'required|exists:academic_documents,id',
            'gpa' => 'nullable|numeric|min:0|max:100',
            'remarks' => 'nullable|string',
        ]);

        $doc = AcademicDocument::findOrFail((int) $validated['document_id']);
        abort_unless(
            (int) $doc->student_id === (int) $validated['student_id']
            && (int) $doc->academic_period_id === (int) $validated['period_id'],
            422,
            'Invalid document for selected student/period.'
        );

        $period = AcademicPeriod::find((int) $validated['period_id']);

        AcademicEligibilityEvaluation::updateOrCreate(
            [
                'student_id' => (int) $validated['student_id'],
                'academic_period_id' => (int) $validated['period_id'],
            ],
            [
                'document_id' => $doc->id,
                'gpa' => $validated['gpa'] !== null ? (float) $validated['gpa'] : null,
                'evaluation_source' => 'manual',
                'final_status' => AcademicEligibilityEvaluation::statusForGpa($validated['gpa'] !== null ? (float) $validated['gpa'] : null),
                'review_required' => AcademicEligibilityEvaluation::interpretGrade(
                    $validated['gpa'] !== null ? (float) $validated['gpa'] : null
                )['review_required'],
                'remarks' => $validated['remarks'] ?? null,
                'evaluated_by' => Auth::id(),
                'evaluated_at' => now(),
            ]
        );

        $doc->update([
            'review_status' => AcademicDocument::REVIEW_STATUS_REVIEWED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $evaluation = AcademicEligibilityEvaluation::query()
            ->where('student_id', (int) $validated['student_id'])
            ->where('academic_period_id', (int) $validated['period_id'])
            ->firstOrFail();

        $student = Student::find((int) $validated['student_id']);
        $studentUserId = (int) ($student?->user_id ?? 0);
        $computedStatus = AcademicEligibilityEvaluation::statusForGpa($validated['gpa'] !== null ? (float) $validated['gpa'] : null);
        $status = strtoupper((string) ($computedStatus ?? 'pending'));
        $periodLabel = $period
            ? "{$period->school_year} {$this->termLabel((string) $period->term)}"
            : 'selected period';

        if ($studentUserId > 0) {
            $message = "Your academic status for {$periodLabel} is {$status}.";
            if (($computedStatus ?? '') === 'eligible') {
                $message .= ' You are now eligible; further submissions for this period are locked.';
            } elseif (($computedStatus ?? '') === 'pending_review') {
                $message .= ' Your record needs additional review before a final eligibility decision.';
            }
            $this->notifications->announce(
                $studentUserId,
                'Academic Evaluation Result',
                $message,
                'academic',
                Auth::id(),
                'notify_academic_alerts'
            );
        }

        $coachUserIds = Team::query()
            ->whereHas('players', fn ($q) => $q->where('student_id', (int) $validated['student_id']))
            ->with(['coach:id,user_id', 'assistantCoach:id,user_id'])
            ->get()
            ->flatMap(function ($team) {
                return [
                    $team->coach?->user_id,
                    $team->assistantCoach?->user_id,
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($coachUserIds) && $student) {
            $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''));
            $this->notifications->announceMany(
                $coachUserIds,
                'Athlete Academic Evaluation',
                "{$studentName} was evaluated as {$status} for {$periodLabel}.",
                'academic',
                Auth::id(),
                'notify_academic_alerts'
            );
        }

        $this->teamPlayerStatuses->syncStudent((int) $validated['student_id']);

        return back()->with('success', 'Academic evaluation saved.');
    }

    public function submissionsRecords(Request $request)
    {
        $filters = $this->validatedRecordsFilters($request);

        $query = DB::table('academic_documents as d')
            ->join('academic_document_types as dt', 'dt.id', '=', 'd.document_type_id')
            ->join('students as s', 's.id', '=', 'd.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->leftJoin('academic_periods as p', 'p.id', '=', 'd.academic_period_id')
            ->leftJoin('academic_eligibility_evaluations as e', function ($join) {
                $join->on('e.student_id', '=', 'd.student_id')
                    ->on('e.academic_period_id', '=', 'd.academic_period_id');
            })
            ->leftJoin('users as evaluator', 'evaluator.id', '=', 'e.evaluated_by')
            ->leftJoin('teams as t', function ($join) {
                $join->on('t.id', '=', DB::raw('(SELECT tp.team_id FROM team_players tp WHERE tp.student_id = d.student_id ORDER BY tp.id ASC LIMIT 1)'));
            })
            ->select([
                'd.id as document_id',
                'd.student_id',
                's.student_id_number',
                'su.first_name',
                'su.last_name',
                'dt.code as document_type',
                'd.uploaded_at',
                'd.notes',
                'd.academic_period_id as period_id',
                'p.school_year',
                'p.term',
                't.id as team_id',
                't.team_name',
                'e.id as evaluation_id',
                'e.gpa as evaluation_gpa',
                'e.final_status as evaluation_final_status',
                'e.remarks as evaluation_remarks',
                'e.evaluation_source',
                'e.review_required',
                'e.evaluated_at',
                DB::raw("TRIM(CONCAT(COALESCE(evaluator.first_name, ''), ' ', COALESCE(evaluator.last_name, ''))) as evaluator_name"),
            ]);

        $this->applyRecordsFilters($query, $filters, 'd', 's', 'e', 'su');

        $perPage = max(10, min(100, (int) $filters['per_page']));
        $paginator = $query
            ->orderByDesc('d.uploaded_at')
            ->paginate($perPage)
            ->withQueryString();

        $documentIds = collect($paginator->items())
            ->pluck('document_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $ocrRunsByDocument = !empty($documentIds)
            ? AcademicDocument::query()
                ->with('latestOcrRun.parsedSummary')
                ->whereIn('id', $documentIds)
                ->get()
                ->keyBy('id')
            : collect();

        return response()->json([
            'data' => collect($paginator->items())->map(function ($row) use ($ocrRunsByDocument) {
                $document = $row->document_id ? $ocrRunsByDocument->get((int) $row->document_id) : null;

                return [
                    'document_id' => (int) $row->document_id,
                    'student_id' => (int) $row->student_id,
                    'student_name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                    'student_id_number' => $row->student_id_number,
                    'team_id' => $row->team_id ? (int) $row->team_id : null,
                    'team_name' => $row->team_name,
                    'document_type' => $row->document_type,
                    'uploaded_at' => $row->uploaded_at,
                    'notes' => $row->notes,
                    'file_url' => $row->document_id ? route('files.academic', $row->document_id) : null,
                    'ocr' => $this->ocrPayload($document?->latestOcrRun),
                    'period' => $row->period_id ? [
                        'id' => (int) $row->period_id,
                        'school_year' => $row->school_year,
                        'term' => $row->term,
                    ] : null,
                    'evaluation' => $row->evaluation_id ? [
                        'id' => (int) $row->evaluation_id,
                        'gpa' => $row->evaluation_gpa !== null ? (float) $row->evaluation_gpa : null,
                        'status' => $row->evaluation_final_status ?: AcademicEligibilityEvaluation::statusForGpa(
                            $row->evaluation_gpa !== null ? (float) $row->evaluation_gpa : null
                        ),
                        'remarks' => $row->evaluation_remarks,
                        'evaluated_at' => $row->evaluated_at,
                        'evaluator_name' => $row->evaluator_name,
                        'evaluation_source' => $row->evaluation_source,
                        'review_required' => (bool) ($row->review_required ?? false),
                    ] : null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function evaluationsRecords(Request $request)
    {
        $filters = $this->validatedRecordsFilters($request);

        $query = DB::table('academic_eligibility_evaluations as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->leftJoin('academic_periods as p', 'p.id', '=', 'e.academic_period_id')
            ->leftJoin('academic_documents as d', 'd.id', '=', 'e.document_id')
            ->leftJoin('academic_document_types as dt', 'dt.id', '=', 'd.document_type_id')
            ->leftJoin('users as evaluator', 'evaluator.id', '=', 'e.evaluated_by')
            ->leftJoin('teams as t', function ($join) {
                $join->on('t.id', '=', DB::raw('(SELECT tp.team_id FROM team_players tp WHERE tp.student_id = e.student_id ORDER BY tp.id ASC LIMIT 1)'));
            })
            ->select([
                'e.id as evaluation_id',
                'e.student_id',
                's.student_id_number',
                'su.first_name',
                'su.last_name',
                'e.academic_period_id as period_id',
                'p.school_year',
                'p.term',
                't.id as team_id',
                't.team_name',
                'd.id as document_id',
                'dt.code as document_type',
                'e.gpa',
                'e.final_status',
                'e.remarks',
                'e.evaluated_at',
                DB::raw("TRIM(CONCAT(COALESCE(evaluator.first_name, ''), ' ', COALESCE(evaluator.last_name, ''))) as evaluator_name"),
            ]);

        $this->applyRecordsFilters($query, $filters, 'd', 's', 'e', 'su');

        $perPage = max(10, min(100, (int) $filters['per_page']));
        $paginator = $query
            ->orderByDesc('e.evaluated_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())->map(function ($row) {
                return [
                    'evaluation_id' => (int) $row->evaluation_id,
                    'student_id' => (int) $row->student_id,
                    'student_name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                    'student_id_number' => $row->student_id_number,
                    'team_id' => $row->team_id ? (int) $row->team_id : null,
                    'team_name' => $row->team_name,
                    'period' => $row->period_id ? [
                        'id' => (int) $row->period_id,
                        'school_year' => $row->school_year,
                        'term' => $row->term,
                    ] : null,
                    'document_id' => $row->document_id ? (int) $row->document_id : null,
                    'document_type' => $row->document_type,
                    'gpa' => $row->gpa !== null ? (float) $row->gpa : null,
                    'status' => $row->final_status ?: AcademicEligibilityEvaluation::statusForGpa(
                        $row->gpa !== null ? (float) $row->gpa : null
                    ),
                    'remarks' => $row->remarks,
                    'evaluated_at' => $row->evaluated_at,
                    'evaluator_name' => $row->evaluator_name,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function exceptions(Request $request)
    {
        $filters = $this->validatedRecordsFilters($request);
        $periodId = $this->resolvePeriodId($filters['period_id'] ?? null);

        if (!$periodId) {
            return response()->json([
                'meta' => ['period_id' => null],
                'kpis' => [
                    'missing_submissions' => 0,
                    'pending_evaluation' => 0,
                    'pending_review' => 0,
                    'ineligible' => 0,
                ],
                'rows' => [],
            ]);
        }

        $teamScope = DB::table('students as s')
            ->select('s.id')
            ->when(!empty($filters['team_id']), function ($q) use ($filters) {
                $q->whereExists(function ($sq) use ($filters) {
                    $sq->selectRaw('1')
                        ->from('team_players as tp')
                        ->whereColumn('tp.student_id', 's.id')
                        ->where('tp.team_id', (int) $filters['team_id']);
                });
            })
            ->when(!empty($filters['coach_id']), function ($q) use ($filters) {
                $coachId = (int) $filters['coach_id'];
                $q->whereExists(function ($sq) use ($coachId) {
                    $sq->selectRaw('1')
                        ->from('team_players as tp')
                        ->join('teams as t', 't.id', '=', 'tp.team_id')
                        ->join('team_staff_assignments as tsa', 'tsa.team_id', '=', 't.id')
                        ->whereColumn('tp.student_id', 's.id')
                        ->whereNull('tsa.ends_at')
                        ->where('tsa.coach_id', $coachId);
                });
            });

        $studentIds = $teamScope->pluck('id');

        $submittedStudentIds = DB::table('academic_documents')
            ->where('academic_period_id', $periodId)
            ->when($studentIds->isNotEmpty(), fn ($q) => $q->whereIn('student_id', $studentIds))
            ->distinct()
            ->pluck('student_id');

        $evaluatedByStatus = DB::table('academic_eligibility_evaluations')
            ->where('academic_period_id', $periodId)
            ->when($studentIds->isNotEmpty(), fn ($q) => $q->whereIn('student_id', $studentIds))
            ->selectRaw("SUM(CASE WHEN final_status = 'pending_review' THEN 1 ELSE 0 END) as pending_review_count")
            ->selectRaw("SUM(CASE WHEN final_status = 'ineligible' THEN 1 ELSE 0 END) as ineligible_count")
            ->selectRaw("SUM(CASE WHEN gpa IS NOT NULL THEN 1 ELSE 0 END) as evaluated_count")
            ->first();

        $missingSubmissions = $studentIds->diff($submittedStudentIds)->count();
        $pendingEvaluation = max(0, $submittedStudentIds->count() - (int) ($evaluatedByStatus->evaluated_count ?? 0));

        $rows = DB::table('academic_documents as d')
            ->join('students as s', 's.id', '=', 'd.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->leftJoin('academic_eligibility_evaluations as e', function ($join) {
                $join->on('e.student_id', '=', 'd.student_id')
                    ->on('e.academic_period_id', '=', 'd.academic_period_id');
            })
            ->where('d.academic_period_id', $periodId)
            ->when($studentIds->isNotEmpty(), fn ($q) => $q->whereIn('d.student_id', $studentIds))
            ->where(function ($q) {
                $q->whereNull('e.id')
                    ->orWhere(function ($sq) {
                        $sq->whereIn('e.final_status', ['pending_review', 'ineligible']);
                    });
            })
            ->select([
                'd.student_id',
                's.student_id_number',
                'su.first_name',
                'su.last_name',
                'd.id as document_id',
                'd.uploaded_at',
                'e.id as evaluation_id',
                'e.gpa',
                'e.final_status',
                'e.evaluated_at',
            ])
            ->orderByDesc('d.uploaded_at')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                return [
                    'student_id' => (int) $row->student_id,
                    'student_name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                    'student_id_number' => $row->student_id_number,
                    'document_id' => (int) $row->document_id,
                    'uploaded_at' => $row->uploaded_at,
                    'evaluation_id' => $row->evaluation_id ? (int) $row->evaluation_id : null,
                    'evaluation_status' => $row->final_status ?: AcademicEligibilityEvaluation::statusForGpa(
                        $row->gpa !== null ? (float) $row->gpa : null
                    ),
                    'gpa' => $row->gpa !== null ? (float) $row->gpa : null,
                    'evaluated_at' => $row->evaluated_at,
                    'exception_type' => $row->evaluation_id ? 'at_risk' : 'pending_evaluation',
                ];
            })
            ->values();

        return response()->json([
            'meta' => ['period_id' => $periodId],
            'kpis' => [
                'missing_submissions' => $missingSubmissions,
                'pending_evaluation' => $pendingEvaluation,
                'pending_review' => (int) ($evaluatedByStatus->pending_review_count ?? 0),
                'ineligible' => (int) ($evaluatedByStatus->ineligible_count ?? 0),
            ],
            'rows' => $rows,
        ]);
    }

    public function updateEvaluation(Request $request, int $studentId, int $periodId)
    {
        $validated = $request->validate([
            'document_id' => 'required|exists:academic_documents,id',
            'gpa' => 'nullable|numeric|min:0|max:100',
            'remarks' => 'nullable|string|max:1000',
            'audit_note' => 'nullable|string|max:1000',
        ]);

        $period = AcademicPeriod::findOrFail($periodId);

        $document = AcademicDocument::query()
            ->where('id', (int) $validated['document_id'])
            ->where('student_id', $studentId)
            ->where('academic_period_id', $periodId)
            ->firstOrFail();

        $remarkParts = [];
        if (!empty($validated['remarks'])) {
            $remarkParts[] = trim((string) $validated['remarks']);
        }
        if (!empty($validated['audit_note'])) {
            $remarkParts[] = '[Admin Note] ' . trim((string) $validated['audit_note']);
        }

        $evaluation = AcademicEligibilityEvaluation::query()->updateOrCreate(
            [
                'student_id' => $studentId,
                'academic_period_id' => $periodId,
            ],
            [
                'document_id' => $document->id,
                'gpa' => $validated['gpa'] !== null ? (float) $validated['gpa'] : null,
                'evaluation_source' => 'manual',
                'final_status' => AcademicEligibilityEvaluation::statusForGpa($validated['gpa'] !== null ? (float) $validated['gpa'] : null),
                'review_required' => AcademicEligibilityEvaluation::interpretGrade(
                    $validated['gpa'] !== null ? (float) $validated['gpa'] : null
                )['review_required'],
                'remarks' => !empty($remarkParts) ? implode("\n\n", $remarkParts) : null,
                'evaluated_by' => Auth::id(),
                'evaluated_at' => now(),
            ]
        );

        $document->update([
            'review_status' => AcademicDocument::REVIEW_STATUS_REVIEWED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->teamPlayerStatuses->syncStudent($studentId);

        return response()->json([
            'message' => 'Evaluation updated.',
            'evaluation_id' => $evaluation->id,
        ]);
    }

    public function destroyDocument(AcademicDocument $document)
    {
        $studentId = (int) $document->student_id;
        $periodId = (int) ($document->academic_period_id ?? 0);

        $document->delete();

        if ($periodId) {
            $hasOtherDocs = AcademicDocument::query()
                ->where('student_id', $studentId)
                ->where('academic_period_id', $periodId)
                ->exists();

            if (!$hasOtherDocs) {
                AcademicEligibilityEvaluation::query()
                    ->where('student_id', $studentId)
                    ->where('academic_period_id', $periodId)
                    ->delete();
            }
        }

        $this->teamPlayerStatuses->syncStudent($studentId);

        return back()->with('success', 'Academic submission deleted.');
    }

    public function printSummary(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'nullable|integer|exists:academic_periods,id',
        ]);

        $periodId = $this->resolvePeriodId($validated['period_id'] ?? null);
        abort_unless($periodId, 404);

        $period = AcademicPeriod::findOrFail($periodId);

        $docs = AcademicDocument::query()
            ->periodSubmission()
            ->with(['student.user'])
            ->where('academic_period_id', $periodId)
            ->latest('uploaded_at')
            ->get();

        $evalByStudent = AcademicEligibilityEvaluation::query()
            ->where('academic_period_id', $periodId)
            ->get()
            ->keyBy('student_id');

        $rows = $docs->map(function ($doc) use ($evalByStudent) {
            $student = $doc->student;
            $evaluation = $evalByStudent->get($doc->student_id);

            return [
                'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_id_number' => $student->student_id_number ?? null,
                'document_type' => $doc->document_type,
                'uploaded_at' => optional($doc->uploaded_at)->format('M j, Y g:i A'),
                'status' => $evaluation?->status ?? 'pending',
                'gpa' => $evaluation?->gpa,
                'evaluated_at' => $evaluation?->evaluated_at
                    ? $evaluation->evaluated_at->format('M j, Y g:i A')
                    : null,
            ];
        })->values();

        $counts = [
            'eligible' => $rows->where('status', 'eligible')->count(),
            'pending_review' => $rows->where('status', 'pending_review')->count(),
            'ineligible' => $rows->where('status', 'ineligible')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
            'total' => $rows->count(),
        ];

        $periodLabel = "{$period->school_year} {$this->termLabel((string) $period->term)}";

        return view('print.academics-summary', [
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('M j, Y g:i A'),
            'counts' => $counts,
            'rows' => $rows,
        ]);
    }

    private function validatedRecordsFilters(Request $request): array
    {
        $validated = $request->validate([
            'period_id' => 'nullable|integer|exists:academic_periods,id',
            'team_id' => 'nullable|integer|exists:teams,id',
            'coach_id' => 'nullable|integer|exists:coaches,id',
            'status' => 'nullable|in:eligible,pending_review,ineligible,pending',
            'search' => 'nullable|string|max:150',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        return [
            'period_id' => isset($validated['period_id']) ? (int) $validated['period_id'] : null,
            'team_id' => isset($validated['team_id']) ? (int) $validated['team_id'] : null,
            'coach_id' => isset($validated['coach_id']) ? (int) $validated['coach_id'] : null,
            'status' => $validated['status'] ?? null,
            'search' => $validated['search'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 15),
        ];
    }

    private function resolveStatus(AcademicPeriod $period): string
    {
        $today = now()->startOfDay();
        $startsOn = $period->starts_on?->copy()->startOfDay();
        $endsOn = $period->ends_on?->copy()->startOfDay();

        if ($startsOn && $today->lt($startsOn)) {
            return 'draft';
        }

        if ($endsOn && $today->gt($endsOn)) {
            return 'closed';
        }

        return 'open';
    }

    private function resolvePeriodId(?int $periodId): ?int
    {
        if ($periodId) {
            return $periodId;
        }

        return AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->value('id');
    }

    private function ocrPayload($ocrRun): ?array
    {
        if (!$ocrRun) {
            return null;
        }

        $summary = $ocrRun->parsedSummary;
        $interpretation = AcademicEligibilityEvaluation::interpretGrade(
            $summary && $summary->gwa !== null ? (float) $summary->gwa : null
        );

        return [
            'id' => $ocrRun->id,
            'engine' => $ocrRun->ocr_engine,
            'engine_version' => $ocrRun->ocr_engine_version,
            'run_status' => $ocrRun->run_status,
            'mean_confidence' => $ocrRun->mean_confidence !== null ? (float) $ocrRun->mean_confidence : null,
            'processed_at' => optional($ocrRun->processed_at)->toDateTimeString(),
            'error_message' => $ocrRun->error_message,
            'raw_text_preview' => $ocrRun->raw_text ? mb_substr((string) $ocrRun->raw_text, 0, 400) : null,
            'validation' => [
                'status' => $ocrRun->validation_status,
                'summary' => $ocrRun->validation_summary,
                'flags' => collect($ocrRun->validation_flags)->values()->all(),
                'checked_at' => optional($ocrRun->validation_checked_at)->toDateTimeString(),
            ],
            'parsed_summary' => $summary ? [
                'gwa' => $summary->gwa !== null ? (float) $summary->gwa : null,
                'total_units' => $summary->total_units !== null ? (float) $summary->total_units : null,
                'parser_status' => $summary->parser_status,
                'parser_confidence' => $summary->parser_confidence !== null ? (float) $summary->parser_confidence : null,
            ] : null,
            'interpretation' => [
                'scale' => $interpretation['scale'],
                'value_label' => $interpretation['value_label'],
                'status' => $interpretation['status'],
                'label' => $interpretation['interpretation_label'],
            ],
        ];
    }

    private function applyRecordsFilters($query, array $filters, string $documentAlias, string $studentAlias, string $evaluationAlias, string $studentUserAlias): void
    {
        $periodId = $this->resolvePeriodId($filters['period_id']);
        if ($periodId) {
            $query->where("{$documentAlias}.academic_period_id", $periodId);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $query->where(function ($q) use ($evaluationAlias) {
                    $q->whereNull("{$evaluationAlias}.id")
                        ->orWhereNull("{$evaluationAlias}.gpa");
                });
            } elseif ($filters['status'] === 'eligible') {
                $query->where("{$evaluationAlias}.final_status", 'eligible');
            } elseif ($filters['status'] === 'pending_review') {
                $query->where("{$evaluationAlias}.final_status", 'pending_review');
            } elseif ($filters['status'] === 'ineligible') {
                $query->where("{$evaluationAlias}.final_status", 'ineligible');
            }
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search, $studentAlias, $documentAlias, $studentUserAlias) {
                $q->where("{$studentUserAlias}.first_name", 'like', "%{$search}%")
                    ->orWhere("{$studentUserAlias}.last_name", 'like', "%{$search}%")
                    ->orWhere("{$studentAlias}.student_id_number", 'like', "%{$search}%")
                    ->orWhereExists(function ($typeQuery) use ($search, $documentAlias) {
                        $typeQuery->selectRaw('1')
                            ->from('academic_document_types as adt')
                            ->whereColumn('adt.id', "{$documentAlias}.document_type_id")
                            ->where('adt.code', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate("{$documentAlias}.uploaded_at", '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate("{$documentAlias}.uploaded_at", '<=', $filters['end_date']);
        }

        if (!empty($filters['team_id'])) {
            $teamId = (int) $filters['team_id'];
            $query->whereExists(function ($sq) use ($studentAlias, $teamId) {
                $sq->selectRaw('1')
                    ->from('team_players as tp')
                    ->whereColumn('tp.student_id', "{$studentAlias}.id")
                    ->where('tp.team_id', $teamId);
            });
        }

        if (!empty($filters['coach_id'])) {
            $coachId = (int) $filters['coach_id'];
            $query->whereExists(function ($sq) use ($studentAlias, $coachId) {
                $sq->selectRaw('1')
                    ->from('team_players as tp')
                    ->join('teams as t', 't.id', '=', 'tp.team_id')
                    ->join('team_staff_assignments as tsa', 'tsa.team_id', '=', 't.id')
                    ->whereColumn('tp.student_id', "{$studentAlias}.id")
                    ->whereNull('tsa.ends_at')
                    ->where('tsa.coach_id', $coachId);
            });
        }
    }

    private function submissionsExportRows(array $filters): array
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
            ->select([
                'd.id as document_id',
                's.student_id_number',
                'su.first_name',
                'su.last_name',
                'p.school_year',
                'p.term',
                'dt.code as document_type',
                'd.uploaded_at',
                'e.gpa',
            ]);

        $this->applyRecordsFilters($query, $filters, 'd', 's', 'e', 'su');

        return $query->orderByDesc('d.uploaded_at')->get()->map(function ($row) {
            return [
                $row->document_id,
                $row->student_id_number,
                trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                trim(($row->school_year ?? '') . ' ' . ($row->term ?? '')),
                $row->document_type,
                $row->uploaded_at,
                AcademicEligibilityEvaluation::statusForGpa(
                    $row->gpa !== null ? (float) $row->gpa : null
                ),
                $row->gpa,
            ];
        })->all();
    }

    private function evaluationsExportRows(array $filters): array
    {
        $query = DB::table('academic_eligibility_evaluations as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->leftJoin('academic_periods as p', 'p.id', '=', 'e.academic_period_id')
            ->leftJoin('academic_documents as d', 'd.id', '=', 'e.document_id')
            ->leftJoin('academic_document_types as dt', 'dt.id', '=', 'd.document_type_id')
            ->select([
                'e.id as evaluation_id',
                's.student_id_number',
                'su.first_name',
                'su.last_name',
                'p.school_year',
                'p.term',
                'dt.code as document_type',
                'e.gpa',
                'e.remarks',
                'e.evaluated_at',
            ]);

        $this->applyRecordsFilters($query, $filters, 'd', 's', 'e', 'su');

        return $query->orderByDesc('e.evaluated_at')->get()->map(function ($row) {
            return [
                $row->evaluation_id,
                $row->student_id_number,
                trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                trim(($row->school_year ?? '') . ' ' . ($row->term ?? '')),
                $row->document_type,
                $row->gpa,
                AcademicEligibilityEvaluation::statusForGpa(
                    $row->gpa !== null ? (float) $row->gpa : null
                ),
                $row->remarks,
                $row->evaluated_at,
            ];
        })->all();
    }

    private function termLabel(string $term): string
    {
        return match ($term) {
            '1st_sem' => '1st Sem',
            '2nd_sem' => '2nd Sem',
            'summer' => 'Summer',
            default => $term,
        };
    }
}
