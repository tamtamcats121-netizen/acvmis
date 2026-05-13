<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\ScheduleAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Team;
use App\Models\Coach;
use App\Models\TeamSchedule;
use App\Models\TeamPlayer;
use App\Models\Student;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoachScheduleController extends Controller
{
    public function __construct(private SystemNotificationService $notifications)
    {
    }

    private function ensureScheduleTimingAllowed(
        int $teamId,
        string $startTime,
        string $endTime,
        ?int $ignoreScheduleId = null
    ): void {
        $tz = config('app.timezone');
        $start = Carbon::parse($startTime, $tz);
        $end = Carbon::parse($endTime, $tz);
        $today = Carbon::now($tz)->startOfDay();

        if ($start->copy()->startOfDay()->lt($today)) {
            throw ValidationException::withMessages([
                'start_time' => 'Creating schedules for prior dates is not allowed.',
            ]);
        }

        $overlapExists = TeamSchedule::query()
            ->where('team_id', $teamId)
            ->when($ignoreScheduleId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->where(function ($query) use ($start, $end) {
                $query->where('start_time', '<', $end->format('Y-m-d H:i:s'))
                    ->where('end_time', '>', $start->format('Y-m-d H:i:s'));
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'start_time' => 'This schedule overlaps with an existing team schedule. Choose a different date or time.',
            ]);
        }
    }

    private function authorizedTeamIdsForCurrentCoach(Request $request): array
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        return Team::query()
            ->forCoach($coach->id)
            ->pluck('id')
            ->all();
    }

    private function attendanceStatusForSchedule(TeamSchedule $schedule): string
    {
        $tz = config('app.timezone');
        $start = Carbon::parse($schedule->start_time, $tz);
        $end = Carbon::parse($schedule->end_time, $tz);
        $now = Carbon::now($tz);

        if ($end->lt($now)) {
            return 'completed';
        }

        if ($start->lte($now) && $end->gte($now)) {
            return 'in_progress';
        }

        return 'upcoming';
    }

    private function buildEditableAttendanceRows(Team $team, int $scheduleId): array
    {
        $players = TeamPlayer::query()
            ->with('student')
            ->where('team_id', $team->id)
            ->join('students', 'team_players.student_id', '=', 'students.id')
            ->join('users as su', 'su.id', '=', 'students.user_id')
            ->orderBy('su.last_name')
            ->orderBy('su.first_name')
            ->select('team_players.*')
            ->get()
            ->filter(fn ($player) => $player->student !== null)
            ->values();

        $attendanceByStudent = ScheduleAttendance::query()
            ->where('schedule_id', $scheduleId)
            ->whereIn('student_id', $players->pluck('student_id')->all())
            ->get()
            ->keyBy('student_id');

        return $players->map(function ($player) use ($attendanceByStudent) {
            $student = $player->student;
            $attendance = $attendanceByStudent->get($student->id);

            return [
                'student_id' => $student->id,
                'student_id_number' => $student->student_id_number,
                'full_name' => trim(
                    ($student->last_name ?? '')
                    . ', '
                    . ($student->first_name ?? '')
                    . (!empty($student->middle_name) ? ' ' . $student->middle_name : '')
                ),
                'jersey_number' => $player->jersey_number,
                'athlete_position' => $player->athlete_position,
                'status' => $attendance?->status,
                'notes' => $attendance?->notes,
                'recorded_at' => $attendance?->recorded_at?->toIso8601String(),
                'verification_method' => $attendance?->verification_method,
            ];
        })->values()->all();
    }

    public function index(Request $request)
    {
        $coach = $request->user()?->coach;

        if (!$coach) {
            return Inertia::render('Coaches/CoachSchedule', [
                'schedules' => [],
                'teams' => [],
                'selectedTeamId' => null,
            ]);
        }

        $teams = Team::with('sport')
            ->forCoach($coach->id)
            ->orderBy('team_name')
            ->get();

        if ($teams->isEmpty()) {
            return Inertia::render('Coaches/CoachSchedule', [
                'schedules' => [],
                'teams' => [],
                'selectedTeamId' => null,
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        // Convert datetime to ISO 8601 for VueCal compatibility
        $schedules = TeamSchedule::with(['team.sport'])
            ->withCount('attendances')
            ->where('team_id', $selectedTeamId)
            ->orderBy('start_time')
            ->get();

        $rosterCount = (int) TeamPlayer::where('team_id', $selectedTeamId)->count();
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        $schedules = $schedules->map(function ($s) use ($teamIds, $rosterCount, $now, $tz) {
                $start = Carbon::parse($s->start_time, $tz);
                $end = Carbon::parse($s->end_time, $tz);

                if ($end->lt($now)) {
                    $status = 'completed';
                } elseif ($start->lte($now) && $end->gte($now)) {
                    $status = 'in_progress';
                } else {
                    $status = 'upcoming';
                }

                $attendanceCount = (int) ($s->attendances_count ?? 0);

                if ($attendanceCount === 0) {
                    $attendanceState = 'not_started';
                } elseif ($rosterCount > 0 && $attendanceCount >= $rosterCount) {
                    $attendanceState = 'completed';
                } else {
                    $attendanceState = 'in_progress';
                }

                $isLocked = $status === 'completed' && $attendanceCount > 0;

                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'type' => $s->type,
                    'venue' => $s->venue,
                    'team_name' => $s->team?->team_name,
                    'sport' => $s->team?->sport?->name ?? $s->team?->sport_id ?? 'unknown',
                    'team_id' => $s->team_id,
                    'is_owner' => in_array($s->team_id, $teamIds, true),
                    'start' => Carbon::parse($s->start_time)->toIso8601String(),
                    'end' => Carbon::parse($s->end_time)->toIso8601String(),
                    'notes' => $s->notes,
                    'calendar_url' => route('schedules.calendar', ['schedule' => $s->id]),
                    'status' => $status,
                    'attendance_state' => $attendanceState,
                    'attendance_count' => $attendanceCount,
                    'roster_count' => $rosterCount,
                    'is_locked' => $isLocked,
                ];
            });

        return Inertia::render('Coaches/CoachSchedule', [
            'schedules' => $schedules,
            'teams' => $teams->map(function (Team $team) {
                return [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ];
            })->values(),
            'selectedTeamId' => $selectedTeamId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'team_id'    => 'nullable|integer',
            'title'      => 'required|string|max:255',
            'type'       => ['required', 'string', 'max:50', Rule::in(['practice', 'game', 'Practice', 'Game'])],
            'venue'      => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after:start_time',
            'notes'      => 'nullable|string',
        ]);

        $validated['type'] = strtolower(trim((string) $validated['type']));

        $coach = $request->user()?->coach;

        if (!$coach) {
            return back()->withErrors(['coach' => 'No coach record']);
        }

        $teams = Team::query()
            ->forCoach($coach->id)
            ->get();

        if ($teams->isEmpty()) {
            return back()->withErrors(['team' => 'No team assigned']);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) ($validated['team_id'] ?? 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            if (count($teamIds) === 1) {
                $selectedTeamId = $teamIds[0];
            } else {
                return back()->withErrors(['team_id' => 'Please select a team.']);
            }
        }

        $ownerTeam = $teams->firstWhere('id', $selectedTeamId);
        if (!$ownerTeam) {
            return back()->withErrors(['team_id' => 'Invalid team selection.']);
        }

        $tz = config('app.timezone');
        $startTime = Carbon::parse($validated['start_time'], $tz)->format('Y-m-d H:i:s');
        $endTime = Carbon::parse($validated['end_time'], $tz)->format('Y-m-d H:i:s');

        $this->ensureScheduleTimingAllowed($ownerTeam->id, $startTime, $endTime);

        $created = TeamSchedule::create([
            'team_id'    => $ownerTeam->id,
            'title'      => $validated['title'],
            'type'       => $validated['type'],
            'venue'      => $validated['venue'],
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'notes'      => $validated['notes'] ?? null,
        ]);

        $this->notifyScheduleChange($ownerTeam, Auth::id(), 'Schedule Created', sprintf(
            'New %s scheduled: %s, %s, %s.',
            strtolower((string) $created->type),
            $created->title,
            Carbon::parse($created->start_time)->format('M j, g:i A'),
            $created->venue
        ));

        return redirect()->route('coach.schedule.index', [
            'team_id' => $ownerTeam->id,
        ])->with('success', 'Schedule created successfully.');
    }
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'team_id' => 'nullable|integer',
            'title' => 'sometimes|string|max:255',
            'type' => ['sometimes', 'string', 'max:50', Rule::in(['practice', 'game', 'Practice', 'Game'])],
            'venue' => 'sometimes|string|max:255',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['type'])) {
            $validated['type'] = strtolower(trim((string) $validated['type']));
        }

        $schedule = TeamSchedule::findOrFail($id);
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $teamIds = Team::query()
            ->forCoach($coach->id)
            ->pluck('id')
            ->all();

        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule update.');
        $tz = config('app.timezone');
        $isLocked = Carbon::parse($schedule->end_time, $tz)->lt(Carbon::now($tz))
            && $schedule->attendances()->exists();
        abort_if($isLocked, 403, 'Completed schedules with attendance cannot be edited.');

        if (isset($validated['start_time'])) {
            $validated['start_time'] = Carbon::parse(
                $validated['start_time'],
                $tz
            )->format('Y-m-d H:i:s');
        }

        if (isset($validated['end_time'])) {
            $validated['end_time'] = Carbon::parse(
                $validated['end_time'],
                $tz
            )->format('Y-m-d H:i:s');
        }

        $nextStart = $validated['start_time'] ?? $schedule->start_time;
        $nextEnd = $validated['end_time'] ?? $schedule->end_time;

        $this->ensureScheduleTimingAllowed($schedule->team_id, $nextStart, $nextEnd, $schedule->id);

        unset($validated['team_id']);
        $schedule->update($validated);

        $ownerTeam = Team::find($schedule->team_id);
        if (!$ownerTeam) {
            return redirect()->route('coach.schedule.index');
        }

        $this->notifyScheduleChange($ownerTeam, Auth::id(), 'Schedule Updated', sprintf(
            'Schedule updated: %s on %s at %s (%s).',
            $schedule->title,
            Carbon::parse($schedule->start_time)->format('M j, g:i A'),
            $schedule->venue,
            strtolower((string) $schedule->type)
        ));

        return redirect()->route('coach.schedule.index', [
            'team_id' => $ownerTeam->id,
        ])->with('success', 'Schedule updated successfully.');
    }


    public function destroy($id)
    {
        $schedule = TeamSchedule::findOrFail($id);
        $coach = Auth::user()?->coach;
        abort_unless($coach, 403);

        $teamIds = Team::query()
            ->forCoach($coach->id)
            ->pluck('id')
            ->all();

        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule delete.');
        $tz = config('app.timezone');
        $isLocked = Carbon::parse($schedule->end_time, $tz)->lt(Carbon::now($tz))
            && $schedule->attendances()->exists();
        abort_if($isLocked, 403, 'Completed schedules with attendance cannot be deleted.');

        $ownerTeam = Team::find($schedule->team_id);
        if (!$ownerTeam) {
            return redirect()->route('coach.schedule.index');
        }

        $title = $schedule->title;
        $type = strtolower((string) $schedule->type);
        $start = Carbon::parse($schedule->start_time)->format('M j, g:i A');
        $venue = (string) $schedule->venue;
        $schedule->delete();

        $this->notifyScheduleChange(
            $ownerTeam,
            Auth::id(),
            'Schedule Cancelled',
            "Schedule cancelled: {$title} ({$type}) on {$start} at {$venue}."
        );

        return redirect()->route('coach.schedule.index', [
            'team_id' => $ownerTeam->id,
        ])->with('success', 'Schedule deleted successfully.');
    }

    public function print(Request $request)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $teamIds = Team::query()
            ->forCoach($coach->id)
            ->pluck('id')
            ->all();
        abort_unless(!empty($teamIds), 403);

        $validated = $request->validate([
            'team_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $selectedTeamId = (int) ($validated['team_id'] ?? 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = Team::with('sport')->findOrFail($selectedTeamId);

        $query = TeamSchedule::query()->where('team_id', $selectedTeamId);
        if (!empty($validated['start_date'])) {
            $query->whereDate('start_time', '>=', $validated['start_date']);
        }
        if (!empty($validated['end_date'])) {
            $query->whereDate('start_time', '<=', $validated['end_date']);
        }

        $schedules = $query->orderBy('start_time')->get()->map(function ($schedule) {
            return [
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'start' => optional($schedule->start_time)->format('M j, Y g:i A'),
                'end' => optional($schedule->end_time)->format('M j, Y g:i A'),
                'notes' => $schedule->notes,
            ];
        })->values();

        $rangeLabel = (!empty($validated['start_date']) || !empty($validated['end_date']))
            ? trim(($validated['start_date'] ?? '...') . ' to ' . ($validated['end_date'] ?? '...'))
            : 'All Dates';

        return view('print.coach-schedule', [
            'team' => [
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? 'Unknown',
            ],
            'schedules' => $schedules,
            'rangeLabel' => $rangeLabel,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ]);
    }

    public function roster(Request $request, TeamSchedule $schedule): JsonResponse
    {
        $teamIds = $this->authorizedTeamIdsForCurrentCoach($request);
        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule.');

        $team = Team::with('sport')->findOrFail($schedule->team_id);
        $scheduleStatus = $this->attendanceStatusForSchedule($schedule);
        $rows = $this->buildEditableAttendanceRows($team, $schedule->id);

        return response()->json([
            'schedule' => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'start' => Carbon::parse($schedule->start_time)->toIso8601String(),
                'end' => Carbon::parse($schedule->end_time)->toIso8601String(),
                'status' => $scheduleStatus,
                'can_record_now' => $scheduleStatus !== 'upcoming',
                'attendance_count' => ScheduleAttendance::query()->where('schedule_id', $schedule->id)->count(),
                'roster_count' => count($rows),
            ],
            'team' => [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
            ],
            'rows' => $rows,
        ]);
    }

    public function bulkAttendance(Request $request, TeamSchedule $schedule): JsonResponse
    {
        $teamIds = $this->authorizedTeamIdsForCurrentCoach($request);
        abort_unless(in_array($schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule.');

        abort_if(
            $this->attendanceStatusForSchedule($schedule) === 'upcoming',
            422,
            'Attendance can only be recorded once the schedule has started.'
        );

        $validated = $request->validate([
            'rows' => 'required|array',
            'rows.*.student_id' => 'required|integer',
            'rows.*.status' => 'nullable|in:present,absent,late,excused',
            'rows.*.notes' => 'nullable|string|max:500',
        ]);

        $memberIds = TeamPlayer::query()
            ->where('team_id', $schedule->team_id)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = collect($validated['rows'] ?? [])
            ->keyBy(fn (array $row) => (int) $row['student_id']);

        foreach ($memberIds as $studentId) {
            $row = $rows->get($studentId);
            if (!$row) {
                continue;
            }

            $status = $row['status'] ?? null;
            $notes = trim((string) ($row['notes'] ?? ''));

            if (!$status) {
                ScheduleAttendance::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('student_id', $studentId)
                    ->delete();
                continue;
            }

            ScheduleAttendance::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'student_id' => $studentId,
                ],
                [
                    'status' => $status,
                    'verification_method' => 'manual_override',
                    'recorded_by' => Auth::id(),
                    'recorded_at' => now(),
                    'verified_at' => now(),
                    'notes' => $notes !== '' ? $notes : null,
                    'override_reason' => in_array($status, ['absent', 'late', 'excused'], true) && $notes !== '' ? $notes : null,
                ]
            );
        }

        return response()->json([
            'message' => 'Attendance saved successfully.',
            'attendance_count' => ScheduleAttendance::query()->where('schedule_id', $schedule->id)->count(),
        ]);
    }

    private function notifyScheduleChange(Team $team, int $actorUserId, string $title, string $message): void
    {
        $studentIds = TeamPlayer::where('team_id', $team->id)->pluck('student_id');
        $studentUserIds = Student::whereIn('id', $studentIds)->pluck('user_id')->filter()->all();

        $coachUserIds = Coach::whereIn('id', $team->activeCoachIds())
            ->pluck('user_id')
            ->filter()
            ->reject(fn ($id) => (int) $id === $actorUserId)
            ->all();

        $recipientIds = array_merge($studentUserIds, $coachUserIds);
        $this->notifications->announceMany(
            $recipientIds,
            $title,
            $message,
            'schedule',
            $actorUserId,
            'notify_schedule_changes'
        );
    }
}
