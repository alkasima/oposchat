<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Subject;
use Illuminate\Mail\Mailables\Attachment as MailAttachment;
use Illuminate\Mail\Mailables\Content as MailContent;
use Illuminate\Mail\Mailables\Envelope as MailEnvelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Mail\Mailable;

class EmailVerification extends \Illuminate\Mail\Mailable
{
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected User $user,
        protected string $verificationUrl
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('services.ses.from_address', 'soporte@oposchat.com'), 'OposChat'),
            subject: 'Confirma tu correo y activa tu cuenta en OposChat',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification',
            with: [
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}