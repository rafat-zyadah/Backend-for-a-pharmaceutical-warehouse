<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupervisorPasswordRecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $supervisor,
        public string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pharmacy Warehouse — Supervisor Password Recovery',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.supervisor-password-recovery',
        );
    }
}
