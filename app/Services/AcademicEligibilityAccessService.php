<?php

namespace App\Services;

use App\Models\AcademicDocument;
use App\Models\AcademicPeriod;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\Student;

class AcademicEligibilityAccessService
{
    public function evaluate(Student $student): array
    {
        $openPeriods = AcademicPeriod::query()
            ->open()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get(['id', 'school_year', 'term', 'starts_on', 'ends_on']);

        if ($openPeriods->isNotEmpty()) {
            return $this->evaluateActivePeriods($student, $openPeriods);
        }

        $latestEvaluation = AcademicEligibilityEvaluation::query()
            ->with('academicPeriod:id,school_year,term,starts_on,ends_on')
            ->where('student_id', $student->id)
            ->whereNotNull('academic_period_id')
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->first();

        $status = $latestEvaluation?->status;
        $period = $latestEvaluation?->academicPeriod;
        $periodLabel = $period
            ? trim(sprintf('%s %s', (string) $period->school_year, (string) $period->term))
            : null;

        return [
            'is_restricted' => false,
            'status' => $status,
            'message' => null,
            'has_active_period' => false,
            'active_period_id' => null,
            'has_submitted_for_active_period' => false,
            'has_eligible_evaluation_for_active_period' => false,
            'evaluation' => $latestEvaluation ? [
                'id' => $latestEvaluation->id,
                'academic_period_id' => $latestEvaluation->academic_period_id,
                'gpa' => $latestEvaluation->gpa,
                'status' => $status,
                'remarks' => $latestEvaluation->remarks,
                'evaluated_at' => optional($latestEvaluation->evaluated_at)->toDateTimeString(),
                'period_label' => $periodLabel,
            ] : null,
        ];
    }

    private function evaluateActivePeriods(Student $student, $openPeriods): array
    {
        $openPeriodIds = $openPeriods->pluck('id')->map(fn ($id) => (int) $id)->all();

        $submissions = AcademicDocument::query()
            ->periodSubmission()
            ->where('student_id', $student->id)
            ->whereIn('academic_period_id', $openPeriodIds)
            ->orderByDesc('uploaded_at')
            ->get(['id', 'academic_period_id', 'uploaded_at'])
            ->groupBy('academic_period_id');

        $evaluations = AcademicEligibilityEvaluation::query()
            ->where('student_id', $student->id)
            ->whereIn('academic_period_id', $openPeriodIds)
            ->with('academicPeriod:id,school_year,term,starts_on,ends_on')
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('academic_period_id');

        $restrictedPeriod = null;
        $restrictedStatus = null;
        $restrictedMessage = null;
        $restrictedEvaluation = null;
        $hasSubmittedForAllActivePeriods = true;
        $hasEligibleForAllActivePeriods = true;

        foreach ($openPeriods as $period) {
            $periodId = (int) $period->id;
            $periodLabel = trim(sprintf('%s %s', (string) $period->school_year, (string) $period->term));
            $latestSubmission = $submissions->get($periodId)?->sortByDesc('uploaded_at')->first();
            $latestEvaluation = $evaluations->get($periodId)?->first();
            $evaluationStatus = $latestEvaluation?->status;

            if (!$latestSubmission) {
                $hasSubmittedForAllActivePeriods = false;
                $hasEligibleForAllActivePeriods = false;

                if ($restrictedPeriod === null) {
                    $restrictedPeriod = $period;
                    $restrictedStatus = 'missing_submission';
                    $restrictedMessage = sprintf(
                        'Varsity features are temporarily limited for %s until you submit your grade report in the Academics module.',
                        $periodLabel
                    );
                }

                continue;
            }

            if ($evaluationStatus !== 'eligible') {
                $hasEligibleForAllActivePeriods = false;

                if ($restrictedPeriod === null) {
                    $restrictedPeriod = $period;
                    $restrictedEvaluation = $latestEvaluation;

                    if ($evaluationStatus === 'ineligible') {
                        $restrictedStatus = 'ineligible';
                        $restrictedMessage = sprintf(
                            'You are currently academically ineligible for %s. Varsity features are temporarily limited. Please review your Academics module.',
                            $periodLabel
                        );
                    } elseif ($evaluationStatus === 'pending_review') {
                        $restrictedStatus = 'pending_review';
                        $restrictedMessage = sprintf(
                            'Your academic submission for %s is still pending review. Varsity features will be restored once you are marked eligible.',
                            $periodLabel
                        );
                    } else {
                        $restrictedStatus = 'awaiting_evaluation';
                        $restrictedMessage = sprintf(
                            'Your academic submission for %s has been received. Varsity features remain limited until an eligible evaluation is confirmed.',
                            $periodLabel
                        );
                    }
                }
            }
        }

        if ($restrictedPeriod === null) {
            $latestEligibleEvaluation = AcademicEligibilityEvaluation::query()
                ->where('student_id', $student->id)
                ->whereIn('academic_period_id', $openPeriodIds)
                ->where('final_status', 'eligible')
                ->with('academicPeriod:id,school_year,term,starts_on,ends_on')
                ->orderByDesc('evaluated_at')
                ->orderByDesc('id')
                ->first();

            return [
                'is_restricted' => false,
                'status' => 'eligible',
                'message' => null,
                'has_active_period' => true,
                'active_period_id' => (int) ($openPeriods->first()->id ?? 0),
                'has_submitted_for_active_period' => $hasSubmittedForAllActivePeriods,
                'has_eligible_evaluation_for_active_period' => $hasEligibleForAllActivePeriods,
                'evaluation' => $latestEligibleEvaluation ? [
                    'id' => $latestEligibleEvaluation->id,
                    'academic_period_id' => $latestEligibleEvaluation->academic_period_id,
                    'gpa' => $latestEligibleEvaluation->gpa,
                    'status' => $latestEligibleEvaluation->status,
                    'remarks' => $latestEligibleEvaluation->remarks,
                    'evaluated_at' => optional($latestEligibleEvaluation->evaluated_at)->toDateTimeString(),
                    'period_label' => trim(sprintf(
                        '%s %s',
                        (string) $latestEligibleEvaluation->academicPeriod?->school_year,
                        (string) $latestEligibleEvaluation->academicPeriod?->term
                    )),
                ] : null,
            ];
        }

        $restrictedPeriodLabel = trim(sprintf(
            '%s %s',
            (string) $restrictedPeriod->school_year,
            (string) $restrictedPeriod->term
        ));

        return [
            'is_restricted' => true,
            'status' => $restrictedStatus,
            'message' => $restrictedMessage,
            'has_active_period' => true,
            'active_period_id' => (int) $restrictedPeriod->id,
            'has_submitted_for_active_period' => $hasSubmittedForAllActivePeriods,
            'has_eligible_evaluation_for_active_period' => $hasEligibleForAllActivePeriods,
            'evaluation' => $restrictedEvaluation ? [
                'id' => $restrictedEvaluation->id,
                'academic_period_id' => $restrictedEvaluation->academic_period_id,
                'gpa' => $restrictedEvaluation->gpa,
                'status' => $restrictedEvaluation->status,
                'remarks' => $restrictedEvaluation->remarks,
                'evaluated_at' => optional($restrictedEvaluation->evaluated_at)->toDateTimeString(),
                'period_label' => $restrictedPeriodLabel,
            ] : null,
        ];
    }
}
