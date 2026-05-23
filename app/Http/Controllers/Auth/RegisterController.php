<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AccountPendingApprovalMail;
use App\Models\Announcement;
use App\Models\Coach;
use App\Models\DocumentType;
use App\Models\Sport;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use App\Services\SystemNotificationService;
use App\Services\SecureUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class RegisterController extends Controller
{
    private const EMERGENCY_RELATIONSHIPS = [
        'Parent',
        'Guardian',
        'Sibling',
        'Grandparent',
        'Relative',
        'Spouse',
        'Other',
    ];

    public function __construct(
        private SecureUploadService $secureUpload,
        private SystemNotificationService $notifications,
    ) {
    }

    public function showStudentAthleteRegistration()
    {
        return Inertia::render('Auth/Student-AthleteRegister', [
            'sports' => Sport::supported()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Sport $sport) => [
                    'id' => (int) $sport->id,
                    'name' => $sport->name,
                ])
                ->values(),
        ]);
    }

    public function registerStudentAthlete(Request $request)
    {
        // --- Validation ---
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'student_id_number' => 'required|unique:students,student_id_number',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:Male,Female,Other',
            'phone_number' => ['required', 'regex:/^\d{10}$/'],
            'home_address' => 'required|string|max:255',
            'current_grade_level' => 'required|in:11,12,1,2,3,4',
            'course_or_strand' => 'required|string|max:255',
            'applied_sport_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('sports', 'id')->where(fn ($query) => $query->whereIn('name', Sport::supportedNames()))],
            'height' => 'required|numeric|min:50|max:260',
            'weight' => 'required|numeric|min:20|max:300',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|in:' . implode(',', self::EMERGENCY_RELATIONSHIPS),
            'emergency_contact_phone' => 'nullable|string|max:30',
            'avatar' => 'nullable|image|max:2048',
            'academic_document_type' => 'required|in:tor,supporting_document',
            'academic_document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'academic_document_notes' => 'nullable|string',
            'medical_document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'medical_document_notes' => 'nullable|string',
        ], [
            'phone_number.regex' => 'Mobile number must be exactly 10 digits.',
            'emergency_contact_relationship.in' => 'Select a valid emergency contact relationship.',
        ]);

        try {
            $user = DB::transaction(function () use ($request) {
                // --- Create User ---
                $user = $this->createUser($request);

                // --- Create Student ---
                $student = $this->createStudent($request, $user);

                // --- Create initial registration documents ---
                $this->createInitialRegistrationDocuments($request, $student, $user);

                return $user;
            });

            $this->sendPendingApprovalMail($user);
            $this->notifyCoachesOfPendingAccount($user);

            // --- Redirect on success ---
            return inertia('Status/PendingApproval', [
                'successMessage' => 'Registration submitted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Student-athlete registration failed.', [
                'email' => $request->input('email'),
                'student_id_number' => $request->input('student_id_number'),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'The registration could not be completed. Please review the highlighted fields and try again.',
            ])
                ->withInput();
        }
    }

    private function createInitialRegistrationDocuments(Request $request, Student $student, User $user): void
    {
        $academicPath = $this->secureUpload->storePublic(
            $request->file('academic_document_file'),
            'student_documents',
            'academic_document'
        );

        StudentDocument::create([
            'student_id' => $student->id,
            'document_type_id' => DocumentType::resolveId(
                DocumentType::CONTEXT_REGISTRATION,
                (string) $request->academic_document_type
            ),
            'academic_period_id' => null,
            'file_path' => $academicPath,
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'notes' => $request->academic_document_notes,
        ]);

        $medicalPath = $this->secureUpload->storePublic(
            $request->file('medical_document_file'),
            'student_documents',
            'academic_document'
        );

        StudentDocument::create([
            'student_id' => $student->id,
            'document_type_id' => DocumentType::resolveId(
                DocumentType::CONTEXT_REGISTRATION,
                DocumentType::CODE_MEDICAL_DOCUMENT
            ),
            'academic_period_id' => null,
            'file_path' => $medicalPath,
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'notes' => $request->medical_document_notes,
        ]);
    }
    // --- Private Helper Methods ---
    private function createUser(Request $request): User
    {
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->secureUpload->storePublic(
                $request->file('avatar'),
                'avatars',
                'avatar'
            );
        }

        return User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'student-athlete',
            'account_state' => 'active',
            'avatar' => $avatarPath,
        ]);
    }

    private function createStudent(Request $request, User $user): Student
    {
        return Student::create([
            'user_id' => $user->id,
            'student_id_number' => $request->student_id_number,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'home_address' => $request->home_address,
            'course_or_strand' => $request->course_or_strand,
            'current_grade_level' => $request->current_grade_level,
            'approval_status' => 'pending',
            'applied_sport_id' => $request->applied_sport_id,
            'student_status' => 'Enrolled',
            'phone_number' => $request->phone_number,
            'height' => $request->height,
            'weight' => $request->weight,
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_relationship' => $request->emergency_contact_relationship,
            'emergency_contact_phone' => $request->emergency_contact_phone,
        ]);
    }

    public function checkStudentIdAvailability(Request $request)
    {
        $validated = $request->validate([
            'student_id_number' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,20}$/'],
        ]);

        $exists = Student::query()
            ->where('student_id_number', $validated['student_id_number'])
            ->exists();

        return response()->json([
            'available' => !$exists,
        ]);
    }

    public function registerCoach(Request $request)
    {
        // --- Validation ---
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string',
            'home_address' => 'nullable|string',
        ]);

        try {
            $user = DB::transaction(function () use ($request) {
                $avatarPath = null;
                if ($request->hasFile('avatar')) {
                $avatarPath = $this->secureUpload->storePublic(
                    $request->file('avatar'),
                    'avatars',
                    'avatar'
                );
                }

                // --- Create User ---
                $user = User::create([
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'coach',
                    'account_state' => 'active',
                    'avatar' => $avatarPath,
                ]);

                // --- Create Coach ---
                $coach = Coach::create([
                    'user_id' => $user->id, // link to the user
                    'phone_number' => $request->phone_number,
                    'date_of_birth' => $request->date_of_birth,
                    'gender' => $request->gender,
                    'home_address' => $request->home_address,
                    'coach_status' => 'Active',
                ]);

                return $user;
            });

            return redirect('/Login')->with('success', 'The coach account has been registered successfully. You may now sign in.');
        } catch (\Exception $e) {
            Log::error('Coach registration failed.', [
                'email' => $request->input('email'),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'The registration could not be completed. Please review the form and try again.',
            ])->withInput();
        }
    }

    private function sendPendingApprovalMail(User $user): void
    {
        $this->notifications->sendUserEmail($user, new AccountPendingApprovalMail($user), [
            'notification_preference' => 'notify_approvals',
            'context' => [
                'communication' => 'pending_approval',
                'user_id' => $user->id,
            ],
        ]);
    }

    private function notifyCoachesOfPendingAccount(User $user): void
    {
        if (!$user->requiresStudentApproval()) {
            return;
        }

        $appliedSportId = $user->student?->applied_sport_id;
        if (!$appliedSportId) {
            return;
        }

        $coachUserIds = User::query()
            ->where('account_state', 'active')
            ->where('role', 'coach')
            ->whereHas('coach', fn ($query) => $query
                ->where('coach_status', 'Active')
                ->where('sport_id', $appliedSportId))
            ->pluck('id')
            ->all();

        if (empty($coachUserIds)) {
            return;
        }

        $roleLabel = ucwords(str_replace('-', ' ', (string) $user->role));
        $sportLabel = Sport::query()->where('id', $appliedSportId)->value('name') ?? 'Unknown Sport';

        $this->notifications->announceMany(
            $coachUserIds,
            'New Student Application',
            "{$user->name} registered as {$roleLabel} for {$sportLabel} and is waiting for coach review.\nEmail: {$user->email}",
            Announcement::TYPE_APPROVAL,
            $user->id,
            'notify_approvals'
        );
    }
}
