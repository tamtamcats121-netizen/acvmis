<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSchedule;
use App\Models\TrainingRequirement;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TrainingRequirementController extends Controller
{
    private function scheduleStatus(TeamSchedule $schedule): string
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

    private function abortUnlessUpcoming(TeamSchedule $schedule): void
    {
        abort_unless(
            $this->scheduleStatus($schedule) === 'upcoming',
            422,
            'Training requirements can only be assigned before the schedule starts.'
        );
    }

    private function viewableScheduleForUser(Request $request, TeamSchedule $schedule): TeamSchedule
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->role === 'admin') {
            return $schedule->load([
                'team.sport',
                'team.headCoachAssignment',
                'trainingRequirements.student.user',
                'trainingRequirements.coach.user',
            ]);
        }

        $coach = $user->coach;
        abort_unless($coach, 403);

        $teamIds = Team::query()
            ->forCoach($coach->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(in_array((int) $schedule->team_id, $teamIds, true), 403, 'Unauthorized schedule.');

        return $schedule->load([
            'team.sport',
            'team.headCoachAssignment',
            'trainingRequirements.student.user',
            'trainingRequirements.coach.user',
        ]);
    }

    private function manageableScheduleForCoach(Request $request, TeamSchedule $schedule): array
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $team = Team::query()
            ->with(['sport', 'headCoachAssignment'])
            ->forCoach($coach->id)
            ->whereKey($schedule->team_id)
            ->first();

        abort_unless($team, 403, 'Unauthorized schedule.');

        $schedule->load([
            'trainingRequirements.student.user',
            'trainingRequirements.coach.user',
        ]);

        return [$coach, $team, $schedule];
    }

    private function rosterForTeam(Team $team)
    {
        return TeamPlayer::query()
            ->with('student.user')
            ->where('team_id', $team->id)
            ->join('students', 'team_players.student_id', '=', 'students.id')
            ->join('users as su', 'su.id', '=', 'students.user_id')
            ->orderBy('su.last_name')
            ->orderBy('su.first_name')
            ->select('team_players.*')
            ->get()
            ->filter(fn ($player) => $player->student !== null)
            ->values();
    }

    private function requirementRows(TeamSchedule $schedule): array
    {
        return $schedule->trainingRequirements
            ->sortBy([
                fn (TrainingRequirement $requirement) => strtolower($requirement->student?->last_name ?? ''),
                fn (TrainingRequirement $requirement) => strtolower($requirement->student?->first_name ?? ''),
                fn (TrainingRequirement $requirement) => strtolower($requirement->title),
            ])
            ->values()
            ->map(function (TrainingRequirement $requirement) {
                return [
                    'id' => $requirement->id,
                    'student_id' => $requirement->student_id,
                    'student_name' => $requirement->student?->full_name ?: 'Unknown student',
                    'student_id_number' => $requirement->student?->student_id_number,
                    'category' => $requirement->category,
                    'title' => $requirement->title,
                    'description' => $requirement->description,
                    'coach_name' => $requirement->coach?->full_name ?: 'Unknown coach',
                    'created_at' => optional($requirement->created_at)?->format('M j, Y g:i A'),
                ];
            })
            ->all();
    }

    public function show(Request $request, TeamSchedule $schedule): Response
    {
        $schedule = $this->viewableScheduleForUser($request, $schedule);
        $team = $schedule->team;

        $roster = $this->rosterForTeam($team)->map(function ($player) {
            return [
                'student_id' => $player->student_id,
                'student_name' => $player->student?->full_name ?: 'Unknown student',
                'student_id_number' => $player->student?->student_id_number,
                'jersey_number' => $player->jersey_number,
                'position' => $player->athlete_position,
            ];
        })->values();

        $coach = $request->user()?->coach;
        $status = $this->scheduleStatus($schedule);
        $canManage = $request->user()?->role === 'coach' && $status === 'upcoming';

        return Inertia::render('Coaches/TrainingRequirements', [
            'schedule' => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'notes' => $schedule->notes,
                'start' => optional($schedule->start_time)?->toIso8601String(),
                'end' => optional($schedule->end_time)?->toIso8601String(),
                'status' => $status,
                'can_manage_requirements' => $canManage,
            ],
            'team' => [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
            ],
            'coach' => $coach ? [
                'id' => $coach->id,
                'full_name' => $coach->full_name,
            ] : null,
            'categories' => TrainingRequirement::CATEGORIES,
            'students' => $roster,
            'requirements' => $this->requirementRows($schedule),
            'canManage' => $canManage,
            'printUrl' => route('training-requirements.print', $schedule),
        ]);
    }

    public function store(Request $request, TeamSchedule $schedule): RedirectResponse
    {
        [$coach, $team, $schedule] = $this->manageableScheduleForCoach($request, $schedule);
        $this->abortUnlessUpcoming($schedule);

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|distinct',
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:' . implode(',', TrainingRequirement::CATEGORIES),
            'description' => 'nullable|string|max:2000',
        ]);

        $teamStudentIds = $this->rosterForTeam($team)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($validated['student_ids'] as $studentId) {
            abort_unless(in_array((int) $studentId, $teamStudentIds, true), 422, 'Selected students must belong to the schedule team.');
        }

        $description = trim((string) ($validated['description'] ?? ''));

        foreach ($validated['student_ids'] as $studentId) {
            TrainingRequirement::create([
                'schedule_id' => $schedule->id,
                'student_id' => (int) $studentId,
                'coach_id' => $coach->id,
                'title' => $validated['title'],
                'category' => $validated['category'],
                'description' => $description !== '' ? $description : null,
            ]);
        }

        return redirect()
            ->route('training-requirements.show', $schedule)
            ->with('success', 'Training requirements assigned successfully.');
    }

    public function destroy(Request $request, TeamSchedule $schedule, TrainingRequirement $trainingRequirement): RedirectResponse
    {
        [$coach, , $schedule] = $this->manageableScheduleForCoach($request, $schedule);
        $this->abortUnlessUpcoming($schedule);

        abort_unless((int) $trainingRequirement->schedule_id === (int) $schedule->id, 404);
        abort_unless((int) $trainingRequirement->coach_id === (int) $coach->id || $request->user()?->role === 'admin', 403);

        $trainingRequirement->delete();

        return redirect()
            ->route('training-requirements.show', $schedule)
            ->with('success', 'Training requirement removed.');
    }

    public function print(Request $request, TeamSchedule $schedule)
    {
        $schedule = $this->viewableScheduleForUser($request, $schedule);
        $team = $schedule->team;
        $coachName = $schedule->trainingRequirements->first()?->coach?->full_name
            ?? Coach::query()->whereKey($team->coach_id)->first()?->full_name
            ?? 'Assigned Coach';

        return view('print.training-requirements', [
            'team' => [
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? 'Unknown',
            ],
            'schedule' => [
                'title' => $schedule->title,
                'type' => $schedule->type,
                'venue' => $schedule->venue,
                'start' => optional($schedule->start_time)?->format('M j, Y g:i A'),
                'end' => optional($schedule->end_time)?->format('M j, Y g:i A'),
            ],
            'coachName' => $coachName,
            'printedAt' => now()->format('M j, Y g:i A'),
            'requirements' => $this->requirementRows($schedule),
        ]);
    }
}
