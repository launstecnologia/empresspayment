<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $assunto,
        public string $corpoTexto,
        public string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        $logo = PlatformSettings::logoUrl('default');
        $logoUrl = $logo && ! str_starts_with($logo, 'http') ? url($logo) : $logo;

        return new Content(
            view: 'emails.layouts.plataforma',
            with: [
                'corpoTexto' => $this->corpoTexto,
                'botaoTexto' => 'Redefinir senha',
                'botaoUrl' => $this->link,
                'appName' => PlatformSettings::appName(),
                'logoUrl' => $logoUrl,
                'themeColor' => PlatformSettings::themeColor(),
            ],
        );
    }
}
