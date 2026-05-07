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

    public static function statusForGpa(?float $gpa, ?string $expectedEducationLevel = null): ?string
    {
        return self::interpretGrade($gpa, $expectedEducationLevel)['status'];
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
    public static function interpretGrade(?float $grade, ?string $expectedEducationLevel = null): array
    {
        $expectedScale = self::expectedScaleForEducationLevel($expectedEducationLevel);

        if ($grade === null) {
            return [
                'scale' => $expectedScale ?? self::SCALE_UNKNOWN,
                'value_label' => self::valueLabelForScale($expectedScale),
                'status' => null,
                'interpretation_label' => 'Pending review',
                'review_required' => true,
            ];
        }

        if (self::hasEducationLevelScaleMismatch($grade, $expectedEducationLevel)) {
            return [
                'scale' => $expectedScale ?? self::SCALE_UNKNOWN,
                'value_label' => self::valueLabelForScale($expectedScale),
                'status' => 'pending_review',
                'interpretation_label' => 'Pending Review',
                'review_required' => true,
                'scale_mismatch' => true,
                'mismatch_message' => self::scaleMismatchMessage($expectedEducationLevel, $grade),
            ];
        }

        $scale = $expectedScale ?? self::detectGradingScale($grade);

        if ($scale === self::SCALE_BASIC_EDUCATION) {
            return [
                'scale' => $scale,
                'value_label' => self::valueLabelForScale($scale),
                'status' => $grade >= 75 ? 'eligible' : 'ineligible',
                'interpretation_label' => $grade >= 75 ? 'Eligible' : 'Ineligible',
                'review_required' => false,
                'scale_mismatch' => false,
                'mismatch_message' => null,
            ];
        }

        if ($scale === self::SCALE_HIGHER_EDUCATION) {
            if ($grade <= 3.0) {
                return [
                    'scale' => $scale,
                    'value_label' => self::valueLabelForScale($scale),
                    'status' => 'eligible',
                    'interpretation_label' => 'Eligible',
                    'review_required' => false,
                    'scale_mismatch' => false,
                    'mismatch_message' => null,
                ];
            }

            if ($grade >= 5.0) {
                return [
                    'scale' => $scale,
                    'value_label' => self::valueLabelForScale($scale),
                    'status' => 'ineligible',
                    'interpretation_label' => 'Ineligible',
                    'review_required' => false,
                    'scale_mismatch' => false,
                    'mismatch_message' => null,
                ];
            }

            return [
                'scale' => $scale,
                'value_label' => self::valueLabelForScale($scale),
                'status' => 'pending_review',
                'interpretation_label' => 'Pending Review',
                'review_required' => true,
                'scale_mismatch' => false,
                'mismatch_message' => null,
            ];
        }

        return [
            'scale' => self::SCALE_UNKNOWN,
            'value_label' => self::valueLabelForScale(null),
            'status' => 'pending_review',
            'interpretation_label' => 'Pending Review',
            'review_required' => true,
            'scale_mismatch' => false,
            'mismatch_message' => null,
        ];
    }

    public static function hasEducationLevelScaleMismatch(?float $grade, ?string $expectedEducationLevel): bool
    {
        if ($grade === null) {
            return false;
        }

        $expectedScale = self::expectedScaleForEducationLevel($expectedEducationLevel);
        if ($expectedScale === null) {
            return false;
        }

        $detectedScale = self::detectGradingScale($grade);

        return $detectedScale !== self::SCALE_UNKNOWN && $detectedScale !== $expectedScale;
    }

    public static function scaleMismatchMessage(?string $expectedEducationLevel, ?float $grade = null): string
    {
        $expectedScale = self::expectedScaleForEducationLevel($expectedEducationLevel);
        $expectedLabel = self::valueLabelForScale($expectedScale);
        $valuePart = $grade !== null ? " ({$grade})" : '';

        return "The recorded academic value{$valuePart} does not match the expected {$expectedLabel} scale for this student's academic level. Manual review is required.";
    }

    /**
     * @return array{
     *     status: string|null,
     *     review_required: bool,
     *     scale_mismatch: bool,
     *     mismatch_message: string|null,
     *     remarks: string|null
     * }
     */
    public static function presentStoredEvaluation(
        ?float $grade,
        ?string $storedStatus,
        ?string $expectedEducationLevel,
        ?string $storedRemarks = null
    ): array {
        $scaleMismatch = self::hasEducationLevelScaleMismatch($grade, $expectedEducationLevel);
        $mismatchMessage = $scaleMismatch
            ? self::scaleMismatchMessage($expectedEducationLevel, $grade)
            : null;

        $status = $scaleMismatch
            ? 'pending_review'
            : ($storedStatus ?: self::statusForGpa($grade, $expectedEducationLevel));

        $remarks = $storedRemarks;
        if ($scaleMismatch && $mismatchMessage !== null) {
            $remarks = $storedRemarks
                ? trim($storedRemarks . "\n\n" . $mismatchMessage)
                : $mismatchMessage;
        }

        return [
            'status' => $status,
            'review_required' => $scaleMismatch ? true : $status === 'pending_review',
            'scale_mismatch' => $scaleMismatch,
            'mismatch_message' => $mismatchMessage,
            'remarks' => $remarks,
        ];
    }

    public static function expectedScaleForEducationLevel(?string $educationLevel): ?string
    {
        $normalized = mb_strtolower(trim((string) $educationLevel));

        return match ($normalized) {
            'senior high', 'senior_high', 'shs' => self::SCALE_BASIC_EDUCATION,
            'college', 'higher education', 'higher_education' => self::SCALE_HIGHER_EDUCATION,
            default => null,
        };
    }

    public static function valueLabelForScale(?string $scale): string
    {
        return match ($scale) {
            self::SCALE_BASIC_EDUCATION => 'GWA',
            self::SCALE_HIGHER_EDUCATION => 'GPA',
            default => 'Academic Result',
        };
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
