<?php

namespace App\Services;

use App\Models\AcademicDocument;
use App\Models\AcademicDocumentOcrRun;
use App\Models\AcademicEligibilityEvaluation;
use Illuminate\Support\Facades\DB;

class AcademicEligibilityRuleEngine
{
    public function evaluateDocument(AcademicDocument $document, AcademicDocumentOcrRun $ocrRun): ?AcademicEligibilityEvaluation
    {
        if (!$document->academic_period_id) {
            return null;
        }

        $ocrRun->loadMissing('parsedSummary');
        $summary = $ocrRun->parsedSummary;

        if (!$summary || $summary->gwa === null) {
            return null;
        }

        $grade = (float) $summary->gwa;
        $interpretation = AcademicEligibilityEvaluation::interpretGrade(
            $grade,
            $document->student?->education_level
        );

        return DB::transaction(function () use ($document, $ocrRun, $grade, $interpretation) {
            $evaluation = AcademicEligibilityEvaluation::query()->updateOrCreate(
                [
                    'student_id' => $document->student_id,
                    'academic_period_id' => $document->academic_period_id,
                ],
                [
                    'document_id' => $document->id,
                    'academic_document_ocr_run_id' => $ocrRun->id,
                    'gpa' => $grade,
                    'evaluation_source' => 'rule_based',
                    'final_status' => $interpretation['status'],
                    'review_required' => $interpretation['review_required'],
                    'evaluated_by' => null,
                    'evaluated_at' => now(),
                    'remarks' => $this->buildRemarks($grade, $interpretation),
                ]
            );

            return $evaluation->fresh();
        });
    }

    private function buildRemarks(float $grade, array $interpretation): string
    {
        if (!empty($interpretation['scale_mismatch']) && !empty($interpretation['mismatch_message'])) {
            return $interpretation['mismatch_message'];
        }

        $scaleLabel = match ($interpretation['scale']) {
            AcademicEligibilityEvaluation::SCALE_BASIC_EDUCATION => 'Basic Education percentage scale',
            AcademicEligibilityEvaluation::SCALE_HIGHER_EDUCATION => 'Higher Education numerical scale',
            default => 'Unknown grading scale',
        };

        return "{$interpretation['value_label']}: {$grade}. System interpretation: {$interpretation['interpretation_label']} ({$scaleLabel}).";
    }
}
