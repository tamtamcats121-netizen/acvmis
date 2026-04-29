<?php

namespace App\Models;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\AnnouncementEvent;
use App\Models\Coach;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\UserSetting;
use App\Support\MediaUrl;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'must_change_password',
        'role',
        'account_state',
        'avatar', // added avatar
    ];

    protected $appends = [
        'full_name',
        'name',
        'account_state',
        'approval_status',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ], fn ($value) => $value !== null && trim((string) $value) !== '');

        return trim(implode(' ', $parts));
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function getAccountStateAttribute(): string
    {
        $raw = $this->attributes['account_state'] ?? null;
        return is_string($raw) && $raw !== '' ? $raw : 'active';
    }

    public function getApprovalStatusAttribute(): ?string
    {
        if (!in_array($this->role, ['student', 'student-athlete'], true)) {
            return null;
        }

        $studentApproval = Student::query()
            ->without('user')
            ->where('user_id', $this->id)
            ->value('approval_status');

        if (is_string($studentApproval) && $studentApproval !== '') {
            return $studentApproval;
        }

        return 'pending';
    }

    public function getAvatarUrlAttribute(): string
    {
        return MediaUrl::public($this->attributes['avatar'] ?? null, '/images/default-avatar.svg');
    }

    public function isActiveAccount(): bool
    {
        return $this->account_state === 'active';
    }

    public function requiresStudentApproval(): bool
    {
        return in_array($this->role, ['student', 'student-athlete'], true);
    }

    /**
     * Relation to Student
     */
    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    /**
     * Relation to Coach
     */
    public function coach()
    {
        return $this->hasOne(Coach::class, 'user_id');
    }

    /**
     * Get all teams associated with this user
     */
    public function teams()
    {
        if ($this->role === 'coach') {
            $coachId = $this->coach?->id;
            if (!$coachId) {
                return collect();
            }

            return Team::query()
                ->forCoach($coachId)
                ->get();
        } elseif ($this->role === 'student' || $this->role === 'student-athlete') {
            return Team::whereHas('players', function ($q) {
                $q->where('student_id', $this->student->id);
            })->get();
        } else {
            return collect(); // empty collection for admin or other roles
        }
    }
    public function recordedAttendances()
    {
        return $this->hasMany(ScheduleAttendance::class, 'recorded_by');
    }

    public function reviewedHealthClearances()
    {
        return $this->hasMany(AthleteHealthClearance::class, 'reviewed_by');
    }

    public function wellnessLogs()
    {
        return $this->hasMany(WellnessLog::class, 'logged_by');
    }

    public function uploadedAcademicDocuments()
    {
        return $this->hasMany(AcademicDocument::class, 'uploaded_by');
    }

    public function reviewedAcademicDocuments()
    {
        return $this->hasMany(AcademicDocument::class, 'reviewed_by');
    }

    public function academicEvaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'evaluated_by');
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'user_id');
    }

    public function createdAnnouncements()
    {
        return $this->hasMany(AnnouncementEvent::class, 'created_by');
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class, 'user_id');
    }
}
