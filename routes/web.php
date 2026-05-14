<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\CoachOnboardingController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\CreateTeamController;
use App\Http\Controllers\Admin\OperationsWorkspaceController;
use App\Http\Controllers\Admin\AcademicEligibilityController;
use App\Http\Controllers\Admin\DocumentReviewController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Coaches\CoachTeamController;
use App\Http\Controllers\Coaches\CoachApplicationsController;
use App\Http\Controllers\Coaches\CoachTeamManagementController;
use App\Http\Controllers\Coaches\TeamInviteController;
use App\Http\Controllers\StudentAthlete\TeamInviteJoinController;
use App\Http\Controllers\StudentAthlete\StudentAthleteController;
use App\Http\Controllers\Coaches\CoachScheduleController;
use App\Http\Controllers\TrainingRequirementController;
use App\Http\Controllers\Coaches\CoachDashboardController;
use App\Http\Controllers\Coaches\AcademicVisibilityController;
use App\Http\Controllers\Coaches\TeamDocumentController;
use App\Http\Controllers\StudentAthlete\ScheduleRecord;
use App\Http\Controllers\StudentAthlete\AcademicSubmissionController;
use App\Http\Controllers\StudentAthlete\StudentDocumentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\FileAccessController;
use App\Http\Controllers\ScheduleCalendarController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', function () {
    if (Auth::check()) {
        $role = Auth::user()->role;
        return redirect(match ($role) {
            'admin' => '/AdminDashboard',
            'coach' => '/coach/dashboard',
            'student-athlete', 'student' => '/StudentAthleteDashboard',
            default => '/Login',
        });
    }
    return Inertia::render('Public/Welcome');
})->name('Welcome');

Route::redirect('/about', '/#about')->name('about');
Route::redirect('/services', '/#features')->name('services');
Route::redirect('/how-it-works', '/#how-it-works')->name('how-it-works');
Route::redirect('/faq', '/#faq')->name('faq');
Route::redirect('/policies', '/#policies')->name('policies');
Route::redirect('/contact', '/#contact')->name('contact');
Route::redirect('/privacy-policy', '/#privacy-policy')->name('privacy-policy');
Route::redirect('/terms-of-use', '/#terms-of-use')->name('terms-of-use');

Route::get('/pending-approval', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->account_state === 'active' && (!$user->requiresStudentApproval() || $user->approval_status === 'approved')) {
            $role = $user->role;
            return redirect(match ($role) {
                'admin' => '/AdminDashboard',
                'coach' => '/coach/dashboard',
                'student-athlete', 'student' => '/StudentAthleteDashboard',
                default => '/Login',
            });
        }
    }
    return inertia('Status/PendingApproval');
})->name('pendingapproval');

Route::get('/rejected', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->account_state === 'active' && (!$user->requiresStudentApproval() || $user->approval_status === 'approved')) {
            $role = $user->role;
            return redirect(match ($role) {
                'admin' => '/AdminDashboard',
                'coach' => '/coach/dashboard',
                'student-athlete', 'student' => '/StudentAthleteDashboard',
                default => '/Login',
            });
        }
    }
    return inertia('Status/Rejected');
})->name('rejected');

