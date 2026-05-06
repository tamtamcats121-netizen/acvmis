<?php

namespace App\Services;

use App\Models\AcademicDocument;
use App\Models\AcademicDocumentOcrRun;

class AcademicDocumentValidationService
{
    public function validate(AcademicDocument $document, AcademicDocumentOcrRun $ocrRun): array
    {
        $ocrRun->loadMissing('parsedSummary');

        $rawText = trim((string) $ocrRun->raw_text);
        $summary = $ocrRun->parsedSummary;

        if ($rawText === '') {
            return $this->result(
                status: 'manual_review',
                summary: 'No OCR text was extracted from the uploaded document.',
                flags: [[
                    'code' => 'ocr_empty',
                    'message' => 'The scan could not read any text from the uploaded file.',
                ]],
            );
        }

        if ($summary?->gwa === null) {
            return $this->result(
                status: 'manual_review',
                summary: 'The scan completed, but GPA or general average could not be extracted automatically.',
                flags: [[
                    'code' => 'gpa_missing',
                    'message' => 'No GPA or general average was detected in the document.',
                ]],
            );
        }

        $flags = [];

        if ($ocrRun->mean_confidence !== null && (float) $ocrRun->mean_confidence < 65.0) {
            $flags[] = [
                'code' => 'ocr_low_confidence',
                'message' => 'The OCR scan succeeded but confidence was low, so the detected grade should be verified.',
            ];
        }

        return $this->result(
            status: empty($flags) ? 'valid' : 'manual_review',
            summary: empty($flags)
                ? 'GPA or general average was extracted successfully from the uploaded document.'
                : 'GPA or general average was extracted, but the OCR confidence was low and should be reviewed.',
            flags: $flags,
        );
    }

    public function failedValidation(string $message): array
    {
        return $this->result(
            status: 'manual_review',
            summary: 'Automatic GPA extraction did not complete.',
            flags: [[
                'code' => 'validation_unavailable',
                'message' => $message,
            ]],
        );
    }

    private function result(string $status, string $summary, array $flags): array
    {
        return [
            'validation_status' => $status,
            'validation_summary' => $summary,
            'validation_flags' => $flags,
            'validation_checked_at' => now(),
        ];
    }
}
