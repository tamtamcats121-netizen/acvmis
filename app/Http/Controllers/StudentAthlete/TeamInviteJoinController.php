<?php

namespace App\Http\Controllers\StudentAthlete;

use App\Http\Controllers\Controller;
use App\Models\AccountActionLog;
use App\Models\Sport;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamInviteJoinController extends Controller
{
    public function show(Request $request, ?string $code = null)
    {
        $user = $request->user();
        $normalizedCode = $this->normalizeCode($code);

        if (!$user || !in_array($user->role, ['student', 'student-athlete'], true)) {
            return Inertia::render('StudentAthletes/JoinTeam', [
                'code' => $normalizedCode,
                'team' => null,
                'canJoin' => false,
                'message' => 'Only student-athlete accounts can join a team using an invite link.',
            ]);
        }

        $student = $user->student;
        $team = $normalizedCode ? $this->activeInviteTeam($normalizedCode) : null;
        $message = null;
        $canJoin = false;

        if ($normalizedCode && !$team) {
            $message = 'This team invite code is no longer active.';
        } elseif ($team && !$student) {
            $message = 'Only student-athlete accounts can join a team using an invite link.';
        } elseif ($team) {
            try {
                $this->validateJoin($student, $team);
                $canJoin = true;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();
            }
        }

        return Inertia::render('StudentAthletes/JoinTeam', [
            'code' => $normalizedCode,
            'team' => $team ? $this->teamPreviewPayload($team) : null,
            'canJoin' => $canJoin,
            'message' => $message,
        ]);
    }

    public function join(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();
        if (!$user || !in_array($user->role, ['student', 'student-athlete'], true)) {
            throw ValidationException::withMessages([
                'code' => 'Only student-athlete accounts can join a team using an invite link.',
            ]);
        }

        $student = $user->student;
        if (!$student) {
            throw ValidationException::withMessages([
                'code' => 'Only student-athlete accounts can join a team using an invite link.',
            ]);
        }

        $team = $this->activeInviteTeam($this->normalizeCode($validated['code']));
        if (!$team) {
            throw ValidationException::withMessages([
                'code' => 'This team invite code is no longer active.',
            ]);
        }

        $this->validateJoin($student, $team);

        DB::transaction(function () use ($request, $student, $team) {
            TeamPlayer::query()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'student_id' => $student->id,
                ],
                [
                    'player_status' => TeamPlayer::STATUS_ACTIVE,
                    'manual_inactive' => false,
                ]
            );

            AccountActionLog::create([
                'user_id' => $student->user_id,
                'admin_id' => $request->user()?->id,
                'action' => 'roster_joined_via_invite',
                'remarks' => "Student joined {$team->team_name} using team invite code {$team->join_code}.",
            ]);
        });

        return redirect('/MyTeam?team_id=' . $team->id)
            ->with('success', "You have joined {$team->team_name}.");
    }

    private function activeInviteTeam(string $code): ?Team
    {
        if ($code === '') {
            return null;
        }

        $team = Team::query()
            ->with(['sport:id,name', 'coach.user'])
            ->where('join_code', $code)
            ->first();

        return $team?->inviteCodeIsActive() ? $team : null;
    }

    private function validateJoin(?Student $student, Team $team): void
    {
        if (!$student) {
            throw ValidationException::withMessages([
                'code' => 'Only student-athlete accounts can join a team using an invite link.',
            ]);
        }

        if ($student->approval_status !== 'approved') {
            throw ValidationException::withMessages([
                'code' => 'Your account must be approved before joining a team.',
            ]);
        }

        if ((int) $student->applied_sport_id !== (int) $team->sport_id) {
            throw ValidationException::withMessages([
                'code' => 'This invite is only for student-athletes in this sport.',
            ]);
        }

        $activeTeam = TeamPlayer::query()
            ->join('teams', 'teams.id', '=', 'team_players.team_id')
            ->where('team_players.student_id', $student->id)
            ->whereNull('teams.archived_at')
            ->select('team_players.team_id')
            ->first();

        if ($activeTeam && (int) $activeTeam->team_id !== (int) $team->id) {
            throw ValidationException::withMessages([
                'code' => 'You are already assigned to an active team.',
            ]);
        }

        if ($activeTeam && (int) $activeTeam->team_id === (int) $team->id) {
            throw ValidationException::withMessages([
                'code' => 'You are already on this team.',
            ]);
        }

        if ($team->players()->count() >= $this->maxPlayersForSport((int) $team->sport_id)) {
            throw ValidationException::withMessages([
                'code' => 'This team roster is already full.',
            ]);
        }
    }

    private function teamPreviewPayload(Team $team): array
    {
        return [
            'id' => $team->id,
            'team_name' => $team->team_name,
            'sport' => $team->sport?->name ?? 'Unknown sport',
            'year' => $team->year,
            'coach' => $team->coach
                ? trim(($team->coach->first_name ?? '') . ' ' . ($team->coach->last_name ?? ''))
                : 'Unassigned',
            'roster_count' => $team->players()->count(),
            'max_players' => $this->maxPlayersForSport((int) $team->sport_id),
        ];
    }

    private function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
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