Route::get('/deactivated', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->account_state === 'active' && (!$user->requiresStudentApproval() || $user->approval_status === 'approved')) {
            $role = $user->role;
            return redirect(match ($role) {
                'admin' => '/AdminDashboard',
                'coach' => '/coach/dashboard',
                'student-athlete', 'student' => '/StudentAthleteDashboard',
                default => '/Login',
            });
        }
    }
    return inertia('Status/Deactivated');
})->name('deactivated');

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return Inertia::render('Auth/Login');
    });
    Route::get('/Login', function () {
        return Inertia::render('Auth/Login');
    })->name('Login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])
        ->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:forgot-password')
        ->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])
        ->name('password.update');
    Route::get('/Register', [RegisterController::class, 'showStudentAthleteRegistration'])->name('Register');
    Route::redirect('/register', '/Register');
    Route::redirect('/Student-AthleteRegister', '/Register')->name('Student-AthleteRegister');
    Route::get('/RegisterStudent-AthleteData/check-student-id', [RegisterController::class, 'checkStudentIdAvailability'])
        ->name('student.register.check_id');
    Route::post('/RegisterStudent-AthleteData', [RegisterController::class, 'registerStudentAthlete']);
    Route::get('/admin/invite/accept', [AdminController::class, 'showAdminInviteAcceptance'])->name('admin.invite.accept');
    Route::post('/admin/invite/accept', [AdminController::class, 'acceptAdminInvite'])->name('admin.invite.accept.submit');

    // Coach accounts are now provisioned by admins only.
    Route::redirect('/CoachRegister', '/Register');
    Route::get('/coach/onboarding/activate', [CoachOnboardingController::class, 'show'])->name('coach.onboarding.activate');
    Route::post('/coach/onboarding/activate', [CoachOnboardingController::class, 'activate'])->name('coach.onboarding.activate.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('/join-team/{code?}', [TeamInviteJoinController::class, 'show'])
        ->name('student.team_invites.show');
    Route::post('/join-team', [TeamInviteJoinController::class, 'join'])
        ->name('student.team_invites.join');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::middleware(['auth', 'force_password_change'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->name('verification.send');
    Route::get('/schedules/{schedule}/calendar', [ScheduleCalendarController::class, 'show'])->name('schedules.calendar');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::put('/announcements/read-all', [AnnouncementController::class, 'markAllRead'])->name('announcements.readAll');
    Route::put('/announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])->name('announcements.read');
    Route::get('/files/academic/{document}', [FileAccessController::class, 'academic'])->name('files.academic');
    Route::get('/files/documents/{document}', [FileAccessController::class, 'document'])->name('files.documents.show');
    Route::get('/account/profile', [AccountSettingsController::class, 'profile'])->name('account.profile.show');
    Route::put('/account/profile', [AccountSettingsController::class, 'updateProfile'])->name('account.profile.update');
    Route::get('/account/settings', [AccountSettingsController::class, 'settings'])->name('account.settings.show');
    Route::get('/account/account-settings', [AccountSettingsController::class, 'accountSettings'])->name('account.account-settings.show');
    Route::get('/account/notifications', [AccountSettingsController::class, 'notifications'])->name('account.notifications.show');
    Route::get('/account/preferences', [AccountSettingsController::class, 'preferences'])->name('account.preferences.show');
    Route::get('/account/help', [AccountSettingsController::class, 'help'])->name('account.help.show');
    Route::put('/account/account-settings', [AccountSettingsController::class, 'updateAccountSettings'])->name('account.account-settings.update');
    Route::put('/account/settings', [AccountSettingsController::class, 'updateSettings'])->name('account.settings.update');
    Route::put('/account/password', [AccountSettingsController::class, 'updatePassword'])->name('account.password.update');
    Route::delete('/account/delete', [AccountSettingsController::class, 'destroy'])->name('account.destroy');

    // Legacy paths kept for backwards compatibility.
    Route::redirect('/Announcements', '/announcements');
    Route::redirect('/Account/Profile', '/account/profile');
    Route::redirect('/Account/Settings', '/account/settings');
    Route::redirect('/Account/AccountSettings', '/account/account-settings');
    Route::redirect('/Account/Notifications', '/account/notifications');
    Route::redirect('/Account/Preferences', '/account/preferences');
    Route::redirect('/Account/Help', '/account/help');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/AdminDashboard', [AdminController::class, 'dashboard'])
        ->name('AdminDashboard');

    Route::post('/admin/users/{user}/deactivate', [AdminController::class, 'deactivate'])->name('admin.users.deactivate');
    Route::post('/admin/users/{user}/reactivate', [AdminController::class, 'reactivate'])->name('admin.users.reactivate');

    Route::get('/people', [AdminController::class, 'userManagement'])
        ->name('admin.people.index');
    Route::get('/people/{user}', [AdminController::class, 'showUserProfile'])
        ->name('admin.people.show');
    Route::post('/admin/invites', [AdminController::class, 'storeAdminInvite'])
        ->name('admin.invites.store');
    Route::post('/admin/coaches', [AdminController::class, 'storeCoach'])
        ->name('admin.coaches.store');
    Route::post('/admin/coaches/{user}/activation-link', [AdminController::class, 'sendCoachActivationLink'])
        ->name('admin.coaches.activation-link');
    Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser'])
        ->name('admin.users.destroy');

    Route::get('/teams', [CreateTeamController::class, 'teamSetup'])
        ->name('admin.teams.index');
    Route::redirect('/teams/archived', '/teams')
        ->name('admin.teams.archived');
    Route::get('/teams/{team}/view-roster', [CreateTeamController::class, 'showRosterPage'])
        ->name('admin.teams.roster.page');
    Route::get('/teams/{team}/manage-coaches', [CreateTeamController::class, 'showCoachManagerPage'])
        ->name('admin.teams.coaches.page');
    Route::get('/teams/{team}/manage-players', [CreateTeamController::class, 'showPlayerManagerPage'])
        ->name('admin.teams.players.page');
    Route::get('/teams/{team}/roster', [CreateTeamController::class, 'roster'])
        ->name('admin.teams.roster');
    Route::get('/teams/{team}/print', [CreateTeamController::class, 'printRoster'])
        ->name('admin.teams.print');
    Route::post('/teams/{team}/archive', [CreateTeamController::class, 'archive'])
        ->name('admin.teams.archive');
    Route::post('/teams/{team}/reactivate', [CreateTeamController::class, 'reactivate'])
        ->name('admin.teams.reactivate');

    Route::get('/operations', [OperationsWorkspaceController::class, 'index'])
        ->name('admin.operations.index');
    Route::get('/operations/attendance/records', [OperationsWorkspaceController::class, 'attendanceRecords'])
        ->name('admin.operations.attendance.records');
    Route::put('/operations/attendance/{schedule}/{student}', [OperationsWorkspaceController::class, 'updateAttendance'])
        ->name('admin.operations.attendance.update');
    Route::get('/operations/attendance/print', [OperationsWorkspaceController::class, 'printAttendance'])
        ->name('admin.operations.attendance.print');
    Route::get('/operations/schedules/print', [OperationsWorkspaceController::class, 'printSchedules'])
        ->name('admin.operations.schedules.print');
    Route::get('/operations/schedules/{schedule}/drilldown', [OperationsWorkspaceController::class, 'scheduleDrilldown'])
        ->name('admin.operations.schedules.drilldown');
    Route::redirect('/operations/attendance', '/operations?tab=attendance')
        ->name('admin.operations.attendance');

    Route::get('/academics', [AcademicEligibilityController::class, 'index'])
        ->name('admin.academics.index');
    Route::get('/documents', [DocumentReviewController::class, 'index'])
        ->name('admin.documents.index');
    Route::put('/documents/{document}/review', [DocumentReviewController::class, 'markReviewed'])
        ->name('admin.documents.review');
    Route::get('/audit-trail', [AdminController::class, 'auditTrail'])
        ->name('admin.audit-trail.index');
    Route::redirect('/reports', '/reports/attendance')
        ->name('admin.reports.index');
    Route::get('/reports/attendance', [ReportsController::class, 'attendance'])
        ->name('admin.reports.attendance');
    Route::get('/reports/roster', [ReportsController::class, 'roster'])
        ->name('admin.reports.roster');
    Route::get('/reports/academics', [ReportsController::class, 'academics'])
        ->name('admin.reports.academics');
    Route::get('/reports/attendance/export.csv', [ReportsController::class, 'exportAttendanceCsv'])
        ->name('admin.reports.attendance.csv');
    Route::get('/reports/attendance/print', [ReportsController::class, 'printAttendanceSummary'])
        ->name('admin.reports.attendance.print');
    Route::get('/reports/roster/export.csv', [ReportsController::class, 'exportRosterCsv'])
        ->name('admin.reports.roster.csv');
    Route::get('/reports/roster/print', [ReportsController::class, 'printRosterSummary'])
        ->name('admin.reports.roster.print');
    Route::get('/reports/academics/export.csv', [ReportsController::class, 'exportAcademicCsv'])
        ->name('admin.reports.academics.csv');
    Route::get('/reports/academics/print', [ReportsController::class, 'printAcademicSummary'])
        ->name('admin.reports.academics.print');
    Route::get('/academics/evaluations', [AcademicEligibilityController::class, 'evaluations'])
        ->name('admin.academics.evaluations');
    Route::get('/academics/past-periods', [AcademicEligibilityController::class, 'pastPeriods'])
        ->name('admin.academics.past-periods');
    Route::post('/academics/periods', [AcademicEligibilityController::class, 'storePeriod'])
        ->name('academic.periods.store');
    Route::put('/academics/periods/{period}/status', [AcademicEligibilityController::class, 'updateStatus'])
        ->name('academic.periods.status');
    Route::delete('/academics/periods/{period}', [AcademicEligibilityController::class, 'destroyPeriod'])
        ->name('academic.periods.destroy');
    Route::post('/academics/evaluate', [AcademicEligibilityController::class, 'evaluate'])
        ->name('academic.evaluate');
    Route::get('/academics/submissions/records', [AcademicEligibilityController::class, 'submissionsRecords'])
        ->name('academic.submissions.records');
    Route::get('/academics/evaluations/records', [AcademicEligibilityController::class, 'evaluationsRecords'])
        ->name('academic.evaluations.records');
    Route::get('/academics/exceptions', [AcademicEligibilityController::class, 'exceptions'])
        ->name('academic.exceptions');
    Route::put('/academics/evaluations/{student}/{period}', [AcademicEligibilityController::class, 'updateEvaluation'])
        ->name('academic.evaluations.update');
    Route::delete('/academics/documents/{document}', [AcademicEligibilityController::class, 'destroyDocument'])
        ->name('academic.documents.destroy');
    // Legacy admin routes kept for backwards compatibility.
    Route::redirect('/ApprovalManagement', '/people');
    Route::redirect('/UserManagement', '/people');
    Route::redirect('/TeamSetup', '/teams');
    Route::redirect('/CreateTeam', '/teams');
    Route::redirect('/ScheduleOverview', '/operations');
    Route::redirect('/AttendanceInsights', '/operations?tab=attendance');
    Route::redirect('/AcademicEligibility', '/academics');
    Route::post('/AcademicEligibility/periods', [AcademicEligibilityController::class, 'storePeriod']);
    Route::put('/AcademicEligibility/periods/{period}/window', [AcademicEligibilityController::class, 'toggleWindow']);
    Route::put('/AcademicEligibility/periods/{period}/lock', [AcademicEligibilityController::class, 'toggleLock']);
    Route::post('/AcademicEligibility/evaluate', [AcademicEligibilityController::class, 'evaluate']);
    Route::get('/AcademicEligibility/submissions/records', [AcademicEligibilityController::class, 'submissionsRecords']);
    Route::get('/AcademicEligibility/evaluations/records', [AcademicEligibilityController::class, 'evaluationsRecords']);
    Route::redirect('/AcademicEligibility/evaluations', '/academics/evaluations');
    Route::get('/AcademicEligibility/exceptions', [AcademicEligibilityController::class, 'exceptions']);
    Route::put('/AcademicEligibility/evaluations/{student}/{period}', [AcademicEligibilityController::class, 'updateEvaluation']);
    // CSV export removed in favor of print-only reports.
});

Route::middleware(['auth', 'role:coach'])->group(function () {
    Route::get('/coach/dashboard', [CoachDashboardController::class, 'index'])
        ->name('coach.dashboard.index');

    Route::get('/coach/applications', [CoachApplicationsController::class, 'index'])
        ->name('coach.applications.index');
    Route::post('/coach/applications/{user}/approve', [CoachApplicationsController::class, 'approve'])
        ->name('coach.applications.approve');
    Route::post('/coach/applications/{user}/reject', [CoachApplicationsController::class, 'reject'])
        ->name('coach.applications.reject');

    Route::get('/coach/operations', function (Request $request) {
        $query = $request->query();
        $tab = (string) ($query['tab'] ?? 'attendance');

        unset($query['tab'], $query['attendance_schedule_id'], $query['attendance_page'], $query['wellness_schedule_id']);

        return redirect()->route('coach.schedule.index', $query);
    })->name('coach.operations.index');
    Route::get('/coach/team', [CoachTeamController::class, 'index'])->name('coach.team.index');
    Route::get('/coach/teams/manage', [CoachTeamManagementController::class, 'index'])
        ->name('coach.teams.manage.index');
    Route::post('/coach/teams', [CoachTeamManagementController::class, 'store'])
        ->name('coach.teams.store');
    Route::put('/coach/teams/{team}', [CoachTeamManagementController::class, 'update'])
        ->name('coach.teams.update');
    Route::put('/coach/teams/{team}/roster', [CoachTeamManagementController::class, 'updateRoster'])
        ->name('coach.teams.roster.update');
    Route::post('/coach/teams/{team}/invite-code', [TeamInviteController::class, 'generate'])
        ->name('coach.teams.invite.generate');
    Route::post('/coach/teams/{team}/invite-code/regenerate', [TeamInviteController::class, 'regenerate'])
        ->name('coach.teams.invite.regenerate');
    Route::delete('/coach/teams/{team}/invite-code', [TeamInviteController::class, 'disable'])
        ->name('coach.teams.invite.disable');
    Route::post('/coach/teams/{team}/assistant-coaches/{assistantCoach}', [CoachTeamManagementController::class, 'assignAssistantCoach'])
        ->name('coach.teams.assistant.assign');
    Route::delete('/coach/teams/{team}/assistant-coach', [CoachTeamManagementController::class, 'removeAssistantCoach'])
        ->name('coach.teams.assistant.remove');
    Route::post('/coach/teams/{team}/archive', [CoachTeamManagementController::class, 'archive'])
        ->name('coach.teams.archive');
    Route::post('/coach/teams/{team}/reactivate', [CoachTeamManagementController::class, 'reactivate'])
        ->name('coach.teams.reactivate');
    Route::get('/coach/team/print', [CoachTeamController::class, 'printRoster'])
        ->name('coach.team.print');
    Route::put('/coach/team-players/{teamPlayer}/position', [CoachTeamController::class, 'updatePlayerPosition'])
        ->name('coach.team_players.position');
    Route::put('/coach/team-players/{teamPlayer}/status', [CoachTeamController::class, 'updatePlayerStatus'])
        ->name('coach.team_players.status');
    Route::get('/coach/schedule', [CoachScheduleController::class, 'index'])
        ->name('coach.schedule.index');
    Route::get('/coach/schedule/print', [CoachScheduleController::class, 'print'])
        ->name('coach.schedule.print');
    Route::get('/coach/attendance', function () {
        return redirect('/coach/schedule');
    })->name('coach.attendance.index');
    Route::get('/coach/schedules/{schedule}/attendance-roster', [CoachScheduleController::class, 'roster'])
        ->name('coach.attendance.roster');
    Route::post('/coach/schedules/{schedule}/attendance/bulk', [CoachScheduleController::class, 'bulkAttendance'])
        ->name('coach.attendance.bulk');
    Route::post('/coach/schedules/{schedule}/training-requirements', [TrainingRequirementController::class, 'store'])
        ->name('training-requirements.store');
    Route::delete('/coach/schedules/{schedule}/training-requirements/{trainingRequirement}', [TrainingRequirementController::class, 'destroy'])
        ->name('training-requirements.destroy');
    Route::get('/coach/academics', [AcademicVisibilityController::class, 'index'])
        ->name('coach.academics.index');
    Route::get('/coach/documents', [TeamDocumentController::class, 'index'])
        ->name('coach.documents.index');

    Route::post('/coach/schedules', [CoachScheduleController::class, 'store'])
        ->name('coach.schedules.store');

    Route::put('/coach/schedules/{id}', [CoachScheduleController::class, 'update'])
        ->name('coach.schedules.update');
    Route::delete('/coach/schedules/{id}', [CoachScheduleController::class, 'destroy'])
        ->name('coach.schedules.destroy');

    // Legacy paths kept for backwards compatibility.
    Route::redirect('/CoachDashboard', '/coach/dashboard');
    Route::redirect('/CoachTeam', '/coach/team');
    Route::redirect('/CoachSchedule', '/coach/schedule');
    Route::redirect('/AttendanceRecord', '/coach/schedule');
    Route::redirect('/CoachAcademicVisibility', '/coach/documents');
});

Route::middleware(['auth', 'role:coach,admin'])->group(function () {
    Route::get('/schedules/{schedule}/training-requirements', [TrainingRequirementController::class, 'show'])
        ->name('training-requirements.show');
    Route::get('/schedules/{schedule}/training-requirements/print', [TrainingRequirementController::class, 'print'])
        ->name('training-requirements.print');
});

Route::middleware(['auth', 'role:student-athlete,student'])->group(function () {
    Route::get('/documents/my', [StudentDocumentController::class, 'index'])->name('student.documents.index');
    Route::post('/documents/my', [StudentDocumentController::class, 'store'])->name('student.documents.store');
    Route::get('/AcademicSubmissions', [AcademicSubmissionController::class, 'index'])->name('AcademicSubmissions');
    Route::get('/AcademicSubmissions/new', [AcademicSubmissionController::class, 'create'])->name('AcademicSubmissions.create');
    Route::get('/AcademicSubmissions/print', [AcademicSubmissionController::class, 'print'])->name('AcademicSubmissions.print');
    Route::post('/AcademicSubmissions', [AcademicSubmissionController::class, 'store'])->name('academic.submissions.store');
});

Route::middleware(['auth', 'role:student-athlete,student', 'academic.eligibility'])->group(function () {
    Route::get('/StudentAthleteDashboard', [StudentAthleteController::class, 'dashboard'])
        ->name('StudentAthleteDashboard');
    Route::get('/MyTeam', [StudentAthleteController::class, 'index'])->name('MyTeam');
    Route::put('/Student/TeamPlayers/{teamPlayer}/jersey', [StudentAthleteController::class, 'updateDesiredJersey'])
        ->name('student.team_players.jersey');
    Route::get('/MySchedule', [ScheduleRecord::class, 'mySchedules'])->name('MySchedule');
});
