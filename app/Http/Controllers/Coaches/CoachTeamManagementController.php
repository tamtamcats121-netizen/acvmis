<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\Sport;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamStaffAssignment;
use App\Services\SecureUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CoachTeamManagementController extends Controller
{
    public function __construct(private SecureUploadService $secureUpload)
    {
    }

    public function index(Request $request)
    {
        $coach = $request->user()?->coach;
        $sportId = (int) ($coach?->sport_id ?? 0);

        if (!$coach || $sportId <= 0) {
            return Inertia::render('Coaches/CoachTeamsManage', [
                'sport' => null,
                'teams' => [],
                'selectedTeam' => null,
                'availablePlayers' => [],
                'availableAssistantCoaches' => [],
                'seasonTemplates' => [],
                'maxPlayers' => 0,
                'mode' => 'create',
            ]);
        }

        $teams = Team::query()
            ->with(['sport:id,name', 'players', 'coach.user', 'assistantCoach.user'])
            ->forCoach($coach->id)
            ->where('sport_id', $sportId)
            ->orderByDesc('year')
            ->orderBy('team_name')
            ->get();

        $createMode = (string) $request->query('mode', '') === 'create';
        $selectedTeamId = (int) $request->query('team_id', 0);
        $selectedTeam = $createMode ? null : ($teams->firstWhere('id', $selectedTeamId) ?? $teams->first());

        if ($selectedTeam) {
            $selectedTeam->load(['players.student.user', 'sport:id,name', 'coach.user', 'assistantCoach.user']);
        }

        return Inertia::render('Coaches/CoachTeamsManage', [
            'sport' => [
                'id' => $coach->sport?->id,
                'name' => $coach->sport?->name,
            ],
            'teams' => $teams->map(fn (Team $team) => [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'year' => $team->year,
                'description' => $team->description,
                'players_count' => (int) $team->players->count(),
                'archived_at' => optional($team->archived_at)?->toDateTimeString(),
                'assistant_coach_name' => $team->assistantCoach?->full_name ?: null,
            ])->values(),
            'selectedTeam' => $selectedTeam ? $this->serializeSelectedTeam($selectedTeam) : null,
            'availablePlayers' => $this->availablePlayersForSport($sportId, $selectedTeam?->id),
            'availableAssistantCoaches' => $this->assistantCoachOptionsForSport($sportId, $selectedTeam, $coach),
            'seasonTemplates' => $this->seasonTemplatesForCoach($teams, $selectedTeam?->id),
            'maxPlayers' => $this->maxPlayersForSport($sportId),
            'mode' => $createMode ? 'create' : 'edit',
        ]);
    }

    public function store(Request $request)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $coach->sport_id, 403);

        $validated = $request->validate([
            'team_name' => 'required|string|max:255',
            'team_avatar' => 'nullable|image|max:4096',
            'year' => 'required|digits:4',
            'description' => 'nullable|string',
            'clone_from_team_id' => 'nullable|integer|exists:teams,id',
            'copy_players' => 'nullable|boolean',
            'copy_assistant_coach' => 'nullable|boolean',
        ]);

        $existingTeam = Team::query()
            ->where('sport_id', (int) $coach->sport_id)
            ->where('year', $validated['year'])
            ->whereHas('staffAssignments', function ($assignmentQuery) use ($coach) {
                $assignmentQuery
                    ->where('coach_id', (int) $coach->id)
                    ->where('role', TeamStaffAssignment::ROLE_HEAD)
                    ->whereNull('ends_at');
            })
            ->first(['id', 'team_name', 'year']);

        if ($existingTeam) {
            $sportName = $coach->sport?->name ?? 'this sport';

            throw ValidationException::withMessages([
                'year' => "You already have a {$validated['year']} {$sportName} team. Edit or delete \"{$existingTeam->team_name}\" before creating another one.",
            ]);
        }

        $templateTeam = $this->resolveTemplateTeam(
            $coach->id,
            (int) $coach->sport_id,
            isset($validated['clone_from_team_id']) ? (int) $validated['clone_from_team_id'] : null
        );

        $copyPlayers = (bool) ($validated['copy_players'] ?? false);
        $copyAssistantCoach = (bool) ($validated['copy_assistant_coach'] ?? false);

        $playerIds = $copyPlayers && $templateTeam
            ? $templateTeam->players->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values()
            : collect();

        $assistantCoachId = $copyAssistantCoach && $templateTeam?->assistantCoach
            ? (int) $templateTeam->assistantCoach->id
            : null;

        $this->validatePlayersForSport($playerIds, (int) $coach->sport_id, null);

        if ($playerIds->count() > $this->maxPlayersForSport((int) $coach->sport_id)) {
            throw ValidationException::withMessages([
                'copy_players' => 'Copied players exceed the maximum roster size for this sport.',
            ]);
        }

        if ($assistantCoachId) {
            $this->validateAssistantCoachAvailability($assistantCoachId, null, (int) $coach->id);
        }

        $team = DB::transaction(function () use ($request, $validated, $playerIds, $coach, $assistantCoachId) {
            $avatarPath = null;
            if ($request->hasFile('team_avatar')) {
                $avatarPath = $this->secureUpload->storePublic(
                    $request->file('team_avatar'),
                    'team_avatars',
                    'team_avatar'
                );
            }

            $team = Team::create([
                'team_name' => $validated['team_name'],
                'team_avatar' => $avatarPath,
                'sport_id' => (int) $coach->sport_id,
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
                'archived_at' => null,
                'archived_by' => null,
            ]);

            $team->syncStaffAssignments((int) $coach->id, $assistantCoachId, $request->user()?->id);

            foreach ($playerIds as $studentId) {
                TeamPlayer::create([
                    'team_id' => $team->id,
                    'student_id' => $studentId,
                    'jersey_number' => null,
                    'athlete_position' => null,
                ]);
            }

            return $team;
        });

        return redirect()
            ->route('coach.teams.manage.index', ['team_id' => $team->id])
            ->with('success', 'Team created successfully. You can now review the roster and assistant coach.');
    }

    public function update(Request $request, Team $team)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $coach->sport_id, 403);
        abort_unless($this->coachCanManageTeam($coach->id, $team), 403);

        $validated = $request->validate([
            'team_name' => 'required|string|max:255',
            'team_avatar' => 'nullable|image|max:4096',
            'year' => 'required|digits:4',
            'description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $validated, $team) {
            $avatarPath = $team->team_avatar;
            if ($request->hasFile('team_avatar')) {
                $newAvatarPath = $this->secureUpload->storePublic(
                    $request->file('team_avatar'),
                    'team_avatars',
                    'team_avatar'
                );

                if (!empty($team->team_avatar) && $team->team_avatar !== $newAvatarPath) {
                    Storage::disk('public')->delete($team->team_avatar);
                }

                $avatarPath = $newAvatarPath;
            }

            $team->update([
                'team_name' => $validated['team_name'],
                'team_avatar' => $avatarPath,
                'year' => $validated['year'],
                'description' => $validated['description'] ?? null,
            ]);
        });

        return back()->with('success', 'Team details updated successfully.');
    }

    public function updateRoster(Request $request, Team $team)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $coach->sport_id, 403);
        abort_unless($this->coachCanManageTeam($coach->id, $team), 403);

        $validated = $request->validate([
            'player_ids' => 'nullable|array',
            'player_ids.*' => 'integer|exists:students,id',
        ]);

        $playerIds = collect($validated['player_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $this->validatePlayersForSport($playerIds, (int) $coach->sport_id, $team->id);

        if ($playerIds->count() > $this->maxPlayersForSport((int) $coach->sport_id)) {
            throw ValidationException::withMessages([
                'player_ids' => 'Selected players exceed the maximum roster size for this sport.',
            ]);
        }

        DB::transaction(function () use ($playerIds, $team) {
            TeamPlayer::query()
                ->where('team_id', $team->id)
                ->whereNotIn('student_id', $playerIds->all())
                ->delete();

            $existingIds = TeamPlayer::query()
                ->where('team_id', $team->id)
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($playerIds->diff($existingIds) as $studentId) {
                TeamPlayer::create([
                    'team_id' => $team->id,
                    'student_id' => $studentId,
                    'jersey_number' => null,
                    'athlete_position' => null,
                ]);
            }
        });

        return back()->with('success', 'Roster updated successfully.');
    }

    public function assignAssistantCoach(Request $request, Team $team, Coach $assistantCoach)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $coach->sport_id, 403);
        abort_unless($this->coachCanManageTeam($coach->id, $team), 403);

        if ((int) $assistantCoach->id === (int) $coach->id) {
            throw ValidationException::withMessages([
                'assistant_coach_id' => 'The head coach cannot also be the assistant coach.',
            ]);
        }

        if ((int) $assistantCoach->sport_id !== (int) $coach->sport_id) {
            throw ValidationException::withMessages([
                'assistant_coach_id' => 'Only coaches from the same assigned sport can be selected.',
            ]);
        }

        $this->validateAssistantCoachAvailability((int) $assistantCoach->id, $team->id, (int) $coach->id);

        DB::transaction(function () use ($request, $team, $coach, $assistantCoach) {
            $team->syncStaffAssignments((int) $coach->id, (int) $assistantCoach->id, $request->user()?->id);
        });

        return back()->with('success', 'Assistant coach assigned successfully.');
    }

    public function removeAssistantCoach(Request $request, Team $team)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $coach->sport_id, 403);
        abort_unless($this->coachCanManageTeam($coach->id, $team), 403);

        DB::transaction(function () use ($request, $team, $coach) {
            $team->syncStaffAssignments((int) $coach->id, null, $request->user()?->id);
        });

        return back()->with('success', 'Assistant coach removed successfully.');
    }

    public function archive(Request $request, Team $team)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $this->coachCanManageTeam($coach->id, $team), 403);

        $team->update([
            'archived_at' => now(),
            'archived_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Team archived.');
    }

    public function reactivate(Request $request, Team $team)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach && $this->coachCanManageTeam($coach->id, $team), 403);

        $team->update([
            'archived_at' => null,
            'archived_by' => null,
        ]);

        return back()->with('success', 'Team reactivated.');
    }

    private function serializeSelectedTeam(Team $team): array
    {
        return [
            'id' => $team->id,
            'team_name' => $team->team_name,
            'team_avatar' => $team->team_avatar,
            'year' => $team->year,
            'description' => $team->description,
            'archived_at' => optional($team->archived_at)->toDateTimeString(),
            'head_coach' => $team->coach ? [
                'id' => $team->coach->id,
                'name' => $team->coach->full_name,
                'email' => $team->coach->user?->email,
                'avatar' => $team->coach->user?->avatar,
            ] : null,
            'assistant_coach' => $team->assistantCoach ? [
                'id' => $team->assistantCoach->id,
                'name' => $team->assistantCoach->full_name,
                'email' => $team->assistantCoach->user?->email,
                'avatar' => $team->assistantCoach->user?->avatar,
                'coach_status' => $team->assistantCoach->coach_status,
            ] : null,
            'player_ids' => $team->players->pluck('student_id')->map(fn ($id) => (int) $id)->values()->all(),
            'players' => $team->players
                ->sortBy(fn ($player) => strtolower((string) ($player->student?->last_name . ' ' . $player->student?->first_name)))
                ->values()
                ->map(fn ($player) => [
                    'id' => $player->id,
                    'student_id' => $player->student_id,
                    'name' => $player->student?->full_name,
                    'student_id_number' => $player->student?->student_id_number,
                ])
                ->all(),
        ];
    }

    private function availablePlayersForSport(int $sportId, ?int $selectedTeamId): array
    {
        return Student::query()
            ->with(['user:id,first_name,middle_name,last_name', 'appliedSport:id,name'])
            ->where('approval_status', 'approved')
            ->where('applied_sport_id', $sportId)
            ->where(function ($query) use ($selectedTeamId) {
                $query->whereDoesntHave('teams', function ($teamQuery) {
                    $teamQuery->join('teams', 'teams.id', '=', 'team_players.team_id')
                        ->whereNull('teams.archived_at');
                });

                if ($selectedTeamId) {
                    $query->orWhereHas('teams', fn ($teamQuery) => $teamQuery->where('team_id', $selectedTeamId));
                }
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'academic_level_label' => $student->academic_level_label,
                'course_or_strand' => $student->course_or_strand,
                'applied_sport' => $student->appliedSport?->name,
            ])
            ->values()
            ->all();
    }

    private function assistantCoachOptionsForSport(int $sportId, ?Team $selectedTeam, Coach $headCoach): array
    {
        $selectedTeamId = $selectedTeam?->id;
        $currentAssistantId = $selectedTeam?->assistantCoach?->id;

        $activeAssignments = Team::query()
            ->with(['sport:id,name', 'headCoachAssignment', 'assistantCoachAssignment'])
            ->where('sport_id', $sportId)
            ->whereNull('archived_at')
            ->when($selectedTeamId, fn ($query) => $query->where('id', '!=', $selectedTeamId))
            ->get(['id', 'team_name', 'sport_id', 'year']);

        $availability = [];
        foreach ($activeAssignments as $team) {
            $teamLabel = $team->team_name . (!empty($team->year) ? " - {$team->year}" : '');

            if ($team->coach_id) {
                $availability[(int) $team->coach_id] = [
                    'is_available' => false,
                    'assigned_team_name' => $team->team_name,
                    'assigned_role' => 'Head Coach',
                    'unavailable_reason' => "Assigned as Head Coach to {$teamLabel}.",
                ];
            }

            if ($team->assistant_coach_id) {
                $availability[(int) $team->assistant_coach_id] = [
                    'is_available' => false,
                    'assigned_team_name' => $team->team_name,
                    'assigned_role' => 'Assistant Coach',
                    'unavailable_reason' => "Assigned as Assistant Coach to {$teamLabel}.",
                ];
            }
        }

        return Coach::query()
            ->with('user')
            ->where('sport_id', $sportId)
            ->where('id', '!=', $headCoach->id)
            ->orderBy('id')
            ->get(['id', 'user_id', 'coach_status', 'sport_id'])
            ->map(function (Coach $coach) use ($availability, $currentAssistantId) {
                $state = $availability[$coach->id] ?? null;
                $isCurrentAssistant = $currentAssistantId && (int) $currentAssistantId === (int) $coach->id;

                return [
                    'id' => $coach->id,
                    'name' => $coach->full_name,
                    'email' => $coach->user?->email,
                    'avatar' => $coach->user?->avatar,
                    'coach_status' => $coach->coach_status,
                    'is_available' => $isCurrentAssistant ? true : ($state['is_available'] ?? true),
                    'assigned_team_name' => $isCurrentAssistant ? null : ($state['assigned_team_name'] ?? null),
                    'assigned_role' => $isCurrentAssistant ? null : ($state['assigned_role'] ?? null),
                    'unavailable_reason' => $isCurrentAssistant ? null : ($state['unavailable_reason'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    private function seasonTemplatesForCoach(Collection $teams, ?int $selectedTeamId): array
    {
        return $teams
            ->when($selectedTeamId, fn ($collection) => $collection->where('id', '!=', $selectedTeamId))
            ->sortByDesc(fn (Team $team) => (int) ($team->year ?? 0))
            ->values()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'label' => trim($team->team_name . ' ' . ($team->year ? '(' . $team->year . ')' : '')),
                'year' => $team->year,
                'player_count' => (int) $team->players->count(),
                'assistant_coach_name' => $team->assistantCoach?->full_name ?: null,
                'archived_at' => optional($team->archived_at)?->toDateTimeString(),
            ])
            ->all();
    }

    private function resolveTemplateTeam(int $coachId, int $sportId, ?int $teamId): ?Team
    {
        if (!$teamId) {
            return null;
        }

        $team = Team::query()
            ->with(['players', 'assistantCoach.user'])
            ->forCoach($coachId)
            ->where('sport_id', $sportId)
            ->find($teamId);

        if (!$team) {
            throw ValidationException::withMessages([
                'clone_from_team_id' => 'The selected team template is not available.',
            ]);
        }

        return $team;
    }

    private function validatePlayersForSport($playerIds, int $sportId, ?int $teamId): void
    {
        if ($playerIds->isEmpty()) {
            return;
        }

        $validIds = Student::query()
            ->whereIn('id', $playerIds->all())
            ->where('approval_status', 'approved')
            ->where('applied_sport_id', $sportId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== $playerIds->count()) {
            throw ValidationException::withMessages([
                'player_ids' => 'Only approved student-athletes in your assigned sport can be added.',
            ]);
        }

        $conflictIds = TeamPlayer::query()
            ->join('teams', 'teams.id', '=', 'team_players.team_id')
            ->whereNull('teams.archived_at')
            ->whereIn('team_players.student_id', $playerIds->all())
            ->when($teamId, fn ($query) => $query->where('teams.id', '!=', $teamId))
            ->pluck('team_players.student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if (!empty($conflictIds)) {
            throw ValidationException::withMessages([
                'player_ids' => 'One or more selected student-athletes are already assigned to another active team.',
            ]);
        }
    }

    private function validateAssistantCoachAvailability(int $assistantCoachId, ?int $ignoreTeamId, int $headCoachId): void
    {
        if ($assistantCoachId === $headCoachId) {
            throw ValidationException::withMessages([
                'assistant_coach_id' => 'The head coach cannot also be the assistant coach.',
            ]);
        }

        $conflict = Team::query()
            ->whereNull('archived_at')
            ->when($ignoreTeamId, fn ($query) => $query->where('id', '!=', $ignoreTeamId))
            ->where(function ($query) use ($assistantCoachId) {
                $query->whereHas('headCoachAssignment', fn ($assignmentQuery) => $assignmentQuery->where('coach_id', $assistantCoachId))
                    ->orWhereHas('assistantCoachAssignment', fn ($assignmentQuery) => $assignmentQuery->where('coach_id', $assistantCoachId));
            })
            ->first();

        if ($conflict) {
            throw ValidationException::withMessages([
                'assistant_coach_id' => 'That coach is already assigned to another active team.',
            ]);
        }
    }

    private function coachCanManageTeam(int $coachId, Team $team): bool
    {
        return Team::query()
            ->where('id', $team->id)
            ->forCoach($coachId)
            ->exists();
    }

    private function maxPlayersForSport(int $sportId): int
    {
        $sportName = strtolower((string) Sport::query()->where('id', $sportId)->value('name'));

        return match ($sportName) {
            'basketball' => 15,
            'volleyball' => 14,
            'soccer' => 30,
            default => 25,
        };
    }
}
