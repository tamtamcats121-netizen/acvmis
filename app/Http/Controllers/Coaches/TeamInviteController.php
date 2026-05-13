<?php

namespace App\Http\Controllers\Coaches;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamInviteController extends Controller
{
    public function generate(Request $request, Team $team)
    {
        $this->authorizeCoachInviteAccess($request, $team);

        if ($team->inviteCodeIsActive()) {
            return back()->with('success', 'Team invite code is already active.');
        }

        $team->assignFreshJoinCode();

        return back()->with('success', 'Team invite code generated.');
    }

    public function regenerate(Request $request, Team $team)
    {
        $this->authorizeCoachInviteAccess($request, $team);

        $team->assignFreshJoinCode();

        return back()->with('success', 'Team invite code regenerated.');
    }

    public function disable(Request $request, Team $team)
    {
        $this->authorizeCoachOwnership($request, $team);

        $team->forceFill([
            'join_code_enabled' => false,
        ])->save();

        return back()->with('success', 'Team invite code disabled.');
    }

    private function authorizeCoachInviteAccess(Request $request, Team $team): void
    {
        $this->authorizeCoachOwnership($request, $team);

        if (!$team->isCurrentYearActive()) {
            throw ValidationException::withMessages([
                'join_code' => 'Invite codes are only available for current active teams.',
            ]);
        }
    }

    private function authorizeCoachOwnership(Request $request, Team $team): void
    {
        $coach = $request->user()?->coach;
        abort_unless($coach, 403);

        $canManage = Team::query()
            ->whereKey($team->id)
            ->forCoach((int) $coach->id)
            ->exists();

        abort_unless($canManage, 403);
    }
}
