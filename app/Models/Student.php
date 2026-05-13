<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TeamPlayer;
use App\Models\Team;
use App\Models\Sport;

class Student extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'student_id_number',
        'date_of_birth',
        'gender',
        'home_address',
        'course_or_strand',
        'current_grade_level',
        'approval_status',
        'applied_sport_id',
        'student_status',
        'phone_number',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'height',
        'weight',
    ];

    protected $with = ['user'];

    protected $appends = [
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
        'education_level',
        'academic_level_label',
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

    public function getEducationLevelAttribute(): ?string
    {
        $raw = trim((string) ($this->current_grade_level ?? ''));
        if ($raw === '') {
            return null;
        }

        $numeric = (int) preg_replace('/[^0-9]/', '', $raw);
        if ($numeric >= 11) {
            return 'Senior High';
        }

        return 'College';
    }

    public function getApprovalStatusAttribute(?string $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return 'pending';
    }

    public function getAcademicLevelLabelAttribute(): ?string
    {
        $raw = trim((string) ($this->current_grade_level ?? ''));
        if ($raw === '') {
            return null;
        }

        return match ($raw) {
            '11' => '11 - Senior High',
            '12' => '12 - Senior High',
            '1' => 'First Year College',
            '2' => 'Second Year College',
            '3' => 'Third Year College',
            '4' => 'Fourth Year College',
            default => $raw,
        };
    }

    /**
     * Teams the student belongs to (via pivot table team_players)
     */
    public function teams()
    {
        return $this->hasMany(TeamPlayer::class, 'student_id');
    }

    public function attendances()
    {
        return $this->hasMany(ScheduleAttendance::class, 'student_id');
    }

    public function trainingRequirements()
    {
        return $this->hasMany(TrainingRequirement::class, 'student_id');
    }

    public function appliedSport()
    {
        return $this->belongsTo(Sport::class, 'applied_sport_id');
    }

    public function academicDocuments()
    {
        return $this->hasMany(AcademicDocument::class, 'student_id');
    }

    public function studentDocuments()
    {
        return $this->hasMany(StudentDocument::class, 'student_id');
    }

    public function registrationDocuments()
    {
        return $this->studentDocuments()->registration();
    }

    public function registrationAcademicDocuments()
    {
        return $this->academicDocuments()->registration();
    }

    public function periodSubmissionDocuments()
    {
        return $this->academicDocuments()->periodSubmission();
    }

    public function latestAcademicDocument()
    {
        return $this->hasOne(AcademicDocument::class, 'student_id')
            ->whereIn('document_type_id', AcademicDocumentType::query()
                ->where('context', AcademicDocumentType::CONTEXT_REGISTRATION)
                ->select('id'))
            ->latestOfMany();
    }

    public function latestRegistrationDocument()
    {
        return $this->hasOne(StudentDocument::class, 'student_id')
            ->whereIn('document_type_id', DocumentType::query()
                ->where('context', DocumentType::CONTEXT_REGISTRATION)
                ->select('id'))
            ->latestOfMany();
    }

    public function academicEvaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'student_id');
    }
}
