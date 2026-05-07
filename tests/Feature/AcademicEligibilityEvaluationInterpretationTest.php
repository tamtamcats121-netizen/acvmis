<?php

use App\Models\AcademicEligibilityEvaluation;

it('classifies higher education grades immediately when clearly eligible or ineligible', function () {
    expect(AcademicEligibilityEvaluation::interpretGrade(1.75))
        ->toMatchArray([
            'scale' => AcademicEligibilityEvaluation::SCALE_HIGHER_EDUCATION,
            'status' => 'eligible',
            'review_required' => false,
        ]);

    expect(AcademicEligibilityEvaluation::interpretGrade(5.00))
        ->toMatchArray([
            'scale' => AcademicEligibilityEvaluation::SCALE_HIGHER_EDUCATION,
            'status' => 'ineligible',
            'review_required' => false,
        ]);
});

it('keeps only unclear higher education values in pending review', function () {
    $interpretation = AcademicEligibilityEvaluation::interpretGrade(4.00);

    expect($interpretation['scale'])->toBe(AcademicEligibilityEvaluation::SCALE_HIGHER_EDUCATION)
        ->and($interpretation['status'])->toBe('pending_review')
        ->and($interpretation['review_required'])->toBeTrue();
});

it('classifies basic education averages without manual review when clear', function () {
    expect(AcademicEligibilityEvaluation::interpretGrade(89.5))
        ->toMatchArray([
            'scale' => AcademicEligibilityEvaluation::SCALE_BASIC_EDUCATION,
            'status' => 'eligible',
            'review_required' => false,
        ]);

    expect(AcademicEligibilityEvaluation::interpretGrade(72.0))
        ->toMatchArray([
            'scale' => AcademicEligibilityEvaluation::SCALE_BASIC_EDUCATION,
            'status' => 'ineligible',
            'review_required' => false,
        ]);

    expect(AcademicEligibilityEvaluation::interpretGrade(86.0))
        ->toMatchArray([
            'scale' => AcademicEligibilityEvaluation::SCALE_BASIC_EDUCATION,
            'status' => 'eligible',
            'review_required' => false,
        ]);
});

it('flags education-level scale mismatches for manual review', function () {
    $interpretation = AcademicEligibilityEvaluation::interpretGrade(88.0, 'College');

    expect($interpretation['scale'])->toBe(AcademicEligibilityEvaluation::SCALE_HIGHER_EDUCATION)
        ->and($interpretation['value_label'])->toBe('GPA')
        ->and($interpretation['status'])->toBe('pending_review')
        ->and($interpretation['review_required'])->toBeTrue()
        ->and($interpretation['scale_mismatch'])->toBeTrue()
        ->and($interpretation['mismatch_message'])->toContain('expected GPA scale');
});
