<?php

namespace App\Services;

use App\Models\AcademicDocumentOcrRun;
use App\Models\AcademicDocumentParsedSummary;
use Illuminate\Support\Collection;

class GradeParsingService
{
    public function persistParsedData(AcademicDocumentOcrRun $ocrRun): array
    {
        $ocrRun->parsedSummary()?->delete();

        $parsed = $this->parseText((string) $ocrRun->raw_text);

        $summary = $ocrRun->parsedSummary()->create([
            'gwa' => $parsed['gwa'],
            'total_units' => $parsed['subject_rows']->sum(fn (array $row) => $row['units'] ?? 0),
            'parser_status' => $parsed['parser_status'],
            'parser_confidence' => $parsed['parser_confidence'],
        ]);

        return [
            'summary' => $summary,
            'parser_status' => $parsed['parser_status'],
            'parser_confidence' => $parsed['parser_confidence'],
            'subject_count' => $parsed['subject_rows']->count(),
            'has_gwa' => $parsed['gwa'] !== null,
        ];
    }

    /**
     * @return array{
     *     gwa: float|null,
     *     subject_rows: Collection<int, array<string, mixed>>,
     *     parser_confidence: float,
     *     parser_status: string
     * }
     */
    public function parseText(string $text): array
    {
        $normalizedText = trim($text);
        $gwa = $this->extractGwa($normalizedText);
        $subjectRows = $this->extractSubjectRows($normalizedText);
        $parserConfidence = $this->parserConfidence($gwa, $subjectRows, $normalizedText);
        $parserStatus = $this->resolveParserStatus($gwa, $subjectRows, $parserConfidence, $normalizedText);

        return [
            'gwa' => $gwa,
            'subject_rows' => $subjectRows,
            'parser_confidence' => $parserConfidence,
            'parser_status' => $parserStatus,
        ];
    }

    private function extractGwa(string $text): ?float
    {
        if ($text === '') {
            return null;
        }

        $normalized = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $summaryCandidates = $this->extractSummaryLineCandidates($text);
        if ($summaryCandidates !== []) {
            return $this->resolveBestSummaryCandidate($summaryCandidates);
        }

        $labeledCandidates = [];

        $labelPatterns = [
            '/\b(?:gwa|gpa|qpa|gen(?:eral)?\.?\s*avg|general weighted average|general average|final average|final grade|average)\b[^\S\r\n]*[:=\-]?[^\S\r\n]*([0-9]{1,3}(?:\.[0-9]{1,2})?)/i',
            '/([0-9]{1,3}(?:\.[0-9]{1,2})?)[^\S\r\n]*(?:<-|—|-)?[^\S\r\n]*\b(?:gwa|gpa|general weighted average|general average|final average|average)\b/i',
        ];

        foreach ($labelPatterns as $pattern) {
            if (preg_match_all($pattern, $normalized, $matches, PREG_SET_ORDER) >= 1) {
                foreach ($matches as $match) {
                    foreach (array_slice($match, 1) as $candidate) {
                        if (!is_string($candidate) || trim($candidate) === '') {
                            continue;
                        }

                        $grade = $this->normalizeGradeCandidate($candidate);
                        if ($grade !== null) {
                            $labeledCandidates[] = $grade;
                        }
                    }
                }
            }
        }

        $resolvedLabeledGrade = $this->resolveUniqueGradeCandidate($labeledCandidates);
        if ($resolvedLabeledGrade !== null || $labeledCandidates !== []) {
            return $resolvedLabeledGrade;
        }

        $lines = preg_split('/\R+/', $normalized) ?: [];
        $lineCandidates = [];
        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            if (!preg_match('/\b(?:gwa|gpa|general weighted average|general average|final average|average)\b/i', $trimmed)) {
                continue;
            }

            if (preg_match_all('/([0-9]{1,3}(?:\.[0-9]{1,2})?)/', $trimmed, $matches) >= 1) {
                foreach ($matches[1] as $candidate) {
                    $grade = $this->normalizeGradeCandidate($candidate);
                    if ($grade !== null) {
                        $lineCandidates[] = $grade;
                    }
                }
            }
        }

        $resolvedLineGrade = $this->resolveUniqueGradeCandidate($lineCandidates);
        if ($resolvedLineGrade !== null || $lineCandidates !== []) {
            return $resolvedLineGrade;
        }

