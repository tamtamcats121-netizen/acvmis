<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAcademicDocumentOcr;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\DocumentType;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\Team;
use App\Models\User;
use App\Services\AcademicEligibilityAccessService;
use App\Services\SecureUploadService;
use App\Services\SystemNotificationService;
use App\Services\TeamPlayerStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

class StudentDocumentController extends Controller
{
    public function __construct(
        private SystemNotificationService $notifications,
        private SecureUploadService $secureUpload,
        private AcademicEligibilityAccessService $academicAccess,
        private TeamPlayerStatusService $teamPlayerStatuses,
    ) {
    }

    public function index(Request $request)
    {
        $student = Student::query()->where('user_id', Auth::id())->first();
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => trim((string) $request->query('type', 'all')),
        ];

        if (!$student) {
            return Inertia::render('StudentAthletes/MyDocuments', [
                'student' => null,
                'openPeriods' => [],
                'documentTypes' => [],
                'filters' => $filters,
                'documents' => [
                    'data' => [],
                    'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 9, 'total' => 0],
                ],
            ]);
        }

        $documentsQuery = StudentDocument::query()
            ->with([
                'documentTypeDefinition:id,context,code,label',
                'academicPeriod:id,school_year,term',
                'latestOcrRun.parsedSummary',
            ])
            ->where('student_id', $student->id)
            ->latest('uploaded_at');

