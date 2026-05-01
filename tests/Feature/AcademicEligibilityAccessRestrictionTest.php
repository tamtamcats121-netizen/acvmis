<?php

use App\Models\AcademicDocument;
use App\Models\AcademicDocumentType;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function makeAcademicAccessFixture(string $email = 'academic-access@example.com'): array
{
    $user = User::query()->create([
        'first_name' => 'Access',
        'last_name' => 'Student',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    $student = Student::query()->create([
        'user_id' => $user->id,
        'student_id_number' => '2026-7777',
        'date_of_birth' => '2006-01-01',
        'gender' => 'Female',
        'home_address' => 'Sample Address',
        'course_or_strand' => 'BSIT',
        'current_grade_level' => '1',
        'approval_status' => 'approved',
        'student_status' => 'Enrolled',
        'phone_number' => '09170000077',
        'emergency_contact_name' => 'Parent Student',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_phone' => '09179990077',
    ]);

    $period = AcademicPeriod::query()->create([
        'school_year' => '2026-2027',
        'term' => '1st_sem',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addDay()->toDateString(),
    ]);

    $documentType = AcademicDocumentType::query()->firstOrCreate([
        'context' => AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION,
        'code' => AcademicDocumentType::CODE_GRADE_REPORT,
    ], [
        'label' => 'Grade Report',
    ]);

    return compact('user', 'student', 'period', 'documentType');
}

it('restricts varsity pages when an active academic period has no submission', function () {
    ['user' => $user] = makeAcademicAccessFixture('restricted-missing-submission@example.com');

    $this->actingAs($user)
        ->get('/StudentAthleteDashboard')
        ->assertRedirect(route('AcademicSubmissions'));
});

it('restores varsity access when the active period has an eligible evaluation', function () {
    [
        'user' => $user,
        'student' => $student,
        'period' => $period,
        'documentType' => $documentType,
    ] = makeAcademicAccessFixture('eligible-access@example.com');

    $document = AcademicDocument::query()->create([
        'student_id' => $student->id,
        'document_type_id' => $documentType->id,
        'academic_period_id' => $period->id,
        'file_path' => 'academic_documents/eligible-access.pdf',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'review_status' => 'reviewed',
    ]);

    AcademicEligibilityEvaluation::query()->create([
        'student_id' => $student->id,
        'academic_period_id' => $period->id,
        'document_id' => $document->id,
        'gpa' => 1.75,
        'evaluation_source' => 'manual',
        'final_status' => 'eligible',
        'review_required' => false,
        'evaluated_by' => $user->id,
        'evaluated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/StudentAthleteDashboard')
        ->assertOk();
});

it('keeps varsity pages restricted while the active period evaluation is pending review', function () {
    [
        'user' => $user,
        'student' => $student,
        'period' => $period,
        'documentType' => $documentType,
    ] = makeAcademicAccessFixture('pending-review-access@example.com');

    $document = AcademicDocument::query()->create([
        'student_id' => $student->id,
        'document_type_id' => $documentType->id,
        'academic_period_id' => $period->id,
        'file_path' => 'academic_documents/pending-review-access.pdf',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'review_status' => 'needs_review',
    ]);

    AcademicEligibilityEvaluation::query()->create([
        'student_id' => $student->id,
        'academic_period_id' => $period->id,
        'document_id' => $document->id,
        'gpa' => 3.40,
        'evaluation_source' => 'manual',
        'final_status' => 'pending_review',
        'review_required' => true,
        'evaluated_by' => $user->id,
        'evaluated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/StudentAthleteDashboard')
        ->assertRedirect(route('AcademicSubmissions'));
});
