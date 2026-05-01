<?php

namespace App\Http\Controllers;

use App\Models\AcademicDocument;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\UserSetting;
use App\Services\SecureUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountSettingsController extends Controller
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

    public function __construct(private SecureUploadService $secureUpload)
    {
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load(['student', 'coach']);

        return Inertia::render('Account/Profile', [
            'profile' => [
                'admin' => $user->role === 'admin' ? [
                    'role' => 'Administrator',
                    'status' => $user->account_state,
                    'capabilities' => [
                        'People approvals and account lifecycle',
                        'Team creation and roster management',
                        'Operations attendance overrides and exports',
                        'Wellness monitoring and athlete follow-up',
                        'Academic period controls and evaluations',
                    ],
                ] : null,
                'student' => $user->student ? [
                    'student_id_number' => $user->student->student_id_number,
                    'first_name' => $user->student->first_name,
                    'middle_name' => $user->student->middle_name,
                    'last_name' => $user->student->last_name,
                    'date_of_birth' => $user->student->date_of_birth ? (string) $user->student->date_of_birth : null,
                    'gender' => $user->student->gender,
                    'phone_number' => $user->student->phone_number,
                    'home_address' => $user->student->home_address,
                    'course_or_strand' => $user->student->course_or_strand,
                    'academic_level_label' => $user->student->academic_level_label,
                    'student_status' => $user->student->student_status,
                    'approval_status' => $user->student->approval_status,
                    'emergency_contact_name' => $user->student->emergency_contact_name,
                    'emergency_contact_relationship' => $user->student->emergency_contact_relationship,
                    'emergency_contact_phone' => $user->student->emergency_contact_phone,
                    'height' => $user->student->height,
                    'weight' => $user->student->weight,
                ] : null,
                'coach' => $user->coach ? [
                    'phone_number' => $user->coach->phone_number,
                    'home_address' => $user->coach->home_address,
                    'date_of_birth' => $user->coach->date_of_birth ? (string) $user->coach->date_of_birth : null,
                    'gender' => $user->coach->gender,
                ] : null,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user()->load(['student', 'coach']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'home_address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', Rule::in(self::EMERGENCY_RELATIONSHIPS)],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
        ]);

        $nameParts = $this->splitName($validated['name']);
        $updates = [
            'first_name' => $nameParts['first_name'],
            'middle_name' => $nameParts['middle_name'],
            'last_name' => $nameParts['last_name'],
        ];

        if ($request->hasFile('avatar')) {
            $newPath = $this->secureUpload->storePublic($request->file('avatar'), 'avatars', 'avatar');

            if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
                Storage::disk('public')->delete($user->avatar);
            }

            $updates['avatar'] = $newPath;
        }

        $user->update($updates);

        if (in_array($user->role, ['student', 'student-athlete'], true) && $user->student) {
            $user->student->update([
                'phone_number' => $validated['phone_number'] ?? null,
                'home_address' => $validated['home_address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            ]);
        }

        if ($user->role === 'coach' && $user->coach) {
            $user->coach->update([
                'phone_number' => $validated['phone_number'] ?? null,
                'home_address' => $validated['home_address'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
            ]);
        }

        return back();
    }

    private function splitName(string $name): array
    {
        $raw = trim($name);
        if ($raw === '') {
            return [
                'first_name' => null,
                'middle_name' => null,
                'last_name' => null,
            ];
        }

        $parts = preg_split('/\s+/', $raw) ?: [];
        $first = array_shift($parts);
        $last = count($parts) ? array_pop($parts) : null;
        $middle = count($parts) ? implode(' ', $parts) : null;

        return [
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
        ];
    }

    public function settings(Request $request)
    {
        $user = $request->user()->load(['student', 'coach']);

        return Inertia::render('Account/Settings', $this->buildSettingsPayload($user));
    }

    public function accountSettings(Request $request)
    {
        $user = $request->user()->load(['student', 'coach']);

        return Inertia::render('Account/AccountSettings', $this->buildSettingsPayload($user));
    }

    public function notifications(Request $request)
    {
        $user = $request->user()->load(['student', 'coach']);

        return Inertia::render('Account/Notifications', $this->buildSettingsPayload($user));
    }

    public function preferences(Request $request)
    {
        $user = $request->user()->load(['student', 'coach']);

        return Inertia::render('Account/Preferences', $this->buildSettingsPayload($user));
    }

    public function help(Request $request)
    {
        $user = $request->user()->load(['student', 'coach']);

        return Inertia::render('Account/Help', $this->buildSettingsPayload($user));
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'notification_email_enabled' => ['nullable', 'boolean'],
            'notify_approvals' => ['nullable', 'boolean'],
            'notify_schedule_changes' => ['nullable', 'boolean'],
            'notify_attendance_changes' => ['nullable', 'boolean'],
            'notify_wellness_alerts' => ['nullable', 'boolean'],
            'notify_academic_alerts' => ['nullable', 'boolean'],
            'notify_attendance_exceptions' => ['nullable', 'boolean'],
            'notify_wellness_injury_threshold' => ['nullable', 'boolean'],
            'wellness_injury_threshold_level' => ['required', 'integer', 'between:1,5'],
        ]);

        UserSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'notification_email_enabled' => (bool) ($validated['notification_email_enabled'] ?? false),
                'notify_approvals' => (bool) ($validated['notify_approvals'] ?? false),
                'notify_schedule_changes' => (bool) ($validated['notify_schedule_changes'] ?? false),
                'notify_attendance_changes' => (bool) ($validated['notify_attendance_changes'] ?? false),
                'notify_wellness_alerts' => (bool) ($validated['notify_wellness_alerts'] ?? false),
                'notify_academic_alerts' => (bool) ($validated['notify_academic_alerts'] ?? false),
                'notify_attendance_exceptions' => (bool) ($validated['notify_attendance_exceptions'] ?? false),
                'notify_wellness_injury_threshold' => (bool) ($validated['notify_wellness_injury_threshold'] ?? false),
                'wellness_injury_threshold_level' => (int) $validated['wellness_injury_threshold_level'],
            ]
        );

        return back();
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => [
                Rule::requiredIf(!$user->must_change_password),
                'nullable',
                'current_password',
            ],
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => false,
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function updateAccountSettings(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update([
            'email' => $validated['email'],
        ]);

        return back()->with('success', 'Email updated successfully.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $user->update([
            'account_state' => 'deactivated',
        ]);

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function buildCompliance($user): array
    {
        if ($user->role === 'admin') {
            $pendingApprovals = \App\Models\User::query()
                ->whereIn('role', ['student-athlete', 'student'])
                ->whereHas('student', fn ($query) => $query->where('approval_status', 'pending'))
                ->count();

            $latestPeriodId = AcademicPeriod::query()->orderByDesc('starts_on')->value('id');
            $atRiskAcademic = $latestPeriodId
                ? AcademicEligibilityEvaluation::query()
                    ->where('academic_period_id', $latestPeriodId)
                    ->whereNotNull('gpa')
                    ->whereIn('final_status', ['pending_review', 'ineligible'])
                    ->count()
                : 0;

            return [
                'admin' => [
                    'pending_approvals' => (int) $pendingApprovals,
                    'academic_at_risk' => (int) $atRiskAcademic,
                ],
                'student' => null,
                'coach' => null,
            ];
        }

        if (in_array($user->role, ['student', 'student-athlete'], true) && $user->student) {
            $studentId = $user->student->id;
            $latestEval = AcademicEligibilityEvaluation::query()
                ->with('academicPeriod')
                ->where('student_id', $studentId)
                ->latest('evaluated_at')
                ->first();

            $latestPeriod = AcademicPeriod::query()->orderByDesc('starts_on')->first();
            $missingLatestSubmission = $latestPeriod
                ? !AcademicDocument::query()
                    ->periodSubmission()
                    ->where('student_id', $studentId)
                    ->where('academic_period_id', $latestPeriod->id)
                    ->exists()
                : false;

            return [
                'admin' => null,
                'student' => [
                    'academic_status' => $latestEval?->status,
                    'academic_period' => $latestEval?->academicPeriod
                        ? ($latestEval->academicPeriod->school_year . ' ' . $latestEval->academicPeriod->term)
                        : null,
                    'missing_latest_submission' => (bool) $missingLatestSubmission,
                ],
                'coach' => null,
            ];
        }

        if ($user->role === 'coach' && $user->coach) {
            $teamIds = Team::query()
                ->forCoach($user->coach->id)
                ->pluck('id');

            $studentIds = TeamPlayer::query()
                ->whereIn('team_id', $teamIds)
                ->pluck('student_id')
                ->filter()
                ->unique()
                ->values();

            $latestPeriod = AcademicPeriod::query()->orderByDesc('starts_on')->first();

            $latestEvaluations = AcademicEligibilityEvaluation::query()
                ->whereIn('student_id', $studentIds)
                ->when($latestPeriod, fn ($q) => $q->where('academic_period_id', $latestPeriod->id))
                ->get()
                ->keyBy('student_id');

            $submittedInLatest = $latestPeriod
                ? AcademicDocument::query()
                    ->periodSubmission()
                    ->whereIn('student_id', $studentIds)
                    ->where('academic_period_id', $latestPeriod->id)
                    ->pluck('student_id')
                    ->unique()
                : collect();

            $missingCount = $latestPeriod ? $studentIds->diff($submittedInLatest)->count() : 0;
            $eligibleCount = $latestEvaluations->where('status', 'eligible')->count();
            $pendingReviewCount = $latestEvaluations->where('status', 'pending_review')->count();
            $ineligibleCount = $latestEvaluations->where('status', 'ineligible')->count();

            return [
                'admin' => null,
                'student' => null,
                'coach' => [
                    'tracked_students' => $studentIds->count(),
                    'latest_period' => $latestPeriod ? ($latestPeriod->school_year . ' ' . $latestPeriod->term) : null,
                    'missing_submissions_count' => (int) $missingCount,
                    'eligible_count' => (int) $eligibleCount,
                    'probation_count' => (int) $pendingReviewCount,
                    'ineligible_count' => (int) $ineligibleCount,
                ],
            ];
        }

        return [
            'admin' => null,
            'student' => null,
            'coach' => null,
        ];
    }

    private function settingsScopeForRole(string $role): array
    {
        if ($role === 'admin') {
            return [
                'notifications' => [
                    'notify_approvals',
                    'notify_attendance_exceptions',
                    'notify_wellness_alerts',
                    'notify_academic_alerts',
                    'notification_email_enabled',
                ],
                'coach_preferences' => false,
            ];
        }

        if ($role === 'coach') {
            return [
                'notifications' => [
                    'notify_schedule_changes',
                    'notify_attendance_changes',
                    'notify_wellness_alerts',
                    'notify_academic_alerts',
                    'notify_attendance_exceptions',
                    'notify_wellness_injury_threshold',
                    'notification_email_enabled',
                ],
                'coach_preferences' => true,
            ];
        }

        return [
            'notifications' => [
                'notify_schedule_changes',
                'notify_attendance_changes',
                'notify_wellness_alerts',
                'notify_academic_alerts',
                'notify_attendance_exceptions',
                'notification_email_enabled',
            ],
            'coach_preferences' => false,
        ];
    }

    private function buildSettingsPayload($user): array
    {
        $settings = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'notification_email_enabled' => true,
                'notify_approvals' => true,
                'notify_schedule_changes' => true,
                'notify_attendance_changes' => true,
                'notify_wellness_alerts' => true,
                'notify_academic_alerts' => true,
                'notify_attendance_exceptions' => true,
                'notify_wellness_injury_threshold' => true,
                'wellness_injury_threshold_level' => 3,
            ]
        );

        return [
            'settings' => [
                'notification_email_enabled' => (bool) $settings->notification_email_enabled,
                'notify_approvals' => (bool) $settings->notify_approvals,
                'notify_schedule_changes' => (bool) $settings->notify_schedule_changes,
                'notify_attendance_changes' => (bool) $settings->notify_attendance_changes,
                'notify_wellness_alerts' => (bool) $settings->notify_wellness_alerts,
                'notify_academic_alerts' => (bool) $settings->notify_academic_alerts,
                'notify_attendance_exceptions' => (bool) $settings->notify_attendance_exceptions,
                'notify_wellness_injury_threshold' => (bool) $settings->notify_wellness_injury_threshold,
                'wellness_injury_threshold_level' => (int) $settings->wellness_injury_threshold_level,
            ],
            'scope' => $this->settingsScopeForRole((string) $user->role),
            'compliance' => $this->buildCompliance($user),
        ];
    }
}
