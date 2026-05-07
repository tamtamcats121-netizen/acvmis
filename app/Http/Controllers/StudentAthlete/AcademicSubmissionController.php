<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAcademicDocumentOcr;
use App\Models\AcademicDocument;
use App\Models\AcademicDocumentType;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\Team;
use App\Models\User;
use App\Services\AcademicEligibilityAccessService;
use App\Services\SystemNotificationService;
use App\Services\SecureUploadService;
use App\Services\TeamPlayerStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Throwable;

class AcademicSubmissionController extends Controller
{
    public function __construct(
        private SystemNotificationService $notifications,
        private SecureUploadService $secureUpload,
        private AcademicEligibilityAccessService $academicAccess,
        private TeamPlayerStatusService $teamPlayerStatuses,
    )
    {
    }

    public function index()
    {
        $student = Student::where('user_id', Auth::id())->first();

        if (!$student) {
            return Inertia::render('StudentAthletes/AcademicSubmissions', [
                'student' => null,
                'openPeriods' => [],
                'submissions' => [],
                'selectedPeriodId' => 0,
                'resultSubmissionId' => 0,
            ]);
        }

        request()->merge([
            'period_id' => (int) request()->query('period_id', 0),
            'result_submission_id' => (int) request()->query('result_submission_id', 0),
        ]);

        return Inertia::render('StudentAthletes/AcademicSubmissions', $this->buildPayload($student));
    }

    public function create(Request $request)
    {
        return redirect()->route('AcademicSubmissions', [
            'period_id' => (int) $request->query('period_id', 0),
        ]);
    }

    private function buildPayload(Student $student): array
    {
        $openPeriodsQuery = AcademicPeriod::query()
            ->orderByDesc('starts_on');

        $openPeriodsQuery->open();

        $openPeriods = $openPeriodsQuery->get();
        $academicAccess = $this->academicAccess->evaluate($student);

        $submissionQuery = AcademicDocument::query()
            ->periodSubmission()
            ->where('student_id', $student->id)
            ->latest('uploaded_at')
            ->with('academicPeriod');

        if ($this->academicOcrTablesReady()) {
            $submissionQuery->with([
                'latestOcrRun' => function ($query) {
                    if ($this->academicParsedSummariesReady()) {
                        $query->with('parsedSummary');
                    }
                },
            ]);
        }

        $submissions = $submissionQuery->get();

        $evalByPeriod = AcademicEligibilityEvaluation::query()
            ->where('student_id', $student->id)
            ->whereNotNull('academic_period_id')
            ->get()
            ->keyBy('academic_period_id');

        return [
            'student' => [
                'id' => $student->id,
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_id_number' => $student->student_id_number,
                'course_or_strand' => $student->course_or_strand,
                'current_grade_level' => $student->current_grade_level,
                'academic_level_label' => $student->academic_level_label,
            ],
            'hasActiveWindow' => (bool) ($academicAccess['has_active_period'] ?? false),
            'hasSubmittedAll' => (bool) ($academicAccess['has_submitted_for_active_period'] ?? false),
            'hasEligibleForActivePeriod' => (bool) ($academicAccess['has_eligible_evaluation_for_active_period'] ?? false),
            'selectedPeriodId' => (int) request()->query('period_id', 0),
            'resultSubmissionId' => (int) request()->query('result_submission_id', 0),
            'openPeriods' => $openPeriods->map(function ($p) use ($evalByPeriod) {
                $evaluation = $evalByPeriod->get($p->id);
                $status = $evaluation?->status;
                $isEligible = $status === 'eligible';

                return [
                    'id' => $p->id,
                    'school_year' => $p->school_year,
                    'term' => $p->term,
                    'starts_on' => optional($p->starts_on)->toDateString(),
                    'ends_on' => optional($p->ends_on)->toDateString(),
                    'eligibility_status' => $status,
                    'is_eligible' => $isEligible,
                    'can_submit' => !$isEligible,
                ];
            }),
            'submissions' => $submissions->map(function ($doc) use ($evalByPeriod, $student) {
                $evaluation = $doc->academic_period_id ? $evalByPeriod->get($doc->academic_period_id) : null;
                $presentedEvaluation = $evaluation
                    ? AcademicEligibilityEvaluation::presentStoredEvaluation(
                        $evaluation->gpa !== null ? (float) $evaluation->gpa : null,
                        $evaluation->status,
                        $student->education_level,
                        $evaluation->remarks,
                    )
                    : null;

                return [
                    'id' => $doc->id,
                    'period_id' => $doc->academic_period_id,
                    'period_label' => $doc->academicPeriod
                        ? ($doc->academicPeriod->school_year . ' - ' . $doc->academicPeriod->term)
                        : null,
                    'document_type' => $doc->document_type,
                    'file_url' => $doc->id ? route('files.academic', $doc->id) : null,
                    'uploaded_at' => optional($doc->uploaded_at)->toDateTimeString(),
                    'notes' => $doc->notes,
                    'ocr' => $this->ocrPayload($doc->latestOcrRun, $student->education_level),
                    'evaluation' => $evaluation ? [
                        'gpa' => $evaluation->gpa,
                        'status' => $presentedEvaluation['status'],
                        'remarks' => $presentedEvaluation['remarks'],
                        'review_required' => $presentedEvaluation['review_required'],
                        'scale_mismatch' => $presentedEvaluation['scale_mismatch'],
                        'mismatch_message' => $presentedEvaluation['mismatch_message'],
                        'evaluated_at' => optional($evaluation->evaluated_at)->toDateTimeString(),
                    ] : null,
                ];
            }),
        ];
    }

