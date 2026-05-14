<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\AccountApprovedMail;
use App\Mail\AdminInviteMail;
use App\Mail\AccountRejectedMail;
use App\Mail\CoachOnboardingMail;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AccountActionLog;
use App\Models\AdminInvite;
use App\Models\Announcement;
use App\Models\Coach;
use App\Models\DocumentType;
use App\Models\Sport;
use App\Models\StudentApprovalHistory;
use App\Models\Student;
use App\Models\Team;
use App\Models\User;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function __construct(
        private SystemNotificationService $notifications,
    )
    {
    }

    public function dashboard(Request $request)
    {
        $period = (string) $request->query('period', 'week');
        if (!in_array($period, ['today', 'week', 'month'], true)) {
            $period = 'week';
        }

        $cacheKey = "admin_dashboard:{$period}";
            $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($period) {
                [$start, $end] = $this->dashboardRange($period);

                $attendanceSummary = $this->attendanceSummary($start, $end);
                $attendanceTrend = $this->attendanceTrend($start, $end);
                $attendanceByTeam = $this->attendanceByTeam($start, $end);
                $academicByTeam = $this->academicByTeam();
                $pendingItems = $this->pendingItemsSummary($start, $end);
                $recentActivity = $this->dashboardRecentActivity();
                $activeTeamsCount = Team::query()->whereNull('archived_at')->count();

                return [
                    'dashboard' => [
                    'filters' => [
                        'period' => $period,
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                    ],
                    'kpis' => [
                        'attendance_rate' => $attendanceSummary['attendance_rate'],
                        'attendance_present' => $attendanceSummary['present'],
                        'attendance_total' => $attendanceSummary['total'],
                        'no_response' => $attendanceSummary['no_response'],
                        'active_teams' => $activeTeamsCount,
                        'pending_academic_review' => $academicByTeam['totals']['pending_review'],
                    ],
                    'pending_items' => $pendingItems,
                    'academic_status' => $academicByTeam['totals'],
                    'trends' => [
                        'labels' => $attendanceTrend['labels'],
                        'attendance' => $attendanceTrend['series'],
                        'attendance_by_team' => $attendanceByTeam,
                    ],
                    'recent_activity' => $recentActivity,
                    ],
                ];
            });

        return Inertia::render('Admin/AdminDashboard', $payload);
    }

    public function auditTrail()
    {
        $approvalHistory = StudentApprovalHistory::query()
            ->with($this->studentApprovalHistoryRelations(['id', 'first_name', 'middle_name', 'last_name']))
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (StudentApprovalHistory $history) {
                return [
                    'id' => $history->id,
                    'student_name' => $history->student?->user?->name ?? 'Unknown student',
                    'decision' => $history->decision,
                    'remarks' => $history->remarks,
                    'admin_name' => $this->studentApprovalActorName($history),
                    'created_at' => optional($history->created_at)?->toIso8601String(),
                ];
            })
            ->values();

        $accountActions = AccountActionLog::query()
            ->with([
                'user:id,first_name,middle_name,last_name,role',
                'admin:id,first_name,middle_name,last_name',
            ])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (AccountActionLog $log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name ?? 'Unknown user',
                    'user_role' => $log->user?->role ?? 'unknown',
                    'action' => $log->action,
                    'remarks' => $log->remarks,
                    'admin_name' => $log->admin?->name ?? 'System',
                    'created_at' => optional($log->created_at)?->toIso8601String(),
                ];
            })
            ->values();

        $academicEvaluations = AcademicEligibilityEvaluation::query()
            ->with([
                'student.user:id,first_name,middle_name,last_name',
                'academicPeriod:id,school_year,term',
                'evaluator:id,first_name,middle_name,last_name',
            ])
            ->whereNotNull('evaluated_at')
            ->latest('evaluated_at')
            ->limit(20)
            ->get()
            ->map(function (AcademicEligibilityEvaluation $evaluation) {
                return [
                    'id' => $evaluation->id,
                    'student_name' => $evaluation->student?->user?->name ?? 'Unknown student',
                    'status' => $evaluation->status,
                    'gpa' => $evaluation->gpa !== null ? (float) $evaluation->gpa : null,
                    'remarks' => $evaluation->remarks,
                    'period_label' => $evaluation->academicPeriod
                        ? trim($evaluation->academicPeriod->school_year . ' ' . $evaluation->academicPeriod->term)
                        : null,
                    'evaluator_name' => $evaluation->evaluator?->name ?? 'System',
                    'evaluated_at' => optional($evaluation->evaluated_at)?->toIso8601String(),
                ];
            })
            ->values();

        return Inertia::render('Admin/AuditTrail', [
            'summary' => [
                'approval_events' => $approvalHistory->count(),
                'account_actions' => $accountActions->count(),
                'academic_evaluations' => $academicEvaluations->count(),
            ],
            'approvalHistory' => $approvalHistory,
            'accountActions' => $accountActions,
            'academicEvaluations' => $academicEvaluations,
        ]);
    }

    public function approve(User $user)
    {
        if (!in_array($user->role, ['student-athlete', 'student'], true) || !$user->student) {
            return back()->withErrors([
                'approval' => 'Only student accounts require approval.',
            ]);
        }

        if (in_array($user->role, ['student-athlete', 'student'], true)) {
            $registrationDocs = $user->student?->registrationDocuments()->with('documentTypeDefinition')->get() ?? collect();
            $hasTor = $registrationDocs->contains(fn ($document) => $document->document_type === DocumentType::CODE_TOR);
            $hasMedical = $registrationDocs->contains(fn ($document) => $document->document_type === DocumentType::CODE_MEDICAL_DOCUMENT);

            if (!$hasTor || !$hasMedical) {
                return back()->withErrors([
                    'approval' => 'Cannot approve this student-athlete without both the academic registration file and medical clearance.',
                ]);
            }
        }

        DB::transaction(function () use ($user) {
            $user->student->update([
                'approval_status' => 'approved',
            ]);

            StudentApprovalHistory::create($this->studentApprovalHistoryAttributes(
                $user->student,
                'approved',
                null,
                Auth::user()?->coach?->id
            ));
        });

        $this->notifications->announce(
            $user->id,
            'Account Approved',
            'Your account has been approved. You may now log in and access the system.',
            Announcement::TYPE_APPROVAL,
            Auth::id(),
            'notify_approvals',
            false
        );

        $this->sendAccountStatusMail($user->fresh(['settings']), new AccountApprovedMail($user));

        return back()->with('success', 'User approved.');
    }

    public function reject(Request $request, User $user)
    {
        if (!in_array($user->role, ['student-athlete', 'student'], true) || !$user->student) {
            return back()->withErrors([
                'approval' => 'Only student accounts require approval.',
            ]);
        }

        $request->validate([
            'remarks' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($user, $request) {
            $user->student->update([
                'approval_status' => 'rejected',
            ]);

            StudentApprovalHistory::create($this->studentApprovalHistoryAttributes(
                $user->student,
                'rejected',
                $request->remarks,
                Auth::user()?->coach?->id
            ));
        });

        $this->notifications->announce(
            $user->id,
            'Account Rejected',
            'Your account registration was rejected.' . ($request->remarks ? " Remarks: {$request->remarks}" : ''),
            Announcement::TYPE_APPROVAL,
            Auth::id(),
            'notify_approvals',
            false
        );

        $this->sendAccountStatusMail($user->fresh(['settings']), new AccountRejectedMail($user, $request->remarks));

        return back()->with('success', 'User rejected.');
    }

    public function deactivate(User $user)
    {
        if ($user->role === 'admin') {
            return back()->withErrors([
                'user_action' => 'Admin accounts cannot be deactivated from this panel.',
            ]);
        }

        if ($user->account_state !== 'active') {
            return back()->withErrors([
                'user_action' => 'Only active accounts can be deactivated.',
            ]);
        }

        try {
            DB::transaction(function () use ($user) {
                $user->update([
                    'account_state' => 'deactivated',
                ]);

                if ($user->role === 'coach' && $user->coach) {
                    $user->coach->update([
                        'coach_status' => 'Inactive',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to deactivate user account.', [
                'target_user_id' => $user->id,
                'target_role' => $user->role,
                'admin_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'user_action' => 'Unable to deactivate this account right now. Please try again.',
            ]);
        }

        try {
            $this->notifications->announce(
                $user->id,
                'Account Deactivated',
                'Your account has been temporarily deactivated. Please contact the system administrator or varsity office for assistance.',
                Announcement::TYPE_SYSTEM,
                Auth::id(),
                'notify_approvals'
            );
        } catch (\Throwable $e) {
            Log::warning('User deactivated but announcement dispatch failed.', [
                'target_user_id' => $user->id,
                'admin_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'The account has been deactivated.');
    }

    public function reactivate(User $user)
    {
        if ($user->role === 'admin') {
            return back()->withErrors([
                'user_action' => 'Admin accounts cannot be reactivated from this panel.',
            ]);
        }

        if ($user->account_state !== 'deactivated') {
            return back()->withErrors([
                'user_action' => 'Only deactivated accounts can be reactivated.',
            ]);
        }

        try {
            DB::transaction(function () use ($user) {
                $user->update([
                    'account_state' => 'active',
                ]);

                if ($user->role === 'coach' && $user->coach) {
                    $user->coach->update([
                        'coach_status' => 'Active',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to reactivate user account.', [
                'target_user_id' => $user->id,
                'target_role' => $user->role,
                'admin_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'user_action' => 'Unable to reactivate this account right now. Please try again.',
            ]);
        }

        try {
            $this->notifications->announce(
                $user->id,
                'Account Reactivated',
                'Your account has been reactivated. You may log in again.',
                Announcement::TYPE_SYSTEM,
                Auth::id(),
                'notify_approvals'
            );
        } catch (\Throwable $e) {
            Log::warning('User reactivated but announcement dispatch failed.', [
                'target_user_id' => $user->id,
                'admin_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'User reactivated.');
    }

    public function approvalManagement(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'pending');
        $readiness = (string) $request->query('readiness', 'all');
        $sort = (string) $request->query('sort', 'newest');

        $allowedStatuses = ['pending', 'rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $allowedReadiness = ['all', 'ready', 'incomplete'];
        if (!in_array($readiness, $allowedReadiness, true)) {
            $readiness = 'all';
        }

        $allowedSorts = ['newest', 'oldest', 'name_asc', 'name_desc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'newest';
        }

        $baseQuery = User::query()
            ->whereIn('role', ['student-athlete', 'student'])
            ->whereHas('student', fn ($query) => $query->where('approval_status', $status));

        if ($search !== '') {
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];

            $baseQuery->where(function ($query) use ($terms, $search) {
                $query->where(function ($nameQuery) use ($terms) {
                    foreach ($terms as $term) {
                        $nameQuery->where(function ($termQuery) use ($term) {
                            $termQuery->where('first_name', 'like', "%{$term}%")
                                ->orWhere('middle_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%")
                                ->orWhereHas('student', function ($studentQuery) use ($term) {
                                    $studentQuery->where('student_id_number', 'like', "%{$term}%")
                                        ->orWhere('course_or_strand', 'like', "%{$term}%");
                                })
                                ->orWhereHas('coach', function ($coachQuery) use ($term) {
                                    $coachQuery->where('phone_number', 'like', "%{$term}%")
                                        ->orWhere('first_name', 'like', "%{$term}%")
                                        ->orWhere('middle_name', 'like', "%{$term}%")
                                        ->orWhere('last_name', 'like', "%{$term}%");
                                });
                        });
                    }
                })
                ->orWhereRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) like ?", ["%{$search}%"])
                ->orWhereRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, ''))) like ?", ["%{$search}%"]);
            });
        }

        $applyReadinessFilter = function ($query, string $state): void {
            if ($state === 'ready') {
                $query->where(function ($q) {
                    $q->whereIn('role', ['student-athlete', 'student'])
                        ->whereHas('student.registrationDocuments', function ($documentQuery) {
                            $documentQuery->whereHas('documentTypeDefinition', function ($typeQuery) {
                                $typeQuery->whereIn('code', [
                                    DocumentType::CODE_TOR,
                                    DocumentType::CODE_MEDICAL_DOCUMENT,
                                ]);
                            });
                        });
                });
            }

            if ($state === 'incomplete') {
                $query->whereIn('role', ['student-athlete', 'student'])
                    ->where(function ($q) {
                        $q->whereDoesntHave('student.registrationDocuments', function ($documentQuery) {
                            $documentQuery->whereHas('documentTypeDefinition', function ($typeQuery) {
                                $typeQuery->where('code', DocumentType::CODE_TOR);
                            });
                        })->orWhereDoesntHave('student.registrationDocuments', function ($documentQuery) {
                            $documentQuery->whereHas('documentTypeDefinition', function ($typeQuery) {
                                $typeQuery->where('code', DocumentType::CODE_MEDICAL_DOCUMENT);
                            });
                        });
                    });
            }
        };

        if ($status === 'pending') {
            $applyReadinessFilter($baseQuery, $readiness);
        }

        $queueQuery = (clone $baseQuery)
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'email', 'role', 'account_state', 'avatar', 'created_at'])
            ->with([
                'student:id,user_id,student_id_number,home_address,course_or_strand,current_grade_level,approval_status,phone_number,date_of_birth,gender,height,weight,emergency_contact_name,emergency_contact_relationship,emergency_contact_phone',
                'student.registrationDocuments' => function ($query) {
                    $query->select(
                        'id',
                        'student_id',
                        'document_type_id',
                        'uploaded_at'
                    )->with('documentTypeDefinition:id,code');
                },
            ]);

        if ($sort === 'newest') {
            $queueQuery->latest();
        } elseif ($sort === 'oldest') {
            $queueQuery->oldest();
        } elseif ($sort === 'name_asc') {
            $queueQuery->orderBy('last_name', 'asc')->orderBy('first_name', 'asc');
        } else {
            $queueQuery->orderBy('last_name', 'desc')->orderBy('first_name', 'desc');
        }

        $queue = $queueQuery
            ->paginate(10)
            ->withQueryString()
            ->through(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->approval_status,
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar_url,
                    'created_at' => optional($user->created_at)->toDateTimeString(),
                    'student' => $user->student ? (function () use ($user) {
                        $documents = $user->student->registrationDocuments->values();
                        return [
                        'id' => $user->student->id,
                        'student_id_number' => $user->student->student_id_number,
                        'first_name' => $user->student->first_name,
                        'middle_name' => $user->student->middle_name,
                        'last_name' => $user->student->last_name,
                        'home_address' => $user->student->home_address,
                        'course_or_strand' => $user->student->course_or_strand,
                        'current_grade_level' => $user->student->current_grade_level,
                        'academic_level_label' => $user->student->academic_level_label,
                        'approval_status' => $user->student->approval_status,
                        'phone_number' => $user->student->phone_number,
                        'date_of_birth' => $user->student->date_of_birth ? (string) $user->student->date_of_birth : null,
                        'gender' => $user->student->gender,
                        'height' => $user->student->height,
                        'weight' => $user->student->weight,
                        'emergency_contact_name' => $user->student->emergency_contact_name,
                        'emergency_contact_relationship' => $user->student->emergency_contact_relationship,
                        'emergency_contact_phone' => $user->student->emergency_contact_phone,
                        'latest_academic_document' => ($documents->firstWhere('document_type', DocumentType::CODE_TOR)
                            ?? $documents->first()) ? [
                                'id' => ($documents->firstWhere('document_type', DocumentType::CODE_TOR) ?? $documents->first())->id,
                                'document_type' => ($documents->firstWhere('document_type', DocumentType::CODE_TOR) ?? $documents->first())->document_type,
                                'uploaded_at' => optional(($documents->firstWhere('document_type', DocumentType::CODE_TOR) ?? $documents->first())->uploaded_at)->toDateTimeString(),
                            ] : null,
                        'registration_documents' => $user->student->registrationDocuments
                            ->map(fn ($document) => [
                                'id' => $document->id,
                                'document_type' => $document->document_type,
                                'uploaded_at' => optional($document->uploaded_at)->toDateTimeString(),
                            ])
                            ->values()
                            ->all(),
                    ];
                    })() : null,
                ];
            });

        $pendingPool = $this->pendingStudentApprovalUsers();
        $rejectedPool = $this->rejectedStudentApprovalUsers();
        $readyPool = $this->pendingStudentApprovalUsers();
        $applyReadinessFilter($readyPool, 'ready');

        $incompletePool = $this->pendingStudentApprovalUsers();
        $applyReadinessFilter($incompletePool, 'incomplete');

        return inertia('Admin/PeopleQueue', [
            'queue' => $queue,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'readiness' => $readiness,
                'sort' => $sort,
            ],
            'stats' => [
                'pending_total' => (clone $pendingPool)->count(),
                'ready_total' => (clone $readyPool)->count(),
                'incomplete_total' => (clone $incompletePool)->count(),
                'rejected_total' => (clone $rejectedPool)->count(),
            ],
            'pendingCount' => (clone $pendingPool)->count(),
        ]);
    }

    public function userManagement(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', 'all');
        $status = (string) $request->query('status', 'active');
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedRoles = ['all', 'student-athlete', 'coach'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'all';
        }

        $allowedStatuses = ['active', 'deactivated'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'active';
        }

        $allowedSorts = ['name', 'email', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $baseQuery = User::query()
            ->where('account_state', $status)
            ->whereIn('role', ['student-athlete', 'student', 'coach']);

        if ($role !== 'all') {
            if ($role === 'student-athlete') {
                $baseQuery->whereIn('role', ['student-athlete', 'student']);
            } else {
                $baseQuery->where('role', $role);
            }
        }

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery->where('student_id_number', 'like', "%{$search}%")
                            ->orWhere('course_or_strand', 'like', "%{$search}%")
                            ->orWhere('current_grade_level', 'like', "%{$search}%")
                            ->orWhereHas('appliedSport', fn ($sportQuery) => $sportQuery->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('teams.team', function ($teamQuery) use ($search) {
                                $teamQuery->where('team_name', 'like', "%{$search}%")
                                    ->orWhereHas('sport', fn ($sportQuery) => $sportQuery->where('name', 'like', "%{$search}%"));
                            });
                    })
                    ->orWhereHas('coach', function ($coachQuery) use ($search) {
                        $coachQuery->where('coach_status', 'like', "%{$search}%")
                            ->orWhereHas('sport', fn ($sportQuery) => $sportQuery->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('staffAssignments.team', function ($teamQuery) use ($search) {
                                $teamQuery->where('team_name', 'like', "%{$search}%")
                                    ->orWhereHas('sport', fn ($sportQuery) => $sportQuery->where('name', 'like', "%{$search}%"));
                            });
                    });
            });
        }

        $users = (clone $baseQuery)
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'email', 'role', 'account_state', 'avatar', 'created_at'])
            ->with([
                'student:id,user_id,student_id_number,course_or_strand,current_grade_level,approval_status,applied_sport_id,student_status,phone_number,date_of_birth,gender,height,weight,emergency_contact_name,emergency_contact_relationship,emergency_contact_phone',
                'student.appliedSport:id,name',
                'student.teams:id,team_id,student_id,athlete_position,player_status',
                'student.teams.team:id,team_name,sport_id,year,archived_at',
                'student.teams.team.sport:id,name',
                'coach:id,user_id,coach_status,sport_id,phone_number,date_of_birth,gender',
                'coach.sport:id,name',
                'coach.staffAssignments:id,team_id,coach_id,role,starts_at,ends_at',
                'coach.staffAssignments.team:id,team_name,sport_id,year,archived_at',
                'coach.staffAssignments.team.sport:id,name',
            ])
            ->when($sort === 'name', function ($query) use ($direction) {
                $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
            }, function ($query) use ($sort, $direction) {
                $query->orderBy($sort, $direction);
            })
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => $this->serializeDirectoryUser($user));

        $totalBase = User::query()
            ->whereIn('account_state', ['active', 'deactivated'])
            ->whereIn('role', ['student-athlete', 'student', 'coach']);

        $totals = [
            'all' => (clone $totalBase)->where('account_state', 'active')->count(),
            'students' => (clone $totalBase)->where('account_state', 'active')->whereIn('role', ['student-athlete', 'student'])->count(),
            'coaches' => (clone $totalBase)->where('account_state', 'active')->where('role', 'coach')->count(),
            'deactivated' => (clone $totalBase)->where('account_state', 'deactivated')->count(),
            'filtered' => (clone $baseQuery)->count(),
        ];
        $sports = Sport::supported()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Sport $sport) => [
                'id' => (int) $sport->id,
                'name' => $sport->name,
            ])
            ->values();

        return Inertia::render('Admin/PeopleUsers', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'totals' => $totals,
            'sports' => $sports,
        ]);
    }

    public function showUserProfile(User $user)
    {
        abort_unless(in_array($user->role, ['student-athlete', 'student', 'coach'], true), 404);

        $user->load([
            'student:id,user_id,student_id_number,course_or_strand,current_grade_level,approval_status,applied_sport_id,student_status,phone_number,date_of_birth,gender,height,weight,emergency_contact_name,emergency_contact_relationship,emergency_contact_phone',
            'student.appliedSport:id,name',
            'student.teams:id,team_id,student_id,athlete_position,player_status',
            'student.teams.team:id,team_name,sport_id,year,archived_at',
            'student.teams.team.sport:id,name',
            'coach:id,user_id,coach_status,sport_id,phone_number,date_of_birth,gender',
            'coach.sport:id,name',
            'coach.staffAssignments:id,team_id,coach_id,role,starts_at,ends_at',
            'coach.staffAssignments.team:id,team_name,sport_id,year,archived_at',
            'coach.staffAssignments.team.sport:id,name',
        ]);

        return Inertia::render('Admin/PeopleUserProfile', [
            'user' => $this->serializeUserProfile($user),
        ]);
    }

    public function destroyUser(User $user)
    {
        if ($user->role === 'admin') {
            return back()->withErrors([
                'user_action' => 'Administrator accounts cannot be deleted from the user directory.',
            ]);
        }

        $deletionMeta = $this->deletionMeta($user);
        if (!$deletionMeta['can_delete']) {
            return back()->withErrors([
                'user_action' => 'This account has linked records and cannot be permanently deleted. Deactivate it instead to preserve data integrity.',
            ]);
        }

        try {
            DB::transaction(function () use ($user) {
                if ($user->student) {
                    $user->student->delete();
                }

                if ($user->coach) {
                    $user->coach->delete();
                }

                $user->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to permanently delete user account.', [
                'target_user_id' => $user->id,
                'target_role' => $user->role,
                'admin_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'user_action' => 'Unable to delete this account right now. Please try again.',
            ]);
        }

        return redirect()->route('admin.people.index')->with('success', 'The account was deleted permanently.');
    }

    private function serializeDirectoryUser(User $user): array
    {
        $assignment = $this->assignmentSummary($user);
        $deletion = $this->deletionMeta($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->account_state,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar_url,
            'created_at' => optional($user->created_at)->toDateTimeString(),
            'assignment' => [
                'sport_label' => $assignment['sport_label'],
                'status_label' => $assignment['status_label'],
                'current_teams' => $assignment['current_teams'],
                'history_teams' => $assignment['history_teams'],
                'needs_action' => $assignment['needs_action'],
                'needs_action_label' => $assignment['needs_action'] ? 'Needs assignment' : null,
            ],
            'actions' => [
                'can_send_activation_link' => $user->role === 'coach',
                'can_deactivate' => $user->account_state === 'active',
                'can_reactivate' => $user->account_state === 'deactivated',
                'can_delete' => $deletion['can_delete'],
                'delete_blockers' => $deletion['blockers'],
            ],
        ];
    }

    private function serializeUserProfile(User $user): array
    {
        $assignment = $this->assignmentSummary($user);
        $deletion = $this->deletionMeta($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->account_state,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar_url,
            'created_at' => optional($user->created_at)->toDateTimeString(),
            'assignment' => $assignment,
            'student' => $user->student ? [
                'student_id_number' => $user->student->student_id_number,
                'course_or_strand' => $user->student->course_or_strand,
                'current_grade_level' => $user->student->current_grade_level,
                'academic_level_label' => $user->student->academic_level_label,
                'student_status' => $user->student->student_status,
                'approval_status' => $user->student->approval_status,
                'applied_sport' => $user->student->appliedSport?->name,
                'phone_number' => $user->student->phone_number,
                'emergency_contact_name' => $user->student->emergency_contact_name,
                'emergency_contact_relationship' => $user->student->emergency_contact_relationship,
                'emergency_contact_phone' => $user->student->emergency_contact_phone,
                'date_of_birth' => $user->student->date_of_birth ? (string) $user->student->date_of_birth : null,
                'gender' => $user->student->gender,
                'height' => $user->student->height,
                'weight' => $user->student->weight,
            ] : null,
            'coach' => $user->coach ? [
                'coach_status' => $user->coach->coach_status,
                'sport' => $user->coach->sport?->name,
                'phone_number' => $user->coach->phone_number,
                'date_of_birth' => $user->coach->date_of_birth ? (string) $user->coach->date_of_birth : null,
                'gender' => $user->coach->gender,
            ] : null,
            'actions' => [
                'can_send_activation_link' => $user->role === 'coach',
                'can_deactivate' => $user->account_state === 'active',
                'can_reactivate' => $user->account_state === 'deactivated',
                'can_delete' => $deletion['can_delete'],
                'delete_blockers' => $deletion['blockers'],
                'delete_warning' => $deletion['can_delete']
                    ? 'Permanent deletion is only intended for mistaken or test accounts with no linked records.'
                    : 'Permanent deletion is blocked because this account has linked records. Deactivation is the safe option.',
            ],
        ];
    }

    private function assignmentSummary(User $user): array
    {
        $currentTeams = [];
        $historyTeams = [];
        $sportLabel = null;

        if (in_array($user->role, ['student-athlete', 'student'], true) && $user->student) {
            $sportLabel = $user->student->appliedSport?->name;

            foreach ($user->student->teams as $teamPlayer) {
                $team = $teamPlayer->team;
                if (!$team) {
                    continue;
                }

                $item = [
                    'id' => (int) $team->id,
                    'name' => $team->team_name,
                    'sport_name' => $team->sport?->name ?? 'Unknown',
                    'year' => $team->year,
                    'role_label' => 'Player',
                    'position' => $teamPlayer->athlete_position,
                    'status' => $teamPlayer->player_status,
                    'archived' => !is_null($team->archived_at),
                ];

                if ($item['archived']) {
                    $historyTeams[] = $item;
                } else {
                    $currentTeams[] = $item;
                }
            }
        }

        if ($user->role === 'coach' && $user->coach) {
            $sportLabel = $user->coach->sport?->name;

            foreach ($user->coach->staffAssignments as $assignment) {
                $team = $assignment->team;
                if (!$team) {
                    continue;
                }

                $item = [
                    'id' => (int) $team->id,
                    'name' => $team->team_name,
                    'sport_name' => $team->sport?->name ?? 'Unknown',
                    'year' => $team->year,
                    'role_label' => $assignment->role === 'assistant' ? 'Assistant Coach' : 'Head Coach',
                    'position' => null,
                    'status' => $assignment->ends_at ? 'ended' : 'active',
                    'archived' => !is_null($team->archived_at),
                ];

                if ($assignment->ends_at || $item['archived']) {
                    $historyTeams[] = $item;
                } else {
                    $currentTeams[] = $item;
                }
            }
        }

        $currentTeams = collect($currentTeams)
            ->unique(fn (array $team) => $team['id'] . ':' . $team['role_label'])
            ->values()
            ->all();
        $historyTeams = collect($historyTeams)
            ->unique(fn (array $team) => $team['id'] . ':' . $team['role_label'] . ':' . ($team['status'] ?? ''))
            ->values()
            ->all();

        if (!$sportLabel && count($currentTeams) > 0) {
            $sportLabel = $currentTeams[0]['sport_name'] ?? null;
        }

        $statusLabel = count($currentTeams) > 0
            ? implode(', ', array_map(fn (array $team) => $team['name'], $currentTeams))
            : (count($historyTeams) > 0 ? 'Archived history only' : 'Unassigned');

        return [
            'sport_label' => $sportLabel,
            'status_label' => $statusLabel,
            'current_teams' => $currentTeams,
            'history_teams' => $historyTeams,
            'needs_action' => count($currentTeams) === 0,
        ];
    }

    private function deletionMeta(User $user): array
    {
        $blockers = [];

        if ($user->role === 'admin') {
            $blockers[] = ['label' => 'Administrator account', 'count' => 1];
        }

        if ($user->student) {
            $studentId = $user->student->id;

            $this->pushDeletionBlocker($blockers, 'Team roster assignments', $this->safeTableCount('team_players', ['student_id' => $studentId]));
            $this->pushDeletionBlocker($blockers, 'Attendance records', $this->safeTableCount('schedule_attendances', ['student_id' => $studentId]));
            $this->pushDeletionBlocker($blockers, 'Student documents', $this->safeTableCount('student_documents', ['student_id' => $studentId]));
            $this->pushDeletionBlocker($blockers, 'Academic evaluations', $this->safeTableCount('academic_eligibility_evaluations', ['student_id' => $studentId]));
            $this->pushDeletionBlocker($blockers, 'Academic holds', $this->safeTableCount('academic_holds', ['student_id' => $studentId]));
            $this->pushDeletionBlocker($blockers, 'Training requirements', $this->safeTableCount('training_requirements', ['student_id' => $studentId]));
            $this->pushDeletionBlocker($blockers, 'Approval history', $this->safeTableCount('student_approval_histories', ['student_id' => $studentId]));
        }

        if ($user->coach) {
            $coachId = $user->coach->id;

            $this->pushDeletionBlocker($blockers, 'Team staff assignments', $this->safeTableCount('team_staff_assignments', ['coach_id' => $coachId]));
            $this->pushDeletionBlocker($blockers, 'Training requirements', $this->safeTableCount('training_requirements', ['coach_id' => $coachId]));
            $this->pushDeletionBlocker($blockers, 'Approval history', $this->safeTableCount('student_approval_histories', ['coach_id' => $coachId]));
        }

        $this->pushDeletionBlocker($blockers, 'Account action logs', $this->safeTableCount('account_action_logs', ['user_id' => $user->id]));
        $this->pushDeletionBlocker($blockers, 'Announcements', $this->safeTableCount('announcements', ['user_id' => $user->id]));
        $this->pushDeletionBlocker($blockers, 'Announcement events', $this->safeTableCount('announcement_events', ['created_by' => $user->id]));
        $this->pushDeletionBlocker($blockers, 'Recorded attendance actions', $this->safeTableCount('schedule_attendances', ['recorded_by' => $user->id]));
        $this->pushDeletionBlocker($blockers, 'Uploaded documents', $this->safeTableCount('student_documents', ['uploaded_by' => $user->id]));
        $this->pushDeletionBlocker($blockers, 'Reviewed documents', $this->safeTableCount('student_documents', ['reviewed_by' => $user->id]));
        $this->pushDeletionBlocker($blockers, 'Evaluations performed', $this->safeTableCount('academic_eligibility_evaluations', ['evaluated_by' => $user->id]));

        return [
            'can_delete' => count($blockers) === 0,
            'blockers' => $blockers,
        ];
    }

    private function pushDeletionBlocker(array &$blockers, string $label, int $count): void
    {
        if ($count > 0) {
            $blockers[] = [
                'label' => $label,
                'count' => $count,
            ];
        }
    }

    private function safeTableCount(string $table, array $where): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->where($where)->count();
    }

    public function storeAdminInvite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email',
        ]);

        AdminInvite::query()
            ->where('email', $validated['email'])
            ->whereNull('used_at')
            ->delete();

        $plainToken = Str::random(64);
        $invite = AdminInvite::create([
            'email' => $validated['email'],
            'token_hash' => hash('sha256', $plainToken),
            'created_by' => Auth::id(),
            'expires_at' => now()->addDays(3),
        ]);

        $acceptUrl = route('admin.invite.accept', [
            'email' => $invite->email,
            'token' => $plainToken,
        ]);

        $this->notifications->sendEmail($invite->email, new AdminInviteMail($invite, $request->user(), $acceptUrl), $invite->email, [
            'context' => [
                'communication' => 'admin_invite',
                'invite_id' => $invite->id,
                'admin_id' => Auth::id(),
            ],
        ]);

        return back()->with('success', "Admin invitation created for {$invite->email}. Email delivery will continue in the background.");
    }

    public function showAdminInviteAcceptance(Request $request)
    {
        $invite = $this->resolveAdminInvite(
            (string) $request->query('email', ''),
            (string) $request->query('token', '')
        );

        if (!$invite) {
            return redirect('/Login')->withErrors([
                'message' => 'This admin invitation is invalid, expired, or already used.',
            ]);
        }

        return Inertia::render('Auth/AdminInviteAccept', [
            'email' => $invite->email,
            'token' => (string) $request->query('token', ''),
            'expiresAt' => $invite->expires_at?->timezone(config('app.timezone'))->format('M d, Y h:i A'),
        ]);
    }

    public function acceptAdminInvite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'token' => 'required|string',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $invite = $this->resolveAdminInvite($validated['email'], $validated['token']);

        if (!$invite) {
            throw ValidationException::withMessages([
                'email' => 'This admin invitation is invalid, expired, or already used.',
            ]);
        }

        if (User::query()->where('email', $validated['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account with this email already exists.',
            ]);
        }

        DB::transaction(function () use ($validated, $invite) {
            User::create([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
                'role' => 'admin',
                'account_state' => 'active',
            ]);

            $invite->update([
                'used_at' => now(),
            ]);
        });

        return redirect('/Login')->with('success', 'The administrator account has been created successfully. You may now sign in.');
    }

    public function storeCoach(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'sport_id' => ['required', 'integer', Rule::exists('sports', 'id')->where(fn ($query) => $query->whereIn('name', Sport::supportedNames()))],
            'phone_number' => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female,Other',
            'home_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ]);

        $newUser = DB::transaction(function () use ($validated) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(64)),
                'must_change_password' => true,
                'role' => 'coach',
                'account_state' => 'active',
            ]);

            $coach = Coach::create([
                'user_id' => $user->id,
                'phone_number' => $validated['phone_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'home_address' => $validated['home_address'] ?? null,
                'coach_status' => 'Active',
                'sport_id' => (int) $validated['sport_id'],
            ]);

            $remarks = "Admin-provisioned coach account created."
                . (!empty($validated['notes']) ? " Notes: {$validated['notes']}" : '');
            AccountActionLog::create([
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'action' => 'coach_account_provisioned',
                'remarks' => $remarks,
            ]);

            return $user;
        });
        $delivery = $this->deliverCoachActivationLink(
            $newUser,
            'coach_onboarding',
            'Coach Account Created',
            'Your coach account has been provisioned by the administrator. Please use the activation link sent to your email to set your password.'
        );

        $message = $delivery['activation_url']
            ? 'The coach account has been created successfully. An activation link has been sent when email delivery is available.'
            : 'The coach account has been created, but the activation link could not be sent. Please try Send Activation Link again or check mail settings.';

        return back()
            ->with('success', $message)
            ->with('coach_onboarding', [
                'email' => $newUser->email,
                'email_sent' => $delivery['email_sent'],
                'activation_url' => $delivery['activation_url'],
            ]);
    }

    public function sendCoachActivationLink(User $user)
    {
        if ($user->role !== 'coach') {
            return back()->withErrors([
                'user_action' => 'Activation links may only be sent for coach accounts.',
            ]);
        }

        DB::transaction(function () use ($user) {
            AccountActionLog::create([
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'action' => 'coach_activation_link_sent',
                'remarks' => 'Coach activation link sent by admin.',
            ]);
        });

        $delivery = $this->deliverCoachActivationLink(
            $user,
            'coach_activation_link',
            'Coach Activation Link Sent',
            'A secure coach account activation link was sent by the administrator.'
        );

        if (!$delivery['activation_url']) {
            return back()->withErrors([
                'user_action' => 'The activation link could not be generated. Please check password reset and mail settings.',
            ]);
        }

        return back()
            ->with('success', $delivery['email_sent'] ? 'Coach activation link has been sent.' : 'Activation link was generated, but email delivery failed. Please check mail settings.')
            ->with('coach_onboarding', [
                'email' => $user->email,
                'email_sent' => $delivery['email_sent'],
                'activation_url' => $delivery['activation_url'],
            ]);
    }

    private function deliverCoachActivationLink(User $user, string $communication, string $title, string $message): array
    {
        $activationUrl = null;
        $emailSent = false;

        try {
            $activationToken = Password::broker()->createToken($user);
            $activationUrl = route('coach.onboarding.activate', [
                'email' => $user->email,
                'token' => $activationToken,
            ]);
        } catch (\Throwable $e) {
            Log::error('Coach activation token generation failed.', [
                'communication' => $communication,
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return [
                'activation_url' => null,
                'email_sent' => false,
            ];
        }

        try {
            $this->notifications->announce(
                $user->id,
                $title,
                $message,
                Announcement::TYPE_APPROVAL,
                Auth::id(),
                'notify_approvals',
                false
            );
        } catch (\Throwable $e) {
            Log::error('Coach activation notification failed.', [
                'communication' => $communication,
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $emailSent = $this->notifications->sendUserEmail(
                $user,
                new CoachOnboardingMail($user, url('/Login'), $activationUrl),
                [
                    'defer' => false,
                    'respect_preferences' => false,
                    'context' => [
                        'communication' => $communication,
                        'user_id' => $user->id,
                        'admin_id' => Auth::id(),
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Coach activation email delivery failed.', [
                'communication' => $communication,
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'activation_url' => $activationUrl,
            'email_sent' => $emailSent,
        ];
    }

    private function dashboardRange(string $period): array
    {
        $now = now();
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();

        if ($period === 'week') {
            $start = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }

        if ($period === 'month') {
            $start = $now->copy()->startOfMonth()->startOfDay();
            $end = $now->copy()->endOfMonth()->endOfDay();
        }

        return [$start, $end];
    }

    private function attendanceSummary(CarbonInterface $start, CarbonInterface $end): array
    {
        $row = DB::table('team_schedules as ts')
            ->join('team_players as tp', 'tp.team_id', '=', 'ts.team_id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->first();

        $total = (int) ($row->total_rows ?? 0);
        $present = (int) ($row->present_count ?? 0);
        $noResponse = (int) ($row->no_response_count ?? 0);

        return [
            'total' => $total,
            'present' => $present,
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            'no_response' => $noResponse,
        ];
    }

    private function attendanceTrend(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = DB::table('team_schedules as ts')
            ->join('team_players as tp', 'tp.team_id', '=', 'ts.team_id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('DATE(ts.start_time) as schedule_date')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupByRaw('DATE(ts.start_time)')
            ->orderByRaw('DATE(ts.start_time)')
            ->get()
            ->keyBy('schedule_date');

        $labels = [];
        $series = [
            'present' => [],
            'late' => [],
            'absent' => [],
            'no_response' => [],
        ];

        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('M j');
            $series['present'][] = (int) ($rows[$key]->present_count ?? 0);
            $series['late'][] = (int) ($rows[$key]->late_count ?? 0);
            $series['absent'][] = (int) ($rows[$key]->absent_count ?? 0);
            $series['no_response'][] = (int) ($rows[$key]->no_response_count ?? 0);
            $cursor = $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }

    private function attendanceByTeam(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = DB::table('team_schedules as ts')
            ->join('teams as t', 't.id', '=', 'ts.team_id')
            ->join('team_players as tp', 'tp.team_id', '=', 'ts.team_id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereNull('t.archived_at')
            ->selectRaw('t.team_name as team_name')
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw("SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->groupBy('t.team_name')
            ->havingRaw('COUNT(*) > 0')
            ->orderByRaw("(SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) DESC")
            ->orderBy('t.team_name')
            ->limit(8)
            ->get();

        return [
            'labels' => $rows->pluck('team_name')->values()->all(),
            'rates' => $rows->map(fn ($row) => round((((int) $row->present_count) / max(1, (int) $row->total_rows)) * 100, 2))->values()->all(),
        ];
    }

    private function academicByTeam(): array
    {
        $periodId = DB::table('academic_periods')->orderByDesc('starts_on')->value('id');
        if (!$periodId) {
            return [
                'rows' => [],
                'totals' => ['eligible' => 0, 'pending_review' => 0, 'ineligible' => 0],
            ];
        }

        $rows = DB::table('academic_eligibility_evaluations as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->leftJoin('teams as t', function ($join) {
                $join->on('t.id', '=', DB::raw('(SELECT tp.team_id FROM team_players tp WHERE tp.student_id = s.id ORDER BY tp.id ASC LIMIT 1)'));
            })
            ->where('e.academic_period_id', $periodId)
            ->selectRaw("COALESCE(t.team_name, 'Unassigned') as team_name")
            ->selectRaw("SUM(CASE WHEN e.final_status = 'eligible' THEN 1 ELSE 0 END) as eligible_count")
            ->selectRaw("SUM(CASE WHEN e.final_status = 'pending_review' THEN 1 ELSE 0 END) as pending_review_count")
            ->selectRaw("SUM(CASE WHEN e.final_status = 'ineligible' THEN 1 ELSE 0 END) as ineligible_count")
            ->selectRaw("SUM(CASE WHEN e.final_status IN ('pending_review', 'ineligible') THEN 1 ELSE 0 END) as risk_count")
            ->groupBy('team_name')
            ->orderByDesc('risk_count')
            ->orderBy('team_name')
            ->limit(8)
            ->get();

        return [
            'rows' => $rows->map(fn ($row) => [
                'team_name' => $row->team_name,
                'eligible' => (int) $row->eligible_count,
                'pending_review' => (int) $row->pending_review_count,
                'ineligible' => (int) $row->ineligible_count,
                'total' => (int) $row->eligible_count + (int) $row->pending_review_count + (int) $row->ineligible_count,
            ])->values(),
            'totals' => [
                'eligible' => (int) $rows->sum('eligible_count'),
                'pending_review' => (int) $rows->sum('pending_review_count'),
                'ineligible' => (int) $rows->sum('ineligible_count'),
            ],
        ];
    }

    private function pendingItemsSummary(CarbonInterface $start, CarbonInterface $end): array
    {
        $pendingAcademicReviews = DB::table('academic_eligibility_evaluations')
            ->where('final_status', 'pending_review')
            ->count();

        $teamsWithoutRecentAttendance = DB::table('teams as t')
            ->whereNull('t.archived_at')
            ->whereExists(function ($query) use ($start, $end) {
                $query->selectRaw('1')
                    ->from('team_schedules as ts')
                    ->whereColumn('ts.team_id', 't.id')
                    ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()]);
            })
            ->whereNotExists(function ($query) use ($start, $end) {
                $query->selectRaw('1')
                    ->from('team_schedules as ts')
                    ->join('schedule_attendances as sa', 'sa.schedule_id', '=', 'ts.id')
                    ->whereColumn('ts.team_id', 't.id')
                    ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()]);
            })
            ->count();

        return [
            'pending_academic_reviews' => $pendingAcademicReviews,
            'teams_without_recent_attendance' => $teamsWithoutRecentAttendance,
        ];
    }

    private function dashboardRecentActivity(): array
    {
        $approvalItems = StudentApprovalHistory::query()
            ->with($this->studentApprovalHistoryRelations(['id', 'first_name', 'last_name']))
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (StudentApprovalHistory $history) {
                $studentName = $history->student?->user?->name ?? 'Unknown student';
                $actorName = $this->studentApprovalActorName($history);
                $decision = ucfirst((string) $history->decision);

                return [
                    'id' => 'approval-' . $history->id,
                    'type' => 'approval',
                    'title' => $decision . ' account approval',
                    'description' => $actorName . ' marked ' . $studentName . ' as ' . strtolower($decision) . '.',
                    'happened_at' => optional($history->created_at)?->toIso8601String(),
                ];
            });

        $academicItems = AcademicEligibilityEvaluation::query()
            ->with([
                'student.user:id,first_name,last_name',
                'evaluator:id,first_name,last_name',
            ])
            ->whereNotNull('evaluated_at')
            ->latest('evaluated_at')
            ->limit(12)
            ->get()
            ->map(function (AcademicEligibilityEvaluation $evaluation) {
                $studentName = $evaluation->student?->user?->name ?? 'Unknown student';
                $actorName = $evaluation->evaluator?->name ?? 'System';
                $status = str_replace('_', ' ', (string) ($evaluation->final_status ?? $evaluation->status ?? 'pending_review'));

                return [
                    'id' => 'academic-' . $evaluation->id,
                    'type' => 'academic',
                    'title' => 'Academic evaluation updated',
                    'description' => $actorName . ' marked ' . $studentName . ' as ' . ucfirst($status) . '.',
                    'happened_at' => optional($evaluation->evaluated_at)?->toIso8601String(),
                ];
            });

        $playerRosterItems = DB::table('team_players as tp')
            ->join('students as s', 's.id', '=', 'tp.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->join('teams as t', 't.id', '=', 'tp.team_id')
            ->whereNotNull('tp.created_at')
            ->orderByDesc('tp.created_at')
            ->limit(12)
            ->get([
                'tp.id',
                'tp.created_at',
                't.team_name',
                'su.first_name',
                'su.last_name',
            ])
            ->map(function ($row) {
                $studentName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));

                return [
                    'id' => 'roster-player-' . $row->id,
                    'type' => 'roster',
                    'title' => 'Player added to roster',
                    'description' => $studentName . ' was added to ' . $row->team_name . '.',
                    'happened_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : null,
                ];
            });

        $coachRosterItems = DB::table('team_staff_assignments as tsa')
            ->join('coaches as c', 'c.id', '=', 'tsa.coach_id')
            ->join('users as cu', 'cu.id', '=', 'c.user_id')
            ->join('teams as t', 't.id', '=', 'tsa.team_id')
            ->whereNotNull('tsa.created_at')
            ->orderByDesc('tsa.created_at')
            ->limit(12)
            ->get([
                'tsa.id',
                'tsa.created_at',
                'tsa.role',
                't.team_name',
                'cu.first_name',
                'cu.last_name',
            ])
            ->map(function ($row) {
                $coachName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                $role = ucfirst(str_replace('_', ' ', (string) $row->role));

                return [
                    'id' => 'roster-coach-' . $row->id,
                    'type' => 'roster',
                    'title' => 'Coach assignment updated',
                    'description' => $coachName . ' was assigned as ' . $role . ' for ' . $row->team_name . '.',
                    'happened_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : null,
                ];
            });

        $attendanceItems = DB::table('schedule_attendances as sa')
            ->join('users as actor', 'actor.id', '=', 'sa.recorded_by')
            ->leftJoin('students as s', 's.id', '=', 'sa.student_id')
            ->leftJoin('users as su', 'su.id', '=', 's.user_id')
            ->leftJoin('team_schedules as ts', 'ts.id', '=', 'sa.schedule_id')
            ->whereNotNull('sa.recorded_by')
            ->orderByDesc(DB::raw('COALESCE(sa.recorded_at, sa.updated_at, sa.created_at)'))
            ->limit(12)
            ->get([
                'sa.id',
                'sa.status',
                'ts.title',
                'su.first_name',
                'su.last_name',
                'actor.first_name as actor_first_name',
                'actor.last_name as actor_last_name',
                DB::raw('COALESCE(sa.recorded_at, sa.updated_at, sa.created_at) as happened_at'),
            ])
            ->map(function ($row) {
                $studentName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                $actorName = trim(($row->actor_first_name ?? '') . ' ' . ($row->actor_last_name ?? ''));
                $status = ucfirst((string) $row->status);

                return [
                    'id' => 'attendance-' . $row->id,
                    'type' => 'attendance',
                    'title' => 'Attendance posted',
                    'description' => $actorName . ' recorded ' . $status . ' for ' . $studentName . ($row->title ? ' in ' . $row->title . '.' : '.'),
                    'happened_at' => $row->happened_at ? Carbon::parse($row->happened_at)->toIso8601String() : null,
                ];
            });

        return $approvalItems
            ->concat($academicItems)
            ->concat($playerRosterItems)
            ->concat($coachRosterItems)
            ->concat($attendanceItems)
            ->filter(fn ($item) => !empty($item['happened_at']))
            ->sortByDesc('happened_at')
            ->take(8)
            ->values()
            ->all();
    }

    private function attendanceHeatmap(CarbonInterface $start, CarbonInterface $end): array
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            $dayExpr = 'EXTRACT(DOW FROM ts.start_time) + 1';
            $hourExpr = 'EXTRACT(HOUR FROM ts.start_time)';
        } elseif ($driver === 'sqlite') {
            $dayExpr = "CAST(strftime('%w', ts.start_time) AS INTEGER) + 1";
            $hourExpr = "CAST(strftime('%H', ts.start_time) AS INTEGER)";
        } else {
            $dayExpr = 'DAYOFWEEK(ts.start_time)';
            $hourExpr = 'HOUR(ts.start_time)';
        }

        $rows = DB::table('team_schedules as ts')
            ->join('team_players as tp', 'tp.team_id', '=', 'ts.team_id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereBetween('ts.start_time', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw("{$dayExpr} as day_index")
            ->selectRaw("{$hourExpr} as hour_key")
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupByRaw("{$dayExpr}, {$hourExpr}")
            ->get();

        $hours = range(6, 21);
        $dayMap = [2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat', 1 => 'Sun'];

        $cells = collect($rows)->map(fn ($row) => [
            'day' => $dayMap[(int) $row->day_index] ?? 'Mon',
            'hour' => (int) $row->hour_key,
            'late' => (int) $row->late_count,
            'no_response' => (int) $row->no_response_count,
            'value' => (int) $row->late_count + (int) $row->no_response_count,
        ])->values();

        return [
            'days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'hours' => $hours,
            'cells' => $cells,
        ];
    }

    private function todaySchedules(): array
    {
        return DB::table('team_schedules as ts')
            ->join('teams as t', 't.id', '=', 'ts.team_id')
            ->join('team_players as tp', 'tp.team_id', '=', 't.id')
            ->leftJoin('schedule_attendances as sa', function ($join) {
                $join->on('sa.schedule_id', '=', 'ts.id')
                    ->on('sa.student_id', '=', 'tp.student_id');
            })
            ->whereDate('ts.start_time', now()->toDateString())
            ->select([
                'ts.id',
                'ts.title',
                't.team_name',
                'ts.start_time',
            ])
            ->selectRaw('COUNT(tp.id) as roster_total')
            ->selectRaw("SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw('SUM(CASE WHEN sa.id IS NULL THEN 1 ELSE 0 END) as no_response_count')
            ->groupBy('ts.id', 'ts.title', 't.team_name', 'ts.start_time')
            ->orderBy('ts.start_time')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'team_name' => $row->team_name,
                'start_time' => Carbon::parse($row->start_time)->format('M j, g:i A'),
                'roster_total' => (int) $row->roster_total,
                'late' => (int) $row->late_count,
                'absent' => (int) $row->absent_count,
                'no_response' => (int) $row->no_response_count,
            ])
            ->values()
            ->all();
    }

    private function needsAttentionQueue(): array
    {
        $academic = DB::table('academic_eligibility_evaluations as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->whereIn('e.final_status', ['pending_review', 'ineligible'])
            ->orderByRaw("CASE WHEN e.final_status = 'ineligible' THEN 0 ELSE 1 END")
            ->orderByDesc('e.evaluated_at')
            ->limit(4)
            ->get([
                'su.first_name',
                'su.last_name',
                'e.gpa',
            ])
            ->map(fn ($row) => [
                'type' => 'academic',
                'title' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'subtitle' => strtoupper(\App\Models\AcademicEligibilityEvaluation::statusForGpa(
                    $row->gpa !== null ? (float) $row->gpa : null
                ) ?? 'pending') . ' academic status',
                'action_label' => 'Evaluate',
                'action_url' => '/academics',
                'priority' => \App\Models\AcademicEligibilityEvaluation::statusForGpa(
                    $row->gpa !== null ? (float) $row->gpa : null
                ) === 'ineligible' ? 95 : 85,
            ]);

        return $academic
            ->sortByDesc('priority')
            ->take(10)
            ->values()
            ->all();
    }

    private function recentActivityLog(): array
    {
        $roleScope = ['student', 'student-athlete', 'coach'];

        $attendance = DB::table('schedule_attendances as sa')
            ->join('users as actor', 'actor.id', '=', 'sa.recorded_by')
            ->leftJoin('students as st', 'st.id', '=', 'sa.student_id')
            ->leftJoin('users as su', 'su.id', '=', 'st.user_id')
            ->leftJoin('team_schedules as ts', 'ts.id', '=', 'sa.schedule_id')
            ->whereIn('actor.role', $roleScope)
            ->whereNotNull('sa.recorded_by')
            ->select([
                'sa.id as source_id',
                'actor.id as actor_id',
                DB::raw("TRIM(CONCAT(COALESCE(actor.first_name, ''), ' ', COALESCE(actor.last_name, ''))) as actor_name"),
                'actor.role as actor_role',
                DB::raw("'attendance' as action_type"),
                DB::raw("CONCAT('Recorded ', COALESCE(sa.status, 'attendance'), ' for ', COALESCE(su.first_name, ''), ' ', COALESCE(su.last_name, ''), CASE WHEN ts.title IS NOT NULL THEN CONCAT(' (', ts.title, ')') ELSE '' END) as description"),
                DB::raw('COALESCE(sa.recorded_at, sa.updated_at, sa.created_at) as happened_at'),
            ])
            ->limit(80)
            ->get();

        $academics = DB::table('academic_documents as ad')
            ->leftJoin('academic_document_types as adt', 'adt.id', '=', 'ad.document_type_id')
            ->join('users as actor', 'actor.id', '=', 'ad.uploaded_by')
            ->leftJoin('students as st', 'st.id', '=', 'ad.student_id')
            ->leftJoin('users as su', 'su.id', '=', 'st.user_id')
            ->whereIn('actor.role', $roleScope)
            ->whereNotNull('ad.uploaded_by')
            ->select([
                'ad.id as source_id',
                'actor.id as actor_id',
                DB::raw("TRIM(CONCAT(COALESCE(actor.first_name, ''), ' ', COALESCE(actor.last_name, ''))) as actor_name"),
                'actor.role as actor_role',
                DB::raw("'academics' as action_type"),
                DB::raw("CONCAT('Uploaded ', REPLACE(COALESCE(adt.code, ''), '_', ' '), ' for ', COALESCE(su.first_name, ''), ' ', COALESCE(su.last_name, '')) as description"),
                DB::raw('COALESCE(ad.uploaded_at, ad.updated_at, ad.created_at) as happened_at'),
            ])
            ->limit(80)
            ->get();

        $combined = $attendance
            ->concat($academics)
            ->filter(fn ($row) => !empty($row->happened_at))
            ->sortByDesc('happened_at')
            ->take(60)
            ->values();

        $byRole = [
            'students' => $combined->filter(fn ($row) => in_array((string) $row->actor_role, ['student', 'student-athlete'], true))->count(),
            'coaches' => $combined->where('actor_role', 'coach')->count(),
        ];

        return [
            'items' => $combined->map(function ($row) {
                return [
                    'id' => (string) $row->action_type . '-' . (string) $row->source_id,
                    'actor_id' => (int) $row->actor_id,
                    'actor_name' => (string) $row->actor_name,
                    'actor_role' => (string) $row->actor_role,
                    'action_type' => (string) $row->action_type,
                    'description' => (string) $row->description,
                    'happened_at' => Carbon::parse($row->happened_at)->toIso8601String(),
                ];
            })->all(),
            'summary' => [
                'total' => $combined->count(),
                'students' => $byRole['students'],
                'coaches' => $byRole['coaches'],
            ],
        ];
    }

    private function buildActionCenter(array $needsAttentionQueue, array $activityLog, array $todaySchedules): array
    {
        $today = now()->toDateString();
        $openPeriod = DB::table('academic_periods')
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->orderByDesc('starts_on')
            ->first(['id', 'school_year', 'term', 'ends_on']);

        $academicAlerts = collect();

        if ($openPeriod) {
            $missingSubmissions = DB::table('team_players as tp')
                ->join('students as s', 's.id', '=', 'tp.student_id')
                ->join('users as su', 'su.id', '=', 's.user_id')
                ->leftJoin('academic_documents as ad', function ($join) use ($openPeriod) {
                    $join->on('ad.student_id', '=', 's.id')
                        ->where('ad.academic_period_id', '=', $openPeriod->id);
                })
                ->whereNull('ad.id')
                ->select([
                    's.id as student_id',
                    'su.first_name',
                    'su.last_name',
                ])
                ->distinct()
                ->limit(2)
                ->get()
                ->map(fn ($row) => [
                    'id' => 'academic-missing-' . $row->student_id,
                    'title' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                    'subtitle' => 'Missing academic submission for the active period',
                    'meta' => trim(($openPeriod->school_year ?? '') . ' ' . ($openPeriod->term ?? '')),
                    'urgency' => 'high',
                    'action_label' => 'Open Academics',
                    'action_url' => '/academics/submissions',
                ]);

            $academicAlerts = $academicAlerts->concat($missingSubmissions);
        }

        $evaluatedAlerts = DB::table('academic_eligibility_evaluations as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->when($openPeriod, fn ($query) => $query->where('e.academic_period_id', $openPeriod->id))
            ->whereIn('e.final_status', ['pending_review', 'ineligible'])
            ->orderByRaw("CASE WHEN e.final_status = 'ineligible' THEN 0 ELSE 1 END")
            ->orderByDesc('e.evaluated_at')
            ->limit(3)
            ->get([
                's.id as student_id',
                'su.first_name',
                'su.last_name',
                'e.gpa',
                'e.evaluated_at',
            ])
            ->map(fn ($row) => [
                'id' => 'academic-risk-' . $row->student_id,
                'title' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'subtitle' => strtoupper(\App\Models\AcademicEligibilityEvaluation::statusForGpa(
                    $row->gpa !== null ? (float) $row->gpa : null
                ) ?? 'PENDING') . ' academic status',
                'meta' => $row->evaluated_at ? Carbon::parse($row->evaluated_at)->diffForHumans() : null,
                'urgency' => \App\Models\AcademicEligibilityEvaluation::statusForGpa(
                    $row->gpa !== null ? (float) $row->gpa : null
                ) === 'ineligible' ? 'critical' : 'high',
                'action_label' => 'Evaluate',
                'action_url' => '/academics',
            ]);

        $academicAlerts = $academicAlerts
            ->concat($evaluatedAlerts)
            ->unique('id')
            ->take(4)
            ->values();

        $attendanceAlerts = collect($todaySchedules)
            ->filter(fn ($item) => (int) ($item['late'] ?? 0) > 0 || (int) ($item['absent'] ?? 0) > 0 || (int) ($item['no_response'] ?? 0) > 0)
            ->map(function ($item) {
                $issueParts = [];
                if ((int) ($item['no_response'] ?? 0) > 0) {
                    $issueParts[] = $item['no_response'] . ' no response';
                }
                if ((int) ($item['absent'] ?? 0) > 0) {
                    $issueParts[] = $item['absent'] . ' absent';
                }
                if ((int) ($item['late'] ?? 0) > 0) {
                    $issueParts[] = $item['late'] . ' late';
                }

                return [
                    'id' => 'attendance-' . $item['id'],
                    'title' => $item['title'],
                    'subtitle' => $item['team_name'] . ' has attendance exceptions',
                    'meta' => implode(' • ', $issueParts),
                    'urgency' => ((int) ($item['no_response'] ?? 0) >= 3 || (int) ($item['absent'] ?? 0) >= 3) ? 'high' : 'medium',
                    'action_label' => 'Open Attendance',
                    'action_url' => '/operations?tab=attendance',
                ];
            })
            ->take(4)
            ->values();

        $teamAlerts = Team::query()
            ->whereNull('archived_at')
            ->where(function ($query) {
                $query->whereDoesntHave('headCoachAssignment')
                    ->orWhereDoesntHave('players');
            })
            ->with('headCoachAssignment')
            ->withCount('players')
            ->orderBy('team_name')
            ->limit(3)
            ->get(['id', 'team_name', 'year'])
            ->map(function (Team $team) {
                $subtitle = $team->coach_id
                    ? 'Team roster still needs athlete assignments'
                    : 'Team has no assigned head coach';

                return [
                    'id' => 'team-' . $team->id,
                    'title' => $team->team_name,
                    'subtitle' => $subtitle,
                    'meta' => $team->year ? 'Year ' . $team->year : null,
                    'urgency' => $team->coach_id ? 'medium' : 'high',
                    'action_label' => $team->coach_id ? 'Open Team' : 'Assign Coach',
                    'action_url' => '/teams',
                ];
            });

        $teamAlerts = $teamAlerts
            ->take(4)
            ->values();

        $systemNotices = collect();

        if ($openPeriod && !empty($openPeriod->ends_on)) {
            $daysLeft = now()->diffInDays(Carbon::parse($openPeriod->ends_on), false);
            if ($daysLeft >= 0 && $daysLeft <= 14) {
                $systemNotices->push([
                    'id' => 'system-period-' . $openPeriod->id,
                    'title' => 'Academic period deadline approaching',
                    'subtitle' => 'Submission window closes soon for the active academic period',
                    'meta' => Carbon::parse($openPeriod->ends_on)->toFormattedDateString(),
                    'urgency' => 'medium',
                    'action_label' => 'Open Academics',
                    'action_url' => '/academics',
                ]);
            }
        }

        $systemNotices = $systemNotices
            ->concat(collect($todaySchedules)->take(2)->map(fn ($item) => [
                'id' => 'system-schedule-' . $item['id'],
                'title' => $item['title'],
                'subtitle' => 'Scheduled today for ' . $item['team_name'],
                'meta' => $item['start_time'],
                'urgency' => 'medium',
                'action_label' => 'Open Calendar',
                'action_url' => '/operations?tab=calendar',
            ]))
            ->take(3)
            ->values();

        $groups = collect([
            [
                'key' => 'academic_alerts',
                'title' => 'Academic Alerts',
                'description' => 'Missing submissions and at-risk athlete evaluations.',
                'count' => $academicAlerts->count(),
                'action_label' => 'Open Academics',
                'action_url' => '/academics',
                'tone' => 'amber',
                'items' => $academicAlerts->all(),
            ],
            [
                'key' => 'attendance_exceptions',
                'title' => 'Attendance Follow-Up',
                'description' => 'Late, absent, no-response, and unresolved attendance issues from active schedules.',
                'count' => $attendanceAlerts->count(),
                'action_label' => 'Open Attendance',
                'action_url' => '/operations?tab=attendance',
                'tone' => 'blue',
                'items' => $attendanceAlerts->all(),
            ],
            [
                'key' => 'team_alerts',
                'title' => 'Team Alerts',
                'description' => 'Assignment gaps and team requests needing attention.',
                'count' => $teamAlerts->count(),
                'action_label' => 'Open Teams',
                'action_url' => '/teams',
                'tone' => 'emerald',
                'items' => $teamAlerts->all(),
            ],
            [
                'key' => 'system_notices',
                'title' => 'System Notices',
                'description' => 'Upcoming deadlines and today’s operational reminders.',
                'count' => $systemNotices->count(),
                'action_label' => 'Open Operations',
                'action_url' => '/operations',
                'tone' => 'slate',
                'items' => $systemNotices->all(),
            ],
        ])->values();

        $allIssues = $groups->flatMap(fn ($group) => $group['items']);
        $criticalCount = $allIssues->where('urgency', 'critical')->count();
        $highCount = $allIssues->where('urgency', 'high')->count();

        return [
            'summary' => [
                'open_issues' => $allIssues->count(),
                'critical' => $criticalCount,
                'due_today' => count($todaySchedules),
                'pending_review' => $groups->where('key', 'academic_alerts')->sum('count'),
            ],
            'groups' => $groups->all(),
            'recent_activity' => [
                'items' => array_slice($activityLog['items'] ?? [], 0, 8),
                'summary' => $activityLog['summary'] ?? [
                    'total' => 0,
                    'students' => 0,
                    'coaches' => 0,
                ],
            ],
            'high_priority_count' => $criticalCount + $highCount,
            'source_count' => count($needsAttentionQueue),
        ];
    }

    private function pendingStudentApprovalUsers()
    {
        return User::query()
            ->whereIn('role', ['student-athlete', 'student'])
            ->whereHas('student', fn ($query) => $query->where('approval_status', 'pending'));
    }

    private function studentApprovalHistoryRelations(array $userColumns): array
    {
        $columns = array_values(array_unique(array_merge(['id'], $userColumns)));
        $relations = [
            'student.user:' . implode(',', $columns),
        ];

        if (Schema::hasColumn('student_approval_histories', 'admin_id')) {
            $relations[] = 'admin:' . implode(',', $columns);
        }

        if (Schema::hasColumn('student_approval_histories', 'coach_id')) {
            $relations[] = 'coach.user:' . implode(',', $columns);
        }

        return $relations;
    }

    private function studentApprovalActorName(StudentApprovalHistory $history): string
    {
        return $history->admin?->name
            ?? $history->coach?->user?->name
            ?? 'System';
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

    private function rejectedStudentApprovalUsers()
    {
        return User::query()
            ->whereIn('role', ['student-athlete', 'student'])
            ->whereHas('student', fn ($query) => $query->where('approval_status', 'rejected'));
    }

    private function sendAccountStatusMail(User $user, Mailable $mailable): void
    {
        $this->notifications->sendUserEmail($user, $mailable, [
            'notification_preference' => 'notify_approvals',
            'created_by' => Auth::id(),
            'context' => [
                'communication' => 'account_status',
                'user_id' => $user->id,
            ],
        ]);
    }

    private function resolveAdminInvite(string $email, string $token): ?AdminInvite
    {
        if ($email === '' || $token === '') {
            return null;
        }

        $invite = AdminInvite::query()
            ->where('email', $email)
            ->where('token_hash', hash('sha256', $token))
            ->latest('id')
            ->first();

        if (!$invite || !$invite->isUsable()) {
            return null;
        }

        return $invite;
    }
}
