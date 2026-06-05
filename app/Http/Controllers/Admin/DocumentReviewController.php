<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\DocumentType;
use App\Models\StudentDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DocumentReviewController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => trim((string) $request->query('type', 'all')),
            'review_status' => trim((string) $request->query('review_status', 'all')),
            'period_id' => (int) $request->query('period_id', 0),
            'upload_date' => trim((string) $request->query('upload_date', '')),
        ];

        $query = StudentDocument::query()
            ->with([
                'student.user:id,first_name,last_name,email',
                'academicPeriod:id,school_year,term',
                'documentTypeDefinition:id,context,code,label',
                'reviewer:id,first_name,last_name',
            ])
            ->latest('uploaded_at');

        if ($filters['type'] !== '' && $filters['type'] !== 'all') {
            $query->whereHas('documentTypeDefinition', fn ($typeQuery) => $typeQuery->where('code', $filters['type']));
        }

        if ($filters['review_status'] !== '' && $filters['review_status'] !== 'all') {
            $query->where('review_status', $filters['review_status']);
        }

        if ($filters['period_id'] > 0) {
            $query->where('academic_period_id', $filters['period_id']);
        }

        if ($filters['upload_date'] !== '') {
            $query->whereDate('uploaded_at', $filters['upload_date']);
        }

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $query->where(function ($searchQuery) use ($term) {
                $searchQuery->whereHas('student.user', function ($userQuery) use ($term) {
                    $userQuery->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                })->orWhereHas('student', function ($studentQuery) use ($term) {
                    $studentQuery->where('student_id_number', 'like', "%{$term}%")
                        ->orWhere('course_or_strand', 'like', "%{$term}%");
                })->orWhereHas('documentTypeDefinition', function ($typeQuery) use ($term) {
                    $typeQuery->where('label', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
            });
        }

        $documents = $query->paginate(12)->withQueryString();

        return Inertia::render('Admin/Documents', [
            'filters' => $filters,
            'periods' => AcademicPeriod::query()
                ->orderByDesc('starts_on')
                ->get(['id', 'school_year', 'term'])
                ->map(fn (AcademicPeriod $period) => [
                    'id' => $period->id,
                    'label' => "{$period->school_year} - {$period->term}",
                ]),
            'documentTypes' => DocumentType::query()
                ->orderBy('context')
                ->orderBy('label')
                ->get(['context', 'code', 'label'])
                ->map(fn (DocumentType $type) => [
                    'context' => $type->context,
                    'code' => $type->code,
                    'label' => $type->label,
                ]),
            'documents' => $documents->through(fn (StudentDocument $document) => $this->serializeDocument($document)),
        ]);
    }

    public function markReviewed(StudentDocument $document)
    {
        $document->update([
            'review_status' => StudentDocument::REVIEW_STATUS_REVIEWED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Document marked as reviewed.');
    }

    private function serializeDocument(StudentDocument $document): array
    {
        $student = $document->student;

        return [
            'id' => $document->id,
            'student' => [
                'id' => $student?->id,
                'name' => $student?->full_name ?? 'Unknown student',
                'email' => $student?->user?->email,
                'student_id_number' => $student?->student_id_number,
                'course_or_strand' => $student?->course_or_strand,
            ],
            'document_type' => $document->document_type,
            'document_label' => $document->documentTypeDefinition?->label ?? 'Document',
            'document_context' => $document->document_context,
            'academic_period' => $document->academicPeriod ? [
                'id' => $document->academicPeriod->id,
                'label' => "{$document->academicPeriod->school_year} - {$document->academicPeriod->term}",
            ] : null,
            'file_url' => route('files.documents.show', $document->id),
            'download_url' => route('files.documents.show', ['document' => $document->id, 'download' => 1]),
            'uploaded_at' => optional($document->uploaded_at)->toDateTimeString(),
            'notes' => $document->notes,
            'review_status' => $document->review_status,
            'reviewed_at' => optional($document->reviewed_at)->toDateTimeString(),
            'reviewer_name' => $document->reviewer?->name,
            'file_name' => basename((string) $document->file_path),
        ];
    }
}
