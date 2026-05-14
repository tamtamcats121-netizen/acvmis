<?php

namespace App\Http\Middleware;

use App\Models\AcademicDocument;
use App\Models\AcademicPeriod;
use App\Models\Announcement;
use App\Models\ScheduleAttendance;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSchedule;
use App\Models\TeamStaffAssignment;
use App\Models\User;
use App\Services\AcademicEligibilityAccessService;
use App\Services\EmailVerificationService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $verificationService = app(EmailVerificationService::class);
        $academicDocumentTable = (new AcademicDocument())->getTable();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'login_success' => fn () => $request->session()->get('login_success'),
                'created_schedule_id' => fn () => $request->session()->get('created_schedule_id'),
            ],
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'first_name' => $request->user()->first_name,
                        'middle_name' => $request->user()->middle_name,
                        'last_name' => $request->user()->last_name,
                        'email' => $request->user()->email,
                        'email_verified_at' => optional($request->user()->email_verified_at)?->toIso8601String(),
                        'role' => $request->user()->role,
                        'account_state' => $request->user()->account_state,
                        'approval_status' => $request->user()->approval_status,
                        'must_change_password' => (bool) $request->user()->must_change_password,
                        'avatar' => $request->user()->avatar,
                        'avatar_url' => $request->user()->avatar_url,
                    ]
                    : null,
                'identity' => fn () => $request->user()
                    ? (function () use ($request) {
                        try {
                                return [
                                    'name' => $request->user()->name,
                                    'subtitle' => in_array($request->user()->role, ['student', 'student-athlete'], true)
                                    ? Student::query()
                                        ->without('user')
                                        ->where('user_id', $request->user()->id)
                                        ->value('student_status')
                                    : null,
                            ];
                        } catch (\Throwable $e) {
                            Log::warning('Identity share payload failed.', [
                                'user_id' => $request->user()?->id,
                                'message' => $e->getMessage(),
                            ]);

                            return [
                                'name' => $request->user()->name,
                                'subtitle' => null,
                            ];
                        }
                    })()
                    : null,
                'verification' => fn () => $request->user()
                    ? $verificationService->statusPayload($request->user())
                    : null,
                'announcements' => [
                    'unread_count' => fn () => $request->user()
                        ? (function () use ($request) {
                            try {
                                $count = Cache::remember(
                                    'announcements:unread:' . $request->user()->id,
                                    now()->addSeconds(60),
                                    fn () => Announcement::query()
                                        ->where('user_id', $request->user()->id)
                                        ->whereNull('read_at')
                                        ->count()
                                );

                                if (!$request->user()->hasVerifiedEmail()) {
                                    $count++;
                                }

                                return $count;
                            } catch (\Throwable $e) {
                                Log::warning('Announcement unread count share payload failed.', [
                                    'user_id' => $request->user()?->id,
                                    'message' => $e->getMessage(),
                                ]);

                                return 0;
                            }
                        })()
                        : 0,
                ],
                'admin_notifications' => fn () => $request->user() && $request->user()->role === 'admin'
                    ? (function () use ($request, $verificationService, $academicDocumentTable) {
                        $payload = Cache::remember(
                            'admin:notifications:' . $request->user()->id,
                            now()->addSeconds(60),
                            function () use ($request, $academicDocumentTable) {
                            $adminId = $request->user()->id;
                            $now = now();

                            $scheduleCount = TeamSchedule::query()
                                ->whereBetween('start_time', [$now, (clone $now)->addDays(7)])
                                ->count();

                            $academicPeriodId = AcademicPeriod::query()
                                ->open()
                                ->orderByDesc('starts_on')
                                ->value('id');

                            $academicSubmissions = AcademicDocument::query()
                                ->when($academicPeriodId, function ($query, $periodId) {
                                    $query->where('academic_period_id', $periodId);
                                })
                                ->whereNotExists(function ($subQuery) use ($academicDocumentTable) {
                                    $subQuery->selectRaw('1')
                                        ->from('academic_eligibility_evaluations as e')
                                        ->whereColumn('e.student_id', $academicDocumentTable . '.student_id')
                                        ->whereColumn('e.academic_period_id', $academicDocumentTable . '.academic_period_id');
                                })
                                ->count();

                            $periodReminder = AcademicPeriod::query()
                                ->open()
                                ->whereNotNull('ends_on')
                                ->whereDate('ends_on', '>=', $now->toDateString())
                                ->whereDate('ends_on', '<=', (clone $now)->addDays(14)->toDateString())
                                ->count();

                            $items = [
                                [
                                    'key' => 'schedules',
                                    'label' => 'Schedules',
                                    'count' => $scheduleCount,
                                    'href' => '/operations',
                                ],
                                [
                                    'key' => 'academic_submissions',
                                    'label' => 'Academic submissions from students',
                                    'count' => $academicSubmissions,
                                    'href' => '/documents',
                                ],
                                [
                                    'key' => 'period_reminder',
                                    'label' => 'Period nearly done reminder',
                                    'count' => $periodReminder,
                                    'href' => '/documents',
                                ],
                            ];

                            return [
                                'total' => array_sum(array_map(fn ($item) => (int) $item['count'], $items)),
                                'items' => $items,
                                'recent' => Announcement::query()
                                    ->join('announcement_events as ae', 'ae.id', '=', 'announcement_recipients.event_id')
                                    ->select('announcement_recipients.*')
                                    ->where('user_id', $adminId)
                                    ->orderByDesc('ae.published_at')
                                    ->orderByDesc('announcement_recipients.id')
                                    ->limit(6)
                                    ->with('event')
                                    ->get()
                                    ->map(function (Announcement $announcement) {
                                        return [
                                            'id' => $announcement->id,
                                            'title' => $announcement->title,
                                            'message' => Str::limit((string) $announcement->message, 140),
                                            'type' => $announcement->type,
                                            'is_read' => !empty($announcement->read_at),
                                            'published_at' => $announcement->published_at?->diffForHumans(),
                                        ];
                                    })
                                    ->values(),
                            ];
                        }
                        );

                        $reminder = $verificationService->reminderPayload($request->user());
                        if (!$reminder) {
                            return $payload;
                        }

                        $payload['recent'] = collect([$reminder])
                            ->merge(collect($payload['recent'] ?? []))
                            ->values()
                            ->all();

                        return $payload;
                    })()
                    : null,
                'coach_notifications' => fn () => $request->user() && $request->user()->role === 'coach'
                    ? (function () use ($request, $verificationService) {
                        $payload = Cache::remember(
                            'coach:notifications:' . $request->user()->id,
                            now()->addSeconds(60),
                            function () use ($request) {
                            try {
                                $coach = $request->user()?->coach;
                                if (!$coach) {
                                    return null;
                                }

                                $now = now();
                                $teamIds = Team::query()
                                    ->forCoach($coach->id)
                                    ->pluck('id')
                                    ->all();

                                if (empty($teamIds)) {
                                    return [
                                        'total' => 0,
                                        'items' => [],
                                        'recent' => [],
                                    ];
                                }

                                $studentIds = TeamPlayer::query()
                                    ->whereIn('team_id', $teamIds)
                                    ->pluck('student_id')
                                    ->unique()
                                    ->values()
                                    ->all();

                                $academicPeriodId = AcademicPeriod::query()
                                    ->open()
                                    ->orderByDesc('starts_on')
                                    ->value('id');

                                $studentSubmissions = $academicPeriodId
                                    ? AcademicDocument::query()
                                        ->where('academic_period_id', $academicPeriodId)
                                        ->whereIn('student_id', $studentIds)
                                        ->count()
                                    : 0;

                                $recentWindow = (clone $now)->subDays(30);
                                $teamCreated = Team::query()
                                    ->whereIn('id', $teamIds)
                                    ->whereDate('created_at', '>=', $recentWindow->toDateString())
                                    ->count();

                                $rosterWindow = (clone $now)->subDays(14);
                                $playerAdds = TeamPlayer::query()
                                    ->whereIn('team_id', $teamIds)
                                    ->whereDate('created_at', '>=', $rosterWindow->toDateString())
                                    ->count();

                                $assistantAdds = TeamStaffAssignment::query()
                                    ->whereIn('team_id', $teamIds)
                                    ->where('role', TeamStaffAssignment::ROLE_ASSISTANT)
                                    ->whereDate('created_at', '>=', $rosterWindow->toDateString())
                                    ->count();

                                $statusWindow = (clone $now)->subDays(7);
                                $statusUpdates = ScheduleAttendance::query()
                                    ->whereHas('schedule', fn ($q) => $q->whereIn('team_id', $teamIds))
                                    ->whereDate('updated_at', '>=', $statusWindow->toDateString())
                                    ->count();

                                $items = [
                                    [
                                        'key' => 'student_submissions',
                                        'label' => 'Student submissions',
                                        'count' => $studentSubmissions,
                                        'href' => '/coach/documents',
                                    ],
                                    [
                                        'key' => 'team_created',
                                        'label' => 'Team created for coach',
                                        'count' => $teamCreated,
                                        'href' => '/coach/team',
                                    ],
                                    [
                                        'key' => 'roster_updates',
                                        'label' => 'Roster updates (assistant + players)',
                                        'count' => $playerAdds + $assistantAdds,
                                        'href' => '/coach/team',
                                    ],
                                    [
                                        'key' => 'student_status',
                                        'label' => 'Attendance updates from schedules',
                                        'count' => $statusUpdates,
                                        'href' => '/coach/schedule',
                                    ],
                                ];

                                return [
                                    'total' => array_sum(array_map(fn ($item) => (int) $item['count'], $items)),
                                    'items' => $items,
                                    'recent' => Announcement::query()
                                        ->join('announcement_events as ae', 'ae.id', '=', 'announcement_recipients.event_id')
                                        ->select('announcement_recipients.*')
                                        ->where('user_id', $request->user()->id)
                                        ->orderByDesc('ae.published_at')
                                        ->orderByDesc('announcement_recipients.id')
                                        ->limit(6)
                                        ->with('event')
                                        ->get()
                                        ->map(function (Announcement $announcement) {
                                            return [
                                                'id' => $announcement->id,
                                                'title' => $announcement->title,
                                                'message' => Str::limit((string) $announcement->message, 140),
                                                'type' => $announcement->type,
                                                'is_read' => !empty($announcement->read_at),
                                                'published_at' => $announcement->published_at?->diffForHumans(),
                                            ];
                                        })
                                        ->values(),
                                ];
                            } catch (\Throwable $e) {
                                Log::warning('Coach notifications share payload failed.', [
                                    'user_id' => $request->user()?->id,
                                    'coach_id' => $request->user()?->coach?->id,
                                    'message' => $e->getMessage(),
                                ]);

                                return [
                                    'total' => 0,
                                    'items' => [],
                                    'recent' => [],
                                ];
                            }
                        }
                        );

                        if (!$payload) {
                            return $payload;
                        }

                        $reminder = $verificationService->reminderPayload($request->user());
                        if (!$reminder) {
                            return $payload;
                        }

                        $payload['recent'] = collect([$reminder])
                            ->merge(collect($payload['recent'] ?? []))
                            ->values()
                            ->all();

                        return $payload;
                    })()
                    : null,
                'student_notifications' => fn () => $request->user() && in_array($request->user()->role, ['student', 'student-athlete'], true)
                    ? (function () use ($request, $verificationService) {
                        $payload = Cache::remember(
                            'student:notifications:' . $request->user()->id,
                            now()->addSeconds(60),
                            function () use ($request) {
                            try {
                                $studentId = $request->user()->id;

                                return [
                                    'recent' => Announcement::query()
                                        ->join('announcement_events as ae', 'ae.id', '=', 'announcement_recipients.event_id')
                                        ->select('announcement_recipients.*')
                                        ->where('user_id', $studentId)
                                        ->orderByDesc('ae.published_at')
                                        ->orderByDesc('announcement_recipients.id')
                                        ->limit(6)
                                        ->with('event')
                                        ->get()
                                        ->map(function (Announcement $announcement) {
                                            return [
                                                'id' => $announcement->id,
                                                'title' => $announcement->title,
                                                'message' => Str::limit((string) $announcement->message, 140),
                                                'type' => $announcement->type,
                                                'is_read' => !empty($announcement->read_at),
                                                'published_at' => $announcement->published_at?->diffForHumans(),
                                            ];
                                        })
                                        ->values(),
                                ];
                            } catch (\Throwable $e) {
                                Log::warning('Student notifications share payload failed.', [
                                    'user_id' => $request->user()?->id,
                                    'message' => $e->getMessage(),
                                ]);

                                return [
                                    'recent' => [],
                                ];
                            }
                        }
                        );

                        $reminder = $verificationService->reminderPayload($request->user());
                        if (!$reminder) {
                            return $payload;
                        }

                        $payload['recent'] = collect([$reminder])
                            ->merge(collect($payload['recent'] ?? []))
                            ->values()
                            ->all();

                        return $payload;
                    })()
                    : null,
                'academic_access' => fn () => $request->user() && in_array($request->user()->role, ['student', 'student-athlete'], true) && $request->user()->student
                    ? (function () use ($request) {
                        try {
                            return app(AcademicEligibilityAccessService::class)->evaluate($request->user()->student);
                        } catch (\Throwable $e) {
                            Log::warning('Academic access share payload failed.', [
                                'user_id' => $request->user()?->id,
                                'student_id' => $request->user()?->student?->id,
                                'message' => $e->getMessage(),
                            ]);

                            return [
                                'is_restricted' => false,
                                'status' => null,
                                'message' => null,
                                'evaluation' => null,
                            ];
                        }
                    })()
                    : null,
                'student_context' => fn () => $request->user() && in_array($request->user()->role, ['student', 'student-athlete'], true) && $request->user()->student
                    ? (function () use ($request) {
                        try {
                            return [
                                'has_team_assignment' => TeamPlayer::query()
                                    ->where('student_id', $request->user()->student->id)
                                    ->exists(),
                            ];
                        } catch (\Throwable $e) {
                            Log::warning('Student context share payload failed.', [
                                'user_id' => $request->user()?->id,
                                'student_id' => $request->user()?->student?->id,
                                'message' => $e->getMessage(),
                            ]);

                            return [
                                'has_team_assignment' => false,
                            ];
                        }
                    })()
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'login_success' => fn () => $request->session()->get('login_success'),
                'coach_onboarding' => fn () => $request->session()->get('coach_onboarding'),
            ],
        ];
    }
}
