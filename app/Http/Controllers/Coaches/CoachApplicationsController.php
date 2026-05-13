<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Mail\AccountApprovedMail;
use App\Mail\AccountRejectedMail;
use App\Models\Announcement;
use App\Models\DocumentType;
use App\Models\Student;
use App\Models\StudentApprovalHistory;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CoachApplicationsController extends Controller
{
    public function __construct(private SystemNotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $coach = $request->user()?->coach;
        $sportId = (int) ($coach?->sport_id ?? 0);
        $status = (string) $request->query('status', 'pending');
        $search = trim((string) $request->query('search', ''));

        if (!$coach || $sportId <= 0) {
            return Inertia::render('Coaches/CoachApplications', [
                'applications' => [],
                'filters' => [
                    'status' => $status,
                    'search' => $search,
                ],
                'sport' => null,
                'stats' => [
                    'pending' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                ],
            ]);
        }

        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $query = User::query()
            ->whereIn('role', ['student-athlete', 'student'])
            ->whereHas('student', fn ($studentQuery) => $studentQuery
                ->where('applied_sport_id', $sportId)
                ->where('approval_status', $status));

        if ($search !== '') {
            $query->where(function ($userQuery) use ($search) {
                $userQuery->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($studentQuery) => $studentQuery
                        ->where('student_id_number', 'like', "%{$search}%")
                        ->orWhere('course_or_strand', 'like', "%{$search}%"));
            });
        }

        $applications = $query
            ->with([
                'student:id,user_id,student_id_number,course_or_strand,current_grade_level,approval_status,applied_sport_id,phone_number,date_of_birth,gender,height,weight,emergency_contact_name,emergency_contact_relationship,emergency_contact_phone',
                'student.appliedSport:id,name',
                'student.registrationDocuments' => fn ($documentQuery) => $documentQuery
                    ->select('id', 'student_id', 'document_type_id', 'uploaded_at')
                    ->with('documentTypeDefinition:id,code'),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (User $user) {
                $student = $user->student;
                $documents = $student?->registrationDocuments ?? collect();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => optional($user->created_at)->toDateTimeString(),
                    'student' => [
                        'id' => $student?->id,
                        'student_id_number' => $student?->student_id_number,
                        'course_or_strand' => $student?->course_or_strand,
                        'current_grade_level' => $student?->current_grade_level,
                        'academic_level_label' => $student?->academic_level_label,
                        'approval_status' => $student?->approval_status,
                        'applied_sport' => $student?->appliedSport?->name,
                        'phone_number' => $student?->phone_number,
                        'date_of_birth' => $student?->date_of_birth ? (string) $student->date_of_birth : null,
                        'gender' => $student?->gender,
                        'height' => $student?->height,
                        'weight' => $student?->weight,
                        'emergency_contact_name' => $student?->emergency_contact_name,
                        'emergency_contact_relationship' => $student?->emergency_contact_relationship,
                        'emergency_contact_phone' => $student?->emergency_contact_phone,
                        'registration_documents' => $documents
                            ->map(fn ($document) => [
                                'id' => $document->id,
                                'document_type' => $document->document_type,
                                'uploaded_at' => optional($document->uploaded_at)->toDateTimeString(),
                            ])
                            ->values()
                            ->all(),
                    ],
                    'requirements_complete' => $this->hasRequirements($student),
                ];
            })
            ->values();

        return Inertia::render('Coaches/CoachApplications', [
            'applications' => $applications,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'sport' => [
                'id' => $coach->sport?->id,
                'name' => $coach->sport?->name,
            ],
            'stats' => [
                'pending' => $this->applicationCountForSport($sportId, 'pending'),
                'approved' => $this->applicationCountForSport($sportId, 'approved'),
                'rejected' => $this->applicationCountForSport($sportId, 'rejected'),
            ],
        ]);
    }

    public function approve(Request $request, User $user)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $coach->sport_id, 403);

        $student = $user->student;
        abort_unless($student && (int) $student->applied_sport_id === (int) $coach->sport_id, 403);

        if (!$this->hasRequirements($student)) {
            return back()->withErrors([
                'approval' => 'Cannot approve this student-athlete without both the academic registration file and medical clearance.',
            ]);
        }

        DB::transaction(function () use ($student, $coach) {
            $student->update([
                'approval_status' => 'approved',
            ]);

            StudentApprovalHistory::create($this->studentApprovalHistoryAttributes(
                $student,
                'approved',
                null,
                $coach->id
            ));
        });

        $this->notifications->announce(
            $user->id,
            'Application Approved',
            'Your student-athlete application was approved by the coach for your selected sport.',
            Announcement::TYPE_APPROVAL,
            Auth::id(),
            'notify_approvals',
            false
        );

        $this->notifications->sendUserEmail($user->fresh(['settings']), new AccountApprovedMail($user), [
            'notification_preference' => 'notify_approvals',
            'context' => [
                'communication' => 'coach_application_approved',
                'user_id' => $user->id,
            ],
        ]);

        return back()->with('success', 'Student application approved.');
    }

    public function reject(Request $request, User $user)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $coach->sport_id, 403);

        $student = $user->student;
        abort_unless($student && (int) $student->applied_sport_id === (int) $coach->sport_id, 403);

        $validated = $request->validate([
            'remarks' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($student, $validated, $coach) {
            $student->update([
                'approval_status' => 'rejected',
            ]);

            StudentApprovalHistory::create($this->studentApprovalHistoryAttributes(
                $student,
                'rejected',
                $validated['remarks'] ?? null,
                $coach->id
            ));
        });

        $this->notifications->announce(
            $user->id,
            'Application Rejected',
            'Your student-athlete application was rejected by the coach.' . (!empty($validated['remarks']) ? " Remarks: {$validated['remarks']}" : ''),
            Announcement::TYPE_APPROVAL,
            Auth::id(),
            'notify_approvals',
            false
        );

        $this->notifications->sendUserEmail($user->fresh(['settings']), new AccountRejectedMail($user, $validated['remarks'] ?? null), [
            'notification_preference' => 'notify_approvals',
            'context' => [
                'communication' => 'coach_application_rejected',
                'user_id' => $user->id,
            ],
        ]);

        return back()->with('success', 'Student application rejected.');
    }

    private function hasRequirements(?Student $student): bool
    {
        if (!$student) {
            return false;
        }

        $documents = $student->registrationDocuments()->get();
        $hasTor = $documents->contains(fn ($document) => $document->document_type === DocumentType::CODE_TOR);
        $hasMedical = $documents->contains(fn ($document) => $document->document_type === DocumentType::CODE_MEDICAL_DOCUMENT);

        return $hasTor && $hasMedical;
    }

    private function studentApprovalHistoryAttributes(Student $student, string $decision, ?string $remarks = null, ?int $coachId = null): array
    {
        $attributes = [
            'student_id' => $student->id,
            'decision' => $decision,
            'remarks' => $remarks,
        ];

        if (Schema::hasColumn('student_approval_histories', 'admin_id')) {
            $attributes['admin_id'] = Auth::id();
        }

        if (Schema::hasColumn('student_approval_histories', 'coach_id')) {
            $attributes['coach_id'] = $coachId;
        }

        return $attributes;
    }

    private function applicationCountForSport(int $sportId, string $status): int
    {
        return Student::query()
            ->where('applied_sport_id', $sportId)
            ->where('approval_status', $status)
            ->count();
    }
}
