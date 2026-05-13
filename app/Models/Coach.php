<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Team;
use App\Models\Sport;
use App\Models\TeamStaffAssignment;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone_number',
        'date_of_birth',
        'gender',
        'home_address',
        'coach_status',
        'sport_id',
    ];

    protected $with = ['user'];

    protected $appends = [
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
    ];

    /**
     * Relation to the user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFirstNameAttribute(): ?string
    {
        return $this->user?->first_name;
    }

    public function getMiddleNameAttribute(): ?string
    {
        return $this->user?->middle_name;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->user?->last_name;
    }

    public function getFullNameAttribute(): string
    {
        return $this->user?->full_name ?? '';
    }

    /**
     * Teams where this coach is the head coach
     */
    public function headTeams()
    {
        return $this->belongsToMany(Team::class, 'team_staff_assignments', 'coach_id', 'team_id')
            ->wherePivot('role', TeamStaffAssignment::ROLE_HEAD)
            ->wherePivotNull('ends_at');
    }

    /**
     * Teams where this coach is the assistant coach
     */
    public function assistantTeams()
    {
        return $this->belongsToMany(Team::class, 'team_staff_assignments', 'coach_id', 'team_id')
            ->wherePivot('role', TeamStaffAssignment::ROLE_ASSISTANT)
            ->wherePivotNull('ends_at');
    }

    public function staffAssignments()
    {
        return $this->hasMany(TeamStaffAssignment::class, 'coach_id');
    }

    public function trainingRequirements()
    {
        return $this->hasMany(TrainingRequirement::class, 'coach_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id');
    }
}
