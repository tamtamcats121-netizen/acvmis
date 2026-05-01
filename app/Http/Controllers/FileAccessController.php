<?php

namespace App\Http\Controllers;

use App\Models\AcademicDocument;
use App\Models\Coach;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileAccessController extends Controller
{
    public function academic(AcademicDocument $document)
    {
        abort_unless($this->canAccessStudentFile((int) $document->student_id), 403);
        $path = (string) $document->file_path;
        abort_if($path === '' || !Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, basename($path), [
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    private function canAccessStudentFile(int $studentId): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if (in_array($user->role, ['student', 'student-athlete'], true)) {
            return (int) ($user->student?->id ?? 0) === $studentId;
        }

        if ($user->role === 'coach') {
            $coachId = (int) (Coach::where('user_id', $user->id)->value('id') ?? 0);
            if ($coachId <= 0) {
                return false;
            }

            return Team::query()
                ->forCoach($coachId)
                ->whereHas('players', fn ($q) => $q->where('student_id', $studentId))
                ->exists();
        }

        return false;
    }
}
