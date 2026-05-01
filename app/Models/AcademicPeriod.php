<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_year',
        'term',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    protected $appends = [
        'status',
    ];

    public function getStatusAttribute(): string
    {
        $today = now()->startOfDay();
        $startsOn = $this->starts_on?->copy()->startOfDay();
        $endsOn = $this->ends_on?->copy()->startOfDay();

        if ($startsOn && $today->lt($startsOn)) {
            return 'draft';
        }

        if ($endsOn && $today->gt($endsOn)) {
            return 'closed';
        }

        return 'open';
    }

    public function scopeOpen($query)
    {
        $today = now()->toDateString();
        return $query
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today);
    }

    public function documents()
    {
        return $this->hasMany(AcademicDocument::class, 'academic_period_id');
    }

    public function evaluations()
    {
        return $this->hasMany(AcademicEligibilityEvaluation::class, 'academic_period_id');
    }
}
