<?php

use App\Mail\PasswordResetLinkMail;
use App\Models\User;
use App\Services\BrevoTransactionalMailer;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

it('sends a password reset link through the transactional mailer', function () {
    $sent = [];

    app()->instance(BrevoTransactionalMailer::class, mock(BrevoTransactionalMailer::class, function (MockInterface $mock) use (&$sent) {
        $mock->shouldReceive('sendMailable')
            ->once()
            ->andReturnUsing(function (string $email, PasswordResetLinkMail $mailable, ?string $name = null) use (&$sent) {
                $sent[] = compact('email', 'mailable', 'name');
            });
    }));

    $user = User::factory()->create([
        'email' => 'athlete@example.com',
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    $response = $this->from('/forgot-password')->post('/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertRedirect('/forgot-password');
    $response->assertSessionHas('success');

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
    expect($sent)->toHaveCount(1)
        ->and($sent[0]['email'])->toBe($user->email)
        ->and($sent[0]['name'])->toBe($user->name)
        ->and($sent[0]['mailable'])->toBeInstanceOf(PasswordResetLinkMail::class)
        ->and($sent[0]['mailable']->resetUrl)->toContain('/reset-password/')
        ->and($sent[0]['mailable']->resetUrl)->toContain('email=athlete%40example.com');
});

it('returns a validation error when password reset email delivery fails', function () {
    app()->instance(BrevoTransactionalMailer::class, mock(BrevoTransactionalMailer::class, function (MockInterface $mock) {
        $mock->shouldReceive('sendMailable')
            ->once()
            ->andThrow(new RuntimeException('Mail provider unavailable.'));
    }));

    $user = User::factory()->create([
        'email' => 'coach@example.com',
        'role' => 'coach',
        'account_state' => 'active',
    ]);

    $response = $this->from('/forgot-password')->post('/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertRedirect('/forgot-password');
    $response->assertSessionHasErrors('email');
});