    public function store(Request $request)
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'academic_period_id' => 'required|exists:academic_periods,id',
            'document_type' => 'required|in:grade_report,supporting_document',
            'notes' => 'nullable|string|max:1000',
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $period = AcademicPeriod::findOrFail((int) $validated['academic_period_id']);
        abort_unless($period->status === 'open', 422, 'Submission window is closed for this period.');

        $eligible = AcademicEligibilityEvaluation::query()
            ->where('student_id', $student->id)
            ->where('academic_period_id', $period->id)
            ->where('final_status', 'eligible')
            ->exists();
        if ($eligible) {
            abort(422, 'You are already eligible for this period. Further submissions are locked.');
        }

        $filePath = $this->secureUpload->storePublic(
            $request->file('document_file'),
            'academic_documents',
            'academic_document'
        );

        $notes = trim((string) ($validated['notes'] ?? ''));

        $document = AcademicDocument::create([
            'student_id' => $student->id,
            'document_type_id' => AcademicDocumentType::resolveId(
                AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION,
                (string) $validated['document_type']
            ),
            'academic_period_id' => (int) $validated['academic_period_id'],
            'file_path' => $filePath,
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now(),
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $toastType = 'success';
        $toastMessage = 'Academic submission processed successfully';

        if ((string) $validated['document_type'] === AcademicDocumentType::CODE_GRADE_REPORT) {
            $canRunInlineOcr = $this->ocrValidationColumnsReady();

            if ($canRunInlineOcr) {
                try {
                    ProcessAcademicDocumentOcr::dispatchSync($document->id);
                } catch (Throwable $e) {
                    Log::warning('Academic document OCR failed during inline student submission.', [
                        'document_id' => $document->id,
                        'student_id' => $student->id,
                        'period_id' => $period->id,
                        'message' => $e->getMessage(),
                    ]);

                    $document->update([
                        'review_status' => AcademicDocument::REVIEW_STATUS_NEEDS_REVIEW,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]);

                    $canRunInlineOcr = false;
                }
            }

            if ($canRunInlineOcr) {
                $document->refresh()->loadMissing('latestOcrRun.parsedSummary');
                $ocrRun = $document->latestOcrRun;
                $evaluation = AcademicEligibilityEvaluation::query()
                    ->where('student_id', $student->id)
                    ->where('academic_period_id', $period->id)
                    ->latest('evaluated_at')
                    ->first();

                $wasFullyProcessed = $ocrRun
                    && $ocrRun->run_status !== 'failed'
                    && in_array($evaluation?->status, ['eligible', 'pending_review', 'ineligible'], true);

                if (!$wasFullyProcessed) {
                    $toastType = 'error';
                    $toastMessage = 'Unable to fully process document. Please review or resubmit.';
                }
            } else {
                $toastType = 'error';
                $toastMessage = 'Unable to fully process document. Please review or resubmit.';
            }
        }

        $periodLabel = "{$period->school_year} - {$period->term}";
        $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''));
        $message = "New academic submission from {$studentName} for {$periodLabel}.";

        $adminUserIds = User::query()
            ->where('account_state', 'active')
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        $coachUserIds = Team::query()
            ->whereHas('players', fn ($q) => $q->where('student_id', $student->id))
            ->with([
                'coach' => fn ($query) => $query->select('coaches.id', 'coaches.user_id'),
                'assistantCoach' => fn ($query) => $query->select('coaches.id', 'coaches.user_id'),
            ])
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

        $this->notifications->announceMany(
            array_merge($adminUserIds, $coachUserIds),
            'Academic Submission Received',
            $message,
            'academic',
            Auth::id(),
            'notify_academic_alerts'
        );

        $this->teamPlayerStatuses->syncStudent($student->id);

