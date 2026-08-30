<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de reinitialisation du PIN.
 *
 * Transport-agnostique : passe par le mailer configure (resend en production,
 * log/array en test). Le token n'apparait QUE dans l'URL du lien — jamais en
 * texte, jamais le PIN, jamais le hash.
 */
class PinResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $resetUrl,
        public readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Réinitialisation de votre PIN Operix');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pin-reset', with: [
            'name'       => $this->name,
            'resetUrl'   => $this->resetUrl,
            'ttlMinutes' => $this->ttlMinutes,
        ]);
    }
}
