<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AcademicEligibilityEvaluation extends Model
{
    use HasFactory;

    private static ?bool $hasLegacyStatusColumn = null;

    public const GPA_ELIGIBLE_MAX = 3.0;
    public const GPA_PROBATION_MAX = 4.99;
    public const SCALE_BASIC_EDUCATION = 'basic_education';
    public const SCALE_HIGHER_EDUCATION = 'higher_education';
    public const SCALE_UNKNOWN = 'unknown';

    protected $fillable = [
        'student_id',
        'academic_period_id',
        'document_id',
        'academic_document_ocr_run_id',
        'gpa',
        'evaluation_source',
        'final_status',
        'review_required',
        'evaluated_by',
        'evaluated_at',
        'remarks',
    ];

    protected $casts = [
        'gpa' => 'decimal:2',
        'review_required' => 'boolean',
        'evaluated_at' => 'datetime',
    ];

    protected $appends = [
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (AcademicEligibilityEvaluation $evaluation) {
            if ($evaluation->academic_period_id === null) {
                throw new \InvalidArgumentException('Academic eligibility evaluations must be linked to an academic period.');
            }

            $resolvedStatus = $evaluation->final_status
                ?: self::statusForGpa($evaluation->gpa !== null ? (float) $evaluation->gpa : null);

            if ($resolvedStatus !== null && self::usesLegacyStatusColumn()) {
                $evaluation->setAttribute('status', self::legacyStatusValue($resolvedStatus));
            }
        });
    }

    private static function usesLegacyStatusColumn(): bool
    {
        if (self::$hasLegacyStatusColumn === null) {
            self::$hasLegacyStatusColumn = Schema::hasColumn('academic_eligibility_evaluations', 'status');
        }

        return self::$hasLegacyStatusColumn;
    }

    public static function statusForGpa(?float $gpa): ?string
    {
        return self::interpretGrade($gpa)['status'];
    }

    public static function statusCaseSql(string $alias = 'e'): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        return "CASE
            WHEN {$prefix}gpa IS NULL THEN NULL
            WHEN {$prefix}gpa BETWEEN 75 AND 100 THEN 'eligible'
            WHEN {$prefix}gpa BETWEEN 1 AND 3 THEN 'eligible'
            WHEN {$prefix}gpa >= 5 THEN 'ineligible'
            WHEN {$prefix}gpa BETWEEN 0 AND 74.99 THEN 'ineligible'
            ELSE 'pending_review'
        END";
    }

    /**
     * @return array{
     *     scale: string,
     *     value_label: string,
     *     status: string|null,
     *     interpretation_label: string,
     *     review_required: bool
     * }
     */
    public static function interpretGrade(?float $grade): array
    {
        if ($grade === null) {
            return [
                'scale' => self::SCALE_UNKNOWN,
                'value_label' => 'Grade',
                'status' => null,
                'interpretation_label' => 'Pending review',
                'review_required' => true,
            ];
        }

        $scale = self::detectGradingScale($grade);

        if ($scale === self::SCALE_BASIC_EDUCATION) {
            return [
                'scale' => $scale,
                'value_label' => 'Average',
                'status' => $grade >= 75 ? 'eligible' : 'ineligible',
                'interpretation_label' => $grade >= 75 ? 'Eligible' : 'Ineligible',
                'review_required' => false,
            ];
        }

        if ($scale === self::SCALE_HIGHER_EDUCATION) {
            if ($grade <= 3.0) {
                return [
                    'scale' => $scale,
                    'value_label' => 'GPA',
                    'status' => 'eligible',
                    'interpretation_label' => 'Eligible',
                    'review_required' => false,
                ];
            }

            if ($grade >= 5.0) {
                return [
                    'scale' => $scale,
                    'value_label' => 'GPA',
                    'status' => 'ineligible',
                    'interpretation_label' => 'Ineligible',
                    'review_required' => false,
                ];
            }

            return [
                'scale' => $scale,
                'value_label' => 'GPA',
                'status' => 'pending_review',
                'interpretation_label' => 'Pending Review',
                'review_required' => true,
            ];
        }

        return [
            'scale' => self::SCALE_UNKNOWN,
            'value_label' => 'Grade',
            'status' => 'pending_review',
            'interpretation_label' => 'Pending Review',
            'review_required' => true,
        ];
    }

    public static function detectGradingScale(?float $grade): string
    {
        if ($grade === null) {
            return self::SCALE_UNKNOWN;
        }

        if ($grade >= 1 && $grade <= 5) {
            return self::SCALE_HIGHER_EDUCATION;
        }

        if ($grade >= 0 && $grade <= 100) {
            return self::SCALE_BASIC_EDUCATION;
        }

        return self::SCALE_UNKNOWN;
    }

    public static function legacyStatusValue(?string $status): ?string
    {
        return match ($status) {
            'eligible' => 'eligible',
            'ineligible' => 'ineligible',
            'pending_review' => 'probation',
            default => $status,
        };
    }

    public function getStatusAttribute(): ?string
    {
        if (!empty($this->final_status)) {
            return (string) $this->final_status;
        }

        return self::statusForGpa($this->gpa !== null ? (float) $this->gpa : null);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function document()
    {
        return $this->belongsTo(AcademicDocument::class, 'document_id');
    }

    public function ocrRun()
    {
        return $this->belongsTo(AcademicDocumentOcrRun::class, 'academic_document_ocr_run_id');
    }
}