        return redirect()->route('AcademicSubmissions', [
            'period_id' => (int) $period->id,
            'result_submission_id' => (int) $document->id,
        ])->with($toastType, $toastMessage);
    }

    public function print(Request $request)
    {
        $student = Student::where('user_id', Auth::id())->first();
        abort_unless($student, 403);

        $openPeriodsQuery = AcademicPeriod::query()
            ->orderByDesc('starts_on');

        $openPeriodsQuery->open();

        $openPeriods = $openPeriodsQuery->get();

        $submissions = AcademicDocument::query()
            ->periodSubmission()
            ->with(['academicPeriod', 'latestOcrRun.parsedSummary'])
            ->where('student_id', $student->id)
            ->latest('uploaded_at')
            ->get();

        $evalByPeriod = AcademicEligibilityEvaluation::query()
            ->where('student_id', $student->id)
            ->whereNotNull('academic_period_id')
            ->get()
            ->keyBy('academic_period_id');

        $periods = $openPeriods->map(function ($p) use ($evalByPeriod) {
            $evaluation = $evalByPeriod->get($p->id);
            $label = $p->school_year . ' ' . $p->term;
            $window = (optional($p->starts_on)->toDateString() ?: '-') . ' to ' . (optional($p->ends_on)->toDateString() ?: '-');

            return [
                'label' => $label,
                'eligibility_status' => $evaluation?->status,
                'window' => $window,
            ];
        })->values();

        $submissionRows = $submissions->map(function ($doc) use ($evalByPeriod, $student) {
            $evaluation = $doc->academic_period_id ? $evalByPeriod->get($doc->academic_period_id) : null;

            return [
                'period_label' => $doc->academicPeriod
                    ? ($doc->academicPeriod->school_year . ' - ' . $doc->academicPeriod->term)
                    : null,
                'document_type' => $doc->document_type,
                'uploaded_at' => optional($doc->uploaded_at)->format('M j, Y g:i A'),
                'status' => $evaluation?->status,
                'gpa' => $evaluation?->gpa,
                'value_label' => AcademicEligibilityEvaluation::valueLabelForScale(
                    AcademicEligibilityEvaluation::expectedScaleForEducationLevel($student->education_level)
                ),
                'extracted_gpa' => $doc->latestOcrRun?->parsedSummary?->gwa !== null
                    ? (float) $doc->latestOcrRun?->parsedSummary?->gwa
                    : null,
                'validation_status' => $doc->latestOcrRun?->validation_status,
                'remarks' => $evaluation?->remarks,
            ];
        })->values();

        return view('print.student-academics', [
            'student' => [
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_id_number' => $student->student_id_number,
                'education_level' => $student->education_level,
            ],
            'periods' => $periods,
            'submissions' => $submissionRows,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    private function ocrPayload($ocrRun, ?string $educationLevel = null): ?array
    {
        if (!$ocrRun || !$this->academicOcrTablesReady()) {
            return null;
        }

        $summary = $this->academicParsedSummariesReady() ? $ocrRun->parsedSummary : null;
        $interpretation = AcademicEligibilityEvaluation::interpretGrade(
            $summary && $summary->gwa !== null ? (float) $summary->gwa : null,
            $educationLevel
        );

        return [
            'id' => $ocrRun->id,
            'run_status' => $ocrRun->run_status,
            'processed_at' => optional($ocrRun->processed_at)->toDateTimeString(),
            'error_message' => $ocrRun->error_message,
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
                'scale_mismatch' => (bool) ($interpretation['scale_mismatch'] ?? false),
                'mismatch_message' => $interpretation['mismatch_message'] ?? null,
            ],
            'validation' => [
                'status' => $this->ocrValidationColumnsReady() ? $ocrRun->validation_status : null,
                'summary' => $this->ocrValidationColumnsReady() ? $ocrRun->validation_summary : null,
                'flags' => $this->ocrValidationColumnsReady()
                    ? collect($ocrRun->validation_flags)->values()->all()
                    : [],
                'checked_at' => $this->ocrValidationColumnsReady()
                    ? optional($ocrRun->validation_checked_at)->toDateTimeString()
                    : null,
            ],
        ];
    }

    private function ocrValidationColumnsReady(): bool
    {
        return Schema::hasColumns('academic_document_ocr_runs', [
            'validation_status',
            'validation_summary',
            'validation_flags',
            'validation_checked_at',
        ]);
    }

    private function academicOcrTablesReady(): bool
    {
        return Schema::hasTable('academic_document_ocr_runs');
    }

    private function academicParsedSummariesReady(): bool
    {
        return Schema::hasTable('academic_document_parsed_summaries');
    }
}
