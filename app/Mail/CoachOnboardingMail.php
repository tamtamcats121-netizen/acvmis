<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoachOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $loginUrl;
    public string $activationUrl;

    public function __construct(User $user, string $loginUrl, string $activationUrl)
    {
        $this->user = $user;
        $this->loginUrl = $loginUrl;
        $this->activationUrl = $activationUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your AC-VMIS Coach Account Is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.coach-onboarding',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
