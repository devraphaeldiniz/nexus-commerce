<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $otpCode
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seu código de ativação - Nexus Commerce',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
            <div style='font-family: Arial, sans-serif; background: #0a0a0a; color: #ffffff; padding: 40px 20px; text-align: center;'>
                <div style='max-width: 480px; margin: 0 auto; background: #171717; border: 1px solid #262626; border-radius: 20px; padding: 32px;'>
                    <h2 style='color: #6366f1; margin-bottom: 8px;'>Nexus Commerce</h2>
                    <p style='color: #a3a3a3; font-size: 14px;'>Olá, <strong>{$this->name}</strong>!</p>
                    <p style='color: #d4d4d4; font-size: 14px; line-height: 1.5;'>
                        Use o código de 6 dígitos abaixo para confirmar sua identidade e criar sua conta:
                    </p>
                    <div style='margin: 28px 0; background: #000; border: 1px dashed #6366f1; border-radius: 12px; padding: 16px;'>
                        <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #10b981; font-family: monospace;'>
                            {$this->otpCode}
                        </span>
                    </div>
                    <p style='color: #ef4444; font-size: 12px; font-weight: bold;'>
                        Atenção: Este código expira em 10 minutos. Se não for validado, seu pré-cadastro será descartado.
                    </p>
                </div>
            </div>
            ",
        );
    }
}
