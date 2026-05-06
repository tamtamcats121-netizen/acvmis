<?php

use App\Models\AcademicDocument;
use App\Models\AcademicDocumentOcrRun;
use App\Models\AcademicDocumentType;
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\GradeParsingService;
use Illuminate\Support\Facades\Hash;

function makeParsingDocumentFixture(string $email = 'parser@example.com'): AcademicDocument
{
    $user = User::query()->create([
        'first_name' => 'Parser',
        'last_name' => 'Tester',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    $student = Student::query()->create([
        'user_id' => $user->id,
        'student_id_number' => '2026-0001',
        'first_name' => 'Parser',
        'last_name' => 'Tester',
        'date_of_birth' => '2006-01-15',
        'gender' => 'Male',
        'home_address' => 'Sample Address',
        'course_or_strand' => 'BSIT',
        'current_grade_level' => '1',
        'approval_status' => 'approved',
        'student_status' => 'Enrolled',
        'phone_number' => '09170000001',
        'emergency_contact_name' => 'Parent Tester',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_phone' => '09179990001',
    ]);

    $period = AcademicPeriod::query()->create([
        'school_year' => '2026-2027',
        'term' => '1st_sem',
        'starts_on' => '2026-04-01',
        'ends_on' => '2026-06-30',
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
        'file_path' => 'academic_documents/sample-grade-report.png',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'review_status' => 'pending',
    ]);
}

it('extracts gwa from multiple real-world labels and parses subject rows', function () {
    $document = makeParsingDocumentFixture('gwa-multi@example.com');

    $ocrRun = AcademicDocumentOcrRun::query()->create([
        'academic_document_id' => $document->id,
        'ocr_engine' => 'tesseract',
        'run_status' => 'processed',
        'raw_text' => implode("\n", [
            'ASIAN COLLEGE',
            'Final Grade Report',
            'MATH101 College Algebra 3.0 1.75 PASSED',
            'ENG102 Communication Skills 3.0 2.00 PASSED',
            'General Average : 1.88',
        ]),
        'mean_confidence' => 91.5,
    ]);

    $result = app(GradeParsingService::class)->persistParsedData($ocrRun->fresh());

    expect($result['has_gwa'])->toBeTrue()
        ->and($result['subject_count'])->toBe(2)
        ->and($result['parser_status'])->toBe('parsed')
        ->and((float) $result['summary']->gwa)->toBe(1.88);
});

it('handles noisy table-like scans and still finds the average', function () {
    $document = makeParsingDocumentFixture('gwa-noisy@example.com');

    $ocrRun = AcademicDocumentOcrRun::query()->create([
        'academic_document_id' => $document->id,
        'ocr_engine' => 'tesseract',
        'run_status' => 'processed',
        'raw_text' => implode("\n", [
            'SUBJECT CODE | DESCRIPTION | UNITS | GRADE',
            'IT101 Introduction to Computing 3.0 1.50 PASS',
            'PE101 Physical Education 2.0 1.25 PASS',
            '----',
            'GWA = 1.38',
        ]),
        'mean_confidence' => 88.2,
    ]);

    $result = app(GradeParsingService::class)->persistParsedData($ocrRun->fresh());

    expect((float) $result['summary']->gwa)->toBe(1.38)
        ->and($result['subject_count'])->toBe(2)
        ->and($result['parser_confidence'])->toBeGreaterThanOrEqual(80.0);
});

it('marks partial scans as needs review instead of pretending they parsed cleanly', function () {
    $document = makeParsingDocumentFixture('gwa-partial@example.com');

    $ocrRun = AcademicDocumentOcrRun::query()->create([
        'academic_document_id' => $document->id,
        'ocr_engine' => 'tesseract',
        'run_status' => 'processed',
        'raw_text' => 'Final Grade 2.50',
        'mean_confidence' => 72.0,
    ]);

    $result = app(GradeParsingService::class)->persistParsedData($ocrRun->fresh());

    expect($result['has_gwa'])->toBeTrue()
        ->and($result['subject_count'])->toBe(0)
        ->and($result['parser_status'])->toBe('needs_review');
});

it('treats a clearly labeled standalone gwa as parsed even without subject rows', function () {
    $parsed = app(GradeParsingService::class)->parseText(implode("\n", [
        'ASIAN COLLEGE',
        'Official Grade Summary',
        'General Weighted Average: 1.88',
    ]));

    expect($parsed['gwa'])->toBe(1.88)
        ->and($parsed['subject_rows'])->toHaveCount(0)
        ->and($parsed['parser_status'])->toBe('parsed');
});

it('prefers the explicit general average label over less-specific grade labels', function () {
    $parsed = app(GradeParsingService::class)->parseText(implode("\n", [
        'General Average: 1.88',
        'Final Grade: 2.50',
    ]));

    expect($parsed['gwa'])->toBe(1.88)
        ->and($parsed['parser_status'])->toBe('parsed');
});

it('extracts general average from noisy k-12 OCR output', function () {
    $parsed = app(GradeParsingService::class)->parseText(implode("\n", [
        'Table 8. Final Grades and General Average',
        '',
        'Filipino 80 89 go | 84 85',
        '',
        '[English 89 90 92 87 90',
        '',
        'Mathematics 82 85 83 [ 83 |i',
        '',
        'pale nee 86 87 85 84 86',
        '',
        'Araling Panlipunan 90 92 91 | 89 91',
        '',
        'mean sa 89 93 90 88 90',
        '',
        'agpapakatao',
        '',
        'Edukasyong',
        '',
        'Pantahanan at 80 81 84 79 81',
        '',
        'iPangkabuhayan',
        '',
        'MAPEH 85 86 85 84 85',
        'General Average 86',
    ]));

    expect($parsed['gwa'])->toBe(86.0)
        ->and($parsed['parser_status'])->toBe('parsed');
});

it('extracts a split general average footer from semester table OCR output', function () {
    $parsed = app(GradeParsingService::class)->parseText(implode("\n", [
        'Table 9. Grade 11, 2nd Semester of ABM strand',
        'Reading and Writing Skills 80 83 82',
        'Pagbasa at Pagsusuri ng Iba\'t Ibang Teksto tungo sa Pananaliksik 86 85 86',
        'Statistics and Probability 82 87 85',
        'Physical Science 88 87 88',
        'General Average for the',
        'Semester',
        '85',
    ]));

    expect($parsed['gwa'])->toBe(85.0)
        ->and($parsed['parser_status'])->toBe('parsed');
});

it('fails cleanly for invalid formats with multiple unrelated numeric values', function () {
    $parsed = app(GradeParsingService::class)->parseText(implode("\n", [
        'Student ID: 2026-0001',
        'Issued: 04/25/2026',
        'Reference: 12345',
        'No grade summary available',
    ]));

    expect($parsed['gwa'])->toBeNull()
        ->and($parsed['subject_rows'])->toHaveCount(0)
        ->and($parsed['parser_status'])->toBe('failed');
});