        return $this->fallbackSingleSummaryValue($normalized);
    }

    /**
     * @return array<int, array{value: float, score: int}>
     */
    private function extractSummaryLineCandidates(string $text): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $candidates = [];

        foreach ($lines as $index => $line) {
            $sanitized = $this->sanitizeLine((string) $line);
            if ($sanitized === '' || !$this->containsSummaryKeyword($sanitized)) {
                continue;
            }

            $values = $this->extractNumericCandidatesFromLine($sanitized);
            if ($values !== []) {
                $candidate = end($values);
                if ($candidate !== false) {
                    $candidates[] = [
                        'value' => $candidate,
                        'score' => $this->summaryLineScore($sanitized, count($values)),
                    ];
                }

                continue;
            }

            $nextValue = $this->extractNearbySummaryValue($lines, $index);
            if ($nextValue !== null) {
                $candidates[] = [
                    'value' => $nextValue,
                    'score' => $this->summaryLineScore($sanitized, 1) - 1,
                ];
            }
        }

        return $candidates;
    }

    private function containsSummaryKeyword(string $line): bool
    {
        return preg_match(
            '/\b(?:general weighted average|general average|gen(?:eral)?\.?\s*avg|gwa|gpa|qpa|final average|final grade|average)\b/i',
            $line
        ) === 1;
    }

    /**
     * @return array<int, float>
     */
    private function extractNumericCandidatesFromLine(string $line): array
    {
        if (preg_match_all('/([0-9]{1,3}(?:\.[0-9]{1,2})?)/', $line, $matches) < 1) {
            return [];
        }

        return collect($matches[1])
            ->map(fn ($candidate) => $this->normalizeGradeCandidate((string) $candidate))
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();
    }

    private function extractNearbySummaryValue(array $lines, int $index): ?float
    {
        for ($offset = 1; $offset <= 3; $offset++) {
            $candidateLine = $lines[$index + $offset] ?? null;
            if (!is_string($candidateLine)) {
                continue;
            }

            $sanitized = $this->sanitizeLine($candidateLine);
            if ($sanitized === '') {
                continue;
            }

            $values = $this->extractNumericCandidatesFromLine($sanitized);
            if (count($values) === 1) {
                return $values[0];
            }

            if (count($values) === 0 && $this->isSummaryContinuationLine($sanitized)) {
                continue;
            }

            break;
        }

        return null;
    }

    private function isSummaryContinuationLine(string $line): bool
    {
        return preg_match(
            '/\b(?:semester|trimester|term|grading period|school year|sy|for the|finals?)\b/i',
            $line
        ) === 1;
    }

    private function summaryLineScore(string $line, int $numericCount): int
    {
        $score = 0;
        $normalized = mb_strtolower($line);

        if (preg_match('/^(general weighted average|general average|gen\.?\s*avg|gwa|gpa|qpa|final average|final grade)\b/i', $line)) {
            $score += 5;
        }

        if (str_contains($normalized, 'general average') || str_contains($normalized, 'general weighted average')) {
            $score += 4;
        }

        if (str_contains($normalized, 'gwa') || str_contains($normalized, 'gen avg')) {
            $score += 3;
        }

        if (str_contains($normalized, 'table') || str_contains($normalized, 'final grades')) {
            $score -= 5;
        }

        if ($numericCount === 1) {
            $score += 3;
        } elseif ($numericCount > 2) {
            $score -= 3;
        }

        return $score;
    }

    /**
     * @param  array<int, array{value: float, score: int}>  $candidates
     */
    private function resolveBestSummaryCandidate(array $candidates): ?float
    {
        usort($candidates, function (array $left, array $right) {
            $scoreComparison = $right['score'] <=> $left['score'];
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return $right['value'] <=> $left['value'];
        });

        $best = $candidates[0] ?? null;
        if (!$best || $best['score'] < 1) {
            return null;
        }

        return round((float) $best['value'], 2);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function extractSubjectRows(string $text): Collection
    {
        if ($text === '') {
            return collect();
        }

        $lines = preg_split('/\R+/', $text) ?: [];

        return collect($lines)
            ->map(fn ($line) => $this->sanitizeLine((string) $line))
            ->filter()
            ->reject(fn (string $line) => $this->isNoiseLine($line))
            ->map(function (string $line) {
                $tokens = preg_split('/\s+/', $line) ?: [];
                if (count($tokens) < 3) {
                    return null;
                }

                $remarks = null;
                $trailingText = end($tokens);
                if (is_string($trailingText) && preg_match('/^(passed|failed|pass|fail|inc|incomplete)$/i', $trailingText)) {
                    $remarks = trim((string) array_pop($tokens));
                }

                $numericTail = [];
                while (!empty($tokens)) {
                    $candidate = end($tokens);
                    if (!is_string($candidate) || !$this->isNumericToken($candidate)) {
                        break;
                    }

                    array_unshift($numericTail, array_pop($tokens));
                }

                if (count($numericTail) < 1 || count($tokens) < 1) {
                    return null;
                }

                $gradeValue = $this->normalizeGradeCandidate((string) array_pop($numericTail));
                if ($gradeValue === null) {
                    return null;
                }

                $units = null;
                if (!empty($numericTail)) {
                    $unitsCandidate = $this->normalizeUnitsCandidate((string) array_pop($numericTail));
                    $units = $unitsCandidate;
                }

                $subjectCode = null;
                $firstToken = $tokens[0] ?? null;
                if (is_string($firstToken) && preg_match('/^[A-Z]{2,}[A-Z0-9-]*$/', $firstToken)) {
                    $subjectCode = array_shift($tokens);
                }

                $subjectName = trim(implode(' ', $tokens));
                if ($subjectName === '' || mb_strlen($subjectName) < 3) {
                    return null;
                }

                return [
                    'subject_code_raw' => $subjectCode,
                    'subject_name_raw' => $subjectName,
                    'units' => $units,
                    'grade_value' => $gradeValue,
                    'remarks_raw' => $remarks,
                    'row_confidence' => $this->rowConfidence($subjectCode, $units, $gradeValue, $remarks),
                    'is_flagged' => $subjectCode === null && $units === null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $subjectRows
     */
    private function parserConfidence(?float $gwa, Collection $subjectRows, string $text): float
    {
        $confidence = 35.0;

        if ($gwa !== null) {
            $confidence += 35.0;
        }

        if ($subjectRows->isNotEmpty()) {
            $confidence += min(25.0, $subjectRows->count() * 5.0);
        }

        $flaggedCount = $subjectRows->where('is_flagged', true)->count();
        $confidence -= min(20.0, $flaggedCount * 5.0);

        if (preg_match('/\b(?:gwa|gpa|general weighted average|general average|average)\b/i', $text)) {
            $confidence += 5.0;
        }

        return max(0.0, min(98.0, round($confidence, 2)));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $subjectRows
     */
    private function resolveParserStatus(?float $gwa, Collection $subjectRows, float $parserConfidence, string $text): string
    {
        if ($gwa === null && $subjectRows->isEmpty()) {
            return 'failed';
        }

        if ($gwa !== null && $parserConfidence >= 80.0) {
            return 'parsed';
        }

        if (
            $gwa !== null
            && $parserConfidence >= 75.0
            && $this->hasStrongSummaryLabel($text)
            && ($subjectRows->isEmpty() || $subjectRows->count() >= 4)
        ) {
            return 'parsed';
        }

        return 'needs_review';
    }

    private function hasStrongSummaryLabel(string $text): bool
    {
        return preg_match(
            '/\b(?:gwa|gpa|qpa|general weighted average|general average|gen\.?\s*avg|final average|final grade)\b/i',
            $text
        ) === 1;
    }

    /**
     * @param  array<int, float>  $candidates
     */
    private function resolveUniqueGradeCandidate(array $candidates): ?float
    {
        $normalized = collect($candidates)
            ->map(fn ($value) => round((float) $value, 2))
            ->unique()
            ->values();

        if ($normalized->count() !== 1) {
            return null;
        }

        return (float) $normalized->first();
    }

    private function sanitizeLine(string $line): string
    {
        $line = html_entity_decode($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $line = preg_replace('/[|]+/', ' ', $line) ?? $line;
        $line = preg_replace('/[_]+/', ' ', $line) ?? $line;
        $line = preg_replace('/[^\PC\s]/u', ' ', $line) ?? $line;
        $line = preg_replace('/\s+/', ' ', $line) ?? $line;

        return trim($line);
    }

    private function isNoiseLine(string $line): bool
    {
        return preg_match(
            '/\b(?:student no|student id|name|semester|school year|registrar|transcript|report card|final grade report|page \d+|subject code|units|grade|gwa|gpa|general weighted average|general average|final average)\b/i',
            $line
        ) === 1;
    }

    private function isNumericToken(string $value): bool
    {
        return preg_match('/^[0-9]{1,3}(?:\.[0-9]{1,2})?$/', trim($value)) === 1;
    }

    private function normalizeGradeCandidate(string $value): ?float
    {
        $value = trim(str_replace(',', '.', $value));
        if (!$this->isNumericToken($value)) {
            return null;
        }

        $number = round((float) $value, 2);
        if ($number < 0 || $number > 100) {
            return null;
        }

        return $number;
    }

    private function normalizeUnitsCandidate(string $value): ?float
    {
        $number = $this->normalizeGradeCandidate($value);

        if ($number === null || $number > 15) {
            return null;
        }

        return $number;
    }

    private function fallbackSingleSummaryValue(string $text): ?float
    {
        if (preg_match_all('/\b([0-9]{1,3}(?:\.[0-9]{1,2})?)\b/', $text, $matches) < 1) {
            return null;
        }

        $candidates = collect($matches[1])
            ->map(fn ($candidate) => $this->normalizeGradeCandidate((string) $candidate))
            ->filter();

        if ($candidates->count() !== 1) {
            return null;
        }

        return (float) $candidates->first();
    }

    private function rowConfidence(?string $subjectCode, ?float $units, ?float $gradeValue, ?string $remarks): float
    {
        $confidence = 60.0;

        if ($subjectCode !== null) {
            $confidence += 10.0;
        }

        if ($units !== null) {
            $confidence += 10.0;
        }

        if ($gradeValue !== null) {
            $confidence += 15.0;
        }

        if ($remarks !== null) {
            $confidence += 5.0;
        }

        return min(95.0, $confidence);
    }
}
