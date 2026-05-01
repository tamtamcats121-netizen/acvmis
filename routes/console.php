<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use App\Mail\AccountPendingApprovalMail;
use App\Models\AcademicDocument;
use App\Models\User;
use App\Models\Team;
use App\Services\SystemNotificationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:test-pending {email} {--name=Test User}', function (string $email) {
    $name = (string) $this->option('name');

    $user = new User([
        'name' => $name,
        'email' => $email,
        'role' => 'student-athlete',
        'status' => 'pending',
    ]);

    try {
        app(SystemNotificationService::class)->sendEmail($email, new AccountPendingApprovalMail($user), $name, [
            'defer' => false,
            'context' => [
                'communication' => 'console_mail_test_pending',
            ],
        ]);
        $this->info("Pending approval test email sent to {$email}");
    } catch (\Throwable $e) {
        $this->error('Failed to send test email.');
        $this->line($e->getMessage());
    }
})->purpose('Send a pending-approval test email to verify mail configuration');

Artisan::command('storage:cleanup-orphans {--execute : Actually delete orphan files}', function () {
    $referenced = collect()
        ->merge(User::query()->whereNotNull('avatar')->pluck('avatar'))
        ->merge(Team::query()->whereNotNull('team_avatar')->pluck('team_avatar'))
        ->merge(AcademicDocument::query()->whereNotNull('file_path')->pluck('file_path'))
        ->filter()
        ->map(fn ($p) => trim((string) $p))
        ->unique()
        ->values();

    $allFiles = collect(Storage::disk('public')->allFiles())
        ->filter(fn ($p) => !str_starts_with((string) $p, '.'))
        ->values();

    $orphans = $allFiles->diff($referenced)->values();
    $this->info("Referenced files: {$referenced->count()}");
    $this->info("Total files on disk: {$allFiles->count()}");
    $this->warn("Orphan candidates: {$orphans->count()}");

    if ($orphans->isEmpty()) {
        return;
    }

    foreach ($orphans as $file) {
        $this->line($file);
    }

    if (!$this->option('execute')) {
        $this->comment('Dry run only. Re-run with --execute to delete listed files.');
        return;
    }

    foreach ($orphans as $file) {
        Storage::disk('public')->delete($file);
    }

    $this->info("Deleted {$orphans->count()} orphan files.");
})->purpose('Detect and optionally delete orphan files from storage/app/public');