        if ($filters['type'] !== '' && $filters['type'] !== 'all') {
            $documentsQuery->whereHas('documentTypeDefinition', fn ($typeQuery) => $typeQuery->where('code', $filters['type']));
        }

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $documentsQuery->where(function ($query) use ($term) {
                $query->where('notes', 'like', "%{$term}%")
                    ->orWhereHas('academicPeriod', fn ($periodQuery) => $periodQuery
                        ->where('school_year', 'like', "%{$term}%")
                        ->orWhere('term', 'like', "%{$term}%"))
                    ->orWhereHas('documentTypeDefinition', fn ($typeQuery) => $typeQuery
                        ->where('label', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%"));
            });
        }

        $documents = $documentsQuery->paginate(9)->withQueryString();

        $evaluations = AcademicEligibilityEvaluation::query()
            ->select(['student_id', 'academic_period_id', 'gpa', 'final_status', 'remarks', 'evaluated_at'])
            ->where('student_id', $student->id)
            ->whereNotNull('academic_period_id')
            ->get()
            ->keyBy('academic_period_id');

        return Inertia::render('StudentAthletes/MyDocuments', [
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'course_or_strand' => $student->course_or_strand,
                'current_grade_level' => $student->current_grade_level,
                'academic_level_label' => $student->academic_level_label,
                'education_level' => $student->education_level,
            ],
            'academicAccess' => $this->academicAccess->evaluate($student),
            'filters' => $filters,
            'openPeriods' => AcademicPeriod::query()
                ->open()
                ->orderByDesc('starts_on')
                ->get(['id', 'school_year', 'term', 'starts_on', 'ends_on'])
                ->map(fn (AcademicPeriod $period) => [
                    'id' => $period->id,
                    'label' => "{$period->school_year} - {$period->term}",
                    'starts_on' => optional($period->starts_on)->toDateString(),
                    'ends_on' => optional($period->ends_on)->toDateString(),
                ]),
            'documentTypes' => [
                ['code' => DocumentType::CODE_GRADE_REPORT, 'label' => 'Grade Report'],
                ['code' => DocumentType::CODE_TOR, 'label' => 'Transcript of Records (TOR)'],
                ['code' => DocumentType::CODE_MEDICAL_DOCUMENT, 'label' => 'Medical Document / Health Clearance'],
                ['code' => DocumentType::CODE_SUPPORTING_DOCUMENT, 'label' => 'Supporting Document'],
            ],
            'documents' => [
                'data' => $documents->through(function (StudentDocument $document) use ($student, $evaluations) {
                    $evaluation = $document->academic_period_id ? $evaluations->get($document->academic_period_id) : null;
                    $presentedEvaluation = $evaluation
                        ? AcademicEligibilityEvaluation::presentStoredEvaluation(
                            $evaluation->gpa !== null ? (float) $evaluation->gpa : null,
                            $evaluation->final_status,
                            $student->education_level,
                            $evaluation->remarks,
                        )
                        : null;

                    return [
                        'id' => $document->id,
                        'document_type' => $document->document_type,
                        'document_label' => $document->documentTypeDefinition?->label ?? 'Document',
                        'document_context' => $document->document_context,
                        'period_label' => $document->academicPeriod
                            ? "{$document->academicPeriod->school_year} - {$document->academicPeriod->term}"
                            : null,
                        'uploaded_at' => optional($document->uploaded_at)->toDateTimeString(),
                        'notes' => $document->notes,
                        'review_status' => $document->review_status,
                        'file_url' => route('files.documents.show', $document->id),
                        'download_url' => route('files.documents.show', ['document' => $document->id, 'download' => 1]),
                        'ocr' => $document->document_type === DocumentType::CODE_GRADE_REPORT ? [
                            'run_status' => $document->latestOcrRun?->run_status,
                            'mean_confidence' => $document->latestOcrRun?->mean_confidence,
                            'validation_status' => $document->latestOcrRun?->validation_status,
                            'validation_summary' => $document->latestOcrRun?->validation_summary,
                            'parsed_value' => $document->latestOcrRun?->parsedSummary?->gwa,
                            'value_label' => AcademicEligibilityEvaluation::valueLabelForScale(
                                AcademicEligibilityEvaluation::expectedScaleForEducationLevel($student->education_level)
                            ),
                        ] : null,
                        'evaluation' => $evaluation ? [
                            'status' => $presentedEvaluation['status'],
                            'remarks' => $presentedEvaluation['remarks'],
                            'review_required' => $presentedEvaluation['review_required'],
                            'scale_mismatch' => $presentedEvaluation['scale_mismatch'],
                            'gpa' => $evaluation->gpa,
                            'evaluated_at' => optional($evaluation->evaluated_at)->toDateTimeString(),
                        ] : null,
                    ];
                })->items(),
                'meta' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $student = Student::query()->where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'document_type' => 'required|in:grade_report,tor,medical_document,supporting_document',
            'academic_period_id' => 'nullable|exists:academic_periods,id',
            'notes' => 'nullable|string|max:1000',
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $documentType = (string) $validated['document_type'];
        $periodId = $documentType === DocumentType::CODE_GRADE_REPORT
            ? (int) ($validated['academic_period_id'] ?? 0)
            : null;

        if ($documentType === DocumentType::CODE_GRADE_REPORT && !$periodId) {
            return back()->withErrors(['academic_period_id' => 'Academic period is required for grade reports.']);
        }

        $period = null;
        if ($periodId) {
            $period = AcademicPeriod::query()->findOrFail($periodId);
            abort_unless($period->status === 'open', 422, 'Submission window is closed for this period.');

            $alreadyEligible = AcademicEligibilityEvaluation::query()
                ->where('student_id', $student->id)
                ->where('academic_period_id', $periodId)
                ->where('final_status', 'eligible')
                ->exists();

            abort_if($alreadyEligible, 422, 'You are already eligible for this period. Further grade report submissions are locked.');
        }

        $filePath = $this->secureUpload->storePublic(
            $request->file('document_file'),
            'student_documents',
            'academic_document'
        );

        $context = $documentType === DocumentType::CODE_GRADE_REPORT
            ? DocumentType::CONTEXT_PERIOD_SUBMISSION
            : DocumentType::CONTEXT_REGISTRATION;

        $document = StudentDocument::create([
            'student_id' => $student->id,
            'document_type_id' => DocumentType::resolveId($context, $documentType),
            'academic_period_id' => $periodId ?: null,
            'file_path' => $filePath,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'review_status' => StudentDocument::REVIEW_STATUS_PENDING,
        ]);

        if ($documentType === DocumentType::CODE_GRADE_REPORT) {
            try {
                ProcessAcademicDocumentOcr::dispatchSync($document->id);
            } catch (Throwable $exception) {
                Log::warning('Student document OCR failed.', [
                    'document_id' => $document->id,
                    'student_id' => $student->id,
                    'message' => $exception->getMessage(),
                ]);

                $document->update([
                    'review_status' => StudentDocument::REVIEW_STATUS_NEEDS_REVIEW,
                ]);
            }

            $this->notifyAcademicUpload($student, $period, $request->user()->id);
            $this->teamPlayerStatuses->syncStudent($student->id);
        }

        return back()->with('success', 'Document uploaded successfully.');
    }

    private function notifyAcademicUpload(Student $student, ?AcademicPeriod $period, int $actorId): void
    {
        $periodLabel = $period ? "{$period->school_year} - {$period->term}" : 'selected period';
        $studentName = $student->full_name;

        $adminUserIds = User::query()
            ->where('account_state', 'active')
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        $coachUserIds = Team::query()
            ->whereHas('players', fn ($query) => $query->where('student_id', $student->id))
            ->with([
                'coach' => fn ($query) => $query->select('coaches.id', 'coaches.user_id'),
                'assistantCoach' => fn ($query) => $query->select('coaches.id', 'coaches.user_id'),
            ])
            ->get()
            ->flatMap(fn ($team) => [$team->coach?->user_id, $team->assistantCoach?->user_id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->notifications->announceMany(
            array_merge($adminUserIds, $coachUserIds),
            'Academic Submission Received',
            "New grade report submission from {$studentName} for {$periodLabel}.",
            'academic',
            $actorId,
            'notify_academic_alerts'
        );
    }
}
