<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\AccountActionLog;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Services\SystemNotificationService;
use App\Services\TeamPlayerStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CoachTeamController extends Controller
{
    public function __construct(
        private SystemNotificationService $notifications,
        private TeamPlayerStatusService $teamPlayerStatuses,
    )
    {
    }

    private function teamAccessStatus($coach): array
    {
        $sportId = (int) ($coach?->sport_id ?? 0);
        $sportName = $coach?->sport?->name;
        $currentYear = (int) now()->year;

        $activeSportTeam = $sportId > 0
            ? Team::query()
                ->where('sport_id', $sportId)
                ->whereNull('archived_at')
                ->orderByDesc('year')
                ->orderBy('team_name')
                ->first(['id', 'team_name', 'sport_id', 'year'])
            : null;

        return [
            'has_coach_profile' => (bool) $coach,
            'sport_id' => $sportId ?: null,
            'sport_name' => $sportName,
            'current_year' => $currentYear,
            'can_create_team' => $sportId > 0 && !$activeSportTeam,
            'active_sport_team' => $activeSportTeam ? [
                'id' => $activeSportTeam->id,
                'team_name' => $activeSportTeam->team_name,
                'year' => $activeSportTeam->year,
            ] : null,
        ];
    }

    public function index()
    {
        $coach = request()->user()?->coach;
        $teamAccessStatus = $this->teamAccessStatus($coach);

        if (!$coach) {
            return Inertia::render('Coaches/CoachTeam', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'currentUserId' => request()->user()?->id,
                'teamAccessStatus' => $teamAccessStatus,
            ]);
        }

        $teams = Team::query()
            ->with('sport:id,name')
            ->forCoach($coach->id)
            ->orderBy('team_name')
            ->get(['id', 'team_name', 'sport_id']);

        if ($teams->isEmpty()) {
            return Inertia::render('Coaches/CoachTeam', [
                'team' => null,
                'teams' => [],
                'selectedTeamId' => null,
                'currentUserId' => request()->user()?->id,
                'teamAccessStatus' => $teamAccessStatus,
            ]);
        }

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) request()->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = Team::query()
            ->with([
                'sport',
                'coach.user',
                'assistantCoach.user',
                'players.student.user',
            ])
            ->forCoach($coach->id)
            ->find($selectedTeamId);

        if ($team) {
            $team->players->each(fn (TeamPlayer $player) => $this->teamPlayerStatuses->sync($player));
            $team->load([
                'sport',
                'coach.user',
                'assistantCoach.user',
                'players.student.user',
            ]);
        }

        if ($team) {
            $team = [
                'id' => $team->id,
                'team_name' => $team->team_name,
                'team_avatar' => $team->team_avatar,
                'sport' => $team->sport,
                'year' => $team->year,
                'coach' => $team->coach, // will have first_name, last_name, etc.
                'assistantCoach' => $team->assistantCoach,
                'players' => $team->players->values(),
                'invite' => [
                    'code' => $team->join_code,
                    'enabled' => (bool) $team->join_code_enabled,
                    'expires_at' => optional($team->join_code_expires_at)?->toIso8601String(),
                    'is_available' => $team->isCurrentYearActive(),
                    'is_active' => $team->inviteCodeIsActive(),
                    'join_url' => $team->join_code
                        ? route('student.team_invites.show', ['code' => $team->join_code])
                        : null,
                ],
            ];
        }

        return Inertia::render('Coaches/CoachTeam', [
            'team' => $team,
            'teams' => $teams->map(function (Team $team) {
                return [
                    'id' => $team->id,
                    'team_name' => $team->team_name,
                    'sport' => $team->sport?->name ?? $team->sport_id ?? 'unknown',
                ];
            })->values(),
            'selectedTeamId' => $selectedTeamId,
            'currentUserId' => request()->user()?->id,
            'teamAccessStatus' => $teamAccessStatus,
        ]);
    }

    public function printRoster(Request $request)
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $teams = Team::query()
            ->forCoach($coach->id)
            ->orderBy('team_name')
            ->get(['id']);

        abort_unless($teams->isNotEmpty(), 403);

        $teamIds = $teams->pluck('id')->all();
        $selectedTeamId = (int) $request->query('team_id', 0);
        if (!$selectedTeamId || !in_array($selectedTeamId, $teamIds, true)) {
            $selectedTeamId = $teamIds[0];
        }

        $team = Team::query()
            ->with([
                'sport',
                'coach.user',
                'assistantCoach.user',
                'players.student.user',
            ])
            ->forCoach($coach->id)
            ->find($selectedTeamId);
        abort_unless($team, 404);

        $players = $team->players
            ->sortBy(function ($player) {
                return strtolower(($player->student?->last_name ?? '') . ' ' . ($player->student?->first_name ?? ''));
            })
            ->values()
            ->map(fn ($player) => [
                'name' => trim(($player->student?->first_name ?? '') . ' ' . ($player->student?->last_name ?? '')),
                'student_id_number' => $player->student?->student_id_number,
                'jersey_number' => $player->jersey_number,
                'athlete_position' => $player->athlete_position,
                'player_status' => $player->player_status ?? 'active',
            ])
            ->all();

        return view('print.team-roster', [
            'contextLabel' => 'Coach Report',
            'generatedAt' => now()->format('M j, Y g:i A'),
            'team' => [
                'team_name' => $team->team_name,
                'sport' => $team->sport?->name ?? 'Unknown',
                'year' => $team->year,
                'coach' => trim(($team->coach?->first_name ?? '') . ' ' . ($team->coach?->last_name ?? '')) ?: 'Unassigned',
                'assistant' => trim(($team->assistantCoach?->first_name ?? '') . ' ' . ($team->assistantCoach?->last_name ?? '')) ?: 'Unassigned',
            ],
            'players' => $players,
        ]);
    }

    public function updatePlayerPosition(Request $request, TeamPlayer $teamPlayer)
    {
        $coach = $request->user()?->coach;
        if (!$coach) {
            abort(403);
        }

        $teamPlayer->load(['team', 'student']);
        $team = $teamPlayer->team;
        if (!$team || !in_array($coach->id, $team->activeCoachIds(), true)) {
            abort(403);
        }

        $validated = $request->validate([
            'athlete_position' => 'nullable|string|max:100',
        ]);

        $position = trim((string) ($validated['athlete_position'] ?? ''));
        $previousPosition = trim((string) ($teamPlayer->athlete_position ?? ''));
        $teamPlayer->update([
            'athlete_position' => $position === '' ? null : $position,
        ]);

        $studentUserId = $teamPlayer->student?->user_id;
        if ($studentUserId) {
            $before = $previousPosition !== '' ? $previousPosition : 'none';
            $after = $position !== '' ? $position : 'none';

            AccountActionLog::create([
                'user_id' => (int) $studentUserId,
                'admin_id' => $request->user()?->id,
                'action' => 'roster_position_updated',
                'remarks' => "Roster position updated for {$teamPlayer->student?->full_name} in {$team->team_name}: {$before} -> {$after}.",
            ]);
        }

        if ($studentUserId) {
            $message = $position === ''
                ? "Your team position in {$team->team_name} was cleared by your coach."
                : "Your team position in {$team->team_name} was set to {$position}.";

            $this->notifications->announce(
                (int) $studentUserId,
                'Team Position Update',
                $message,
                'system',
                $request->user()?->id,
                'notify_attendance_exceptions'
            );
        }

        return back()->with('success', 'Player position updated.');
    }

    public function updatePlayerStatus(Request $request, TeamPlayer $teamPlayer)
    {
        throw ValidationException::withMessages([
            'player_status' => 'Player status is managed automatically by academic eligibility and admin deactivation.',
        ]);
    }

}
