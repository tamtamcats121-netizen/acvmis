<?php

use App\Models\AcademicDocument;
use App\Models\AcademicDocumentOcrRun;
use App\Models\AcademicDocumentType;
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicDocumentValidationService;
use App\Services\GradeParsingService;
use Illuminate\Support\Facades\Hash;

function makeAcademicDocumentFixture(): AcademicDocument
{
    $user = User::query()->create([
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'email' => 'juan@example.com',
        'password' => Hash::make('password'),
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    $student = Student::query()->create([
        'user_id' => $user->id,
        'student_id_number' => '023840234',
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'date_of_birth' => '2005-10-10',
        'gender' => 'Male',
        'home_address' => 'Sample Address',
        'course_or_strand' => 'BSCS',
        'current_grade_level' => '1',
        'approval_status' => 'approved',
        'student_status' => 'Enrolled',
        'phone_number' => '09170000011',
        'emergency_contact_name' => 'Maria Cruz',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_phone' => '09179990011',
    ]);

    $period = AcademicPeriod::query()->create([
        'school_year' => '2026-2027',
        'term' => '2nd_sem',
        'starts_on' => '2026-04-01',
        'ends_on' => '2026-05-31',
    ]);

    $documentType = AcademicDocumentType::query()->firstOrCreate([
        'context' => AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION,
        'code' => AcademicDocumentType::CODE_GRADE_REPORT,
    ], [
        'label' => 'Grade Report',
    ]);

    return AcademicDocument::query()->create([
        'student_id' => $student->id,
        'document_type_id' => $documentType->id,
        'academic_period_id' => $period->id,
        'file_path' => 'academic_documents/sample.png',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'review_status' => 'pending',
    ]);
}

it('marks a scanned grade report valid when gpa or gwa is extracted', function () {
    $document = makeAcademicDocumentFixture();

    $ocrRun = AcademicDocumentOcrRun::query()->create([
        'academic_document_id' => $document->id,
        'ocr_engine' => 'tesseract',
        'run_status' => 'processed',
        'raw_text' => implode("\n", [
            'MATH101 Mathematics 3.0 1.75 PASSED',
            'GWA: 1.75',
        ]),
        'mean_confidence' => 90,
    ]);

    app(GradeParsingService::class)->persistParsedData($ocrRun);

    $result = app(AcademicDocumentValidationService::class)->validate(
        $document,
        $ocrRun->fresh(['parsedSummary'])
    );

    expect($result['validation_status'])->toBe('valid')
        ->and($result['validation_flags'])->toBe([])
        ->and($result['validation_summary'])->toContain('GPA or general average was extracted successfully');
});

it('flags a scanned grade report for manual review when gpa extraction fails', function () {
    $document = makeAcademicDocumentFixture();

    $ocrRun = AcademicDocumentOcrRun::query()->create([
        'academic_document_id' => $document->id,
        'ocr_engine' => 'tesseract',
        'run_status' => 'processed',
        'raw_text' => 'This document has subject rows but no general average value.',
        'mean_confidence' => 90,
    ]);

    app(GradeParsingService::class)->persistParsedData($ocrRun);

    $result = app(AcademicDocumentValidationService::class)->validate(
        $document,
        $ocrRun->fresh(['parsedSummary'])
    );

    expect($result['validation_status'])->toBe('manual_review')
        ->and(collect($result['validation_flags'])->pluck('code')->all())->toContain('gpa_missing')
        ->and($result['validation_summary'])->toContain('GPA or general average could not be extracted automatically.')
        ->and(collect($result['validation_flags'])->pluck('message')->implode(' '))->toContain('No GPA or general average was detected');
});
