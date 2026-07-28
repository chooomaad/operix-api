<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email d'activation : « Votre espace Operix est prêt » + lien d'activation.
 * Ne contient JAMAIS de mot de passe — l'utilisateur définit son accès via le lien.
 */
class ActivationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public string $activationUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre espace Operix est prêt');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.activation');
    }
}
