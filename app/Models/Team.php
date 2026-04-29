<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Team extends Model
{
    protected $fillable = [
        'team_name',
        'team_avatar',
        'sport_id',
        'year',
        'description',
        'archived_at',
        'archived_by',
    ];

    protected $appends = [
        'coach_id',
        'assistant_coach_id',
        'team_avatar_url',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(TeamStaffAssignment::class, 'team_id');
    }

    public function activeStaffAssignments(): HasMany
    {
        return $this->staffAssignments()->active();
    }

    public function headCoachAssignment(): HasOne
    {
        return $this->hasOne(TeamStaffAssignment::class, 'team_id')
            ->where('role', TeamStaffAssignment::ROLE_HEAD)
            ->whereNull('ends_at');
    }

    public function assistantCoachAssignment(): HasOne
    {
        return $this->hasOne(TeamStaffAssignment::class, 'team_id')
            ->where('role', TeamStaffAssignment::ROLE_ASSISTANT)
            ->whereNull('ends_at');
    }

    public function coach(): HasOneThrough
    {
        return $this->hasOneThrough(
            Coach::class,
            TeamStaffAssignment::class,
            'team_id',
            'id',
            'id',
            'coach_id'
        )
            ->where('team_staff_assignments.role', TeamStaffAssignment::ROLE_HEAD)
            ->whereNull('team_staff_assignments.ends_at');
    }

    public function assistantCoach(): HasOneThrough
    {
        return $this->hasOneThrough(
            Coach::class,
            TeamStaffAssignment::class,
            'team_id',
            'id',
            'id',
            'coach_id'
        )
            ->where('team_staff_assignments.role', TeamStaffAssignment::ROLE_ASSISTANT)
            ->whereNull('team_staff_assignments.ends_at');
    }

    public function players()
    {
        return $this->hasMany(TeamPlayer::class, 'team_id');
    }

    public function schedules()
    {
        return $this->hasMany(TeamSchedule::class, 'team_id');
    }

    public function archivedByUser()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function wellnessLogs()
    {
        return $this->hasManyThrough(
            WellnessLog::class,
            TeamSchedule::class,
            'team_id',
            'schedule_id',
            'id',
            'id'
        );
    }

    public function getCoachIdAttribute(): ?int
    {
        $assignment = $this->relationLoaded('headCoachAssignment')
            ? $this->getRelation('headCoachAssignment')
            : $this->headCoachAssignment()->first();

        return $assignment?->coach_id ? (int) $assignment->coach_id : null;
    }

    public function getAssistantCoachIdAttribute(): ?int
    {
        $assignment = $this->relationLoaded('assistantCoachAssignment')
            ? $this->getRelation('assistantCoachAssignment')
            : $this->assistantCoachAssignment()->first();

        return $assignment?->coach_id ? (int) $assignment->coach_id : null;
    }

    public function getTeamAvatarUrlAttribute(): string
    {
        return MediaUrl::public($this->attributes['team_avatar'] ?? null, '/images/default-avatar.svg');
    }

    public function scopeForCoach($query, int $coachId)
    {
        return $query->whereHas('activeStaffAssignments', function ($assignmentQuery) use ($coachId) {
            $assignmentQuery->where('coach_id', $coachId);
        });
    }

    public function scopeMissingAssistant($query)
    {
        return $query->whereDoesntHave('assistantCoachAssignment');
    }

    public function scopeWithAssistant($query)
    {
        return $query->whereHas('assistantCoachAssignment');
    }

    public function syncStaffAssignments(?int $headCoachId, ?int $assistantCoachId, ?int $createdBy = null): void
    {
        $this->syncStaffAssignmentRole(TeamStaffAssignment::ROLE_HEAD, $headCoachId, $createdBy);
        $this->syncStaffAssignmentRole(TeamStaffAssignment::ROLE_ASSISTANT, $assistantCoachId, $createdBy);
        $this->unsetRelation('headCoachAssignment');
        $this->unsetRelation('assistantCoachAssignment');
        $this->unsetRelation('coach');
        $this->unsetRelation('assistantCoach');
    }

    public function activeCoachIds(): array
    {
        $assignments = $this->relationLoaded('activeStaffAssignments')
            ? $this->getRelation('activeStaffAssignments')
            : $this->activeStaffAssignments()->get();

        return $assignments
            ->pluck('coach_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function syncStaffAssignmentRole(string $role, ?int $coachId, ?int $createdBy = null): void
    {
        $current = $this->staffAssignments()
            ->where('role', $role)
            ->whereNull('ends_at')
            ->latest('id')
            ->first();

        $normalizedCoachId = $coachId ?: null;
        $currentCoachId = $current?->coach_id ? (int) $current->coach_id : null;

        if ($currentCoachId === $normalizedCoachId) {
            return;
        }

        if ($current) {
            $current->update([
                'ends_at' => now(),
            ]);
        }

        if ($normalizedCoachId) {
            $this->staffAssignments()->create([
                'coach_id' => $normalizedCoachId,
                'role' => $role,
                'starts_at' => now(),
                'ends_at' => null,
                'created_by' => $createdBy,
            ]);
        }
    }
}
