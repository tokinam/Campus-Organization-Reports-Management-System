<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Part\DataPart;

class ConfirmIdentityMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $userName,
        public string $verificationUrl,
        public int $expiryMinutes = 60,
        public ?string $previewLogoUrl = null,
    ) {}

    public function build(): static
    {
        if ($this->previewLogoUrl === null) {
            $this->withSymfonyMessage(static function ($message): void {
                $logo = new DataPart(
                    file_get_contents(public_path('images/osdw.logo.jpg')),
                    'osdw-logo.jpg',
                    'image/jpeg',
                );
                $logo->asInline()->setContentId('osdw-logo@orgtrack');
                $message->addPart($logo);
            });
        }

        return $this;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your identity | OrgTrack');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confirm-identity',
            with: [
                'userName' => $this->userName,
                'verificationUrl' => $this->verificationUrl,
                'expiryMinutes' => $this->expiryMinutes,
                'logoUrl' => $this->previewLogoUrl ?? 'cid:osdw-logo@orgtrack',
            ],
        );
    }
}
