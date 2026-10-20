<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResendService
{
    public static function sendEmail(string $to, string $subject, string $htmlContent): bool
    {
        $apiKey = env('RESEND_API_KEY');
        $from = env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev');
        $fromName = env('MAIL_FROM_NAME', 'Nexus Commerce');

        if (!$apiKey) {
            Log::warning("RESEND_API_KEY nao configurada. E-mail simulado para {$to}: {$subject}");
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->post('https://api.resend.com/emails', [
                'from'    => "{$fromName} <{$from}>",
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $htmlContent,
            ]);

            if ($response->successful()) {
                Log::info("E-mail enviado via Resend com sucesso para: {$to}");
                return true;
            } else {
                Log::error("Falha ao enviar e-mail via Resend: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Excecao ao disparar Resend: " . $e->getMessage());
            return false;
        }
    }

    public static function generateOtp(string $email): string
    {
        // Remove codigos anteriores do mesmo email
        \Illuminate\Support\Facades\DB::table('email_verifications')->where('email', $email)->delete();

        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        \Illuminate\Support\Facades\DB::table('email_verifications')->insert([
            'email'      => $email,
            'code'       => $code,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = "
        <div style='font-family: Arial, sans-serif; background-color: #0c1222; color: #f8fafc; padding: 30px; border-radius: 12px; max-width: 500px; margin: auto;'>
            <h2 style='color: #38bdf8; text-align: center; margin-bottom: 20px;'>Nexus Commerce ⚡</h2>
            <p style='font-size: 14px; color: #94a3b8;'>Seu código de verificação de segurança é:</p>
            <div style='background-color: #1e293b; border: 2px dashed #06b6d4; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #38bdf8;'>{$code}</span>
            </div>
            <p style='font-size: 12px; color: #64748b;'>Este código expira em 15 minutos. Se você não solicitou este código, desconsidere este e-mail.</p>
        </div>";

        self::sendEmail($email, "{$code} é o seu código de verificação - Nexus Commerce", $html);

        return $code;
    }
}
