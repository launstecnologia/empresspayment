<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class PlatformMail
{
    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('platform_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $setting = PlatformSettings::get();
        $mailer = $setting->mail_mailer ?: (string) config('mail.default', 'log');

        if ($mailer === 'smtp' && ! $setting->mail_host) {
            $mailer = (string) config('mail.default', 'log');
        }

        config(['mail.default' => $mailer]);

        if ($setting->mail_host) {
            config([
                'mail.mailers.smtp.host' => $setting->mail_host,
                'mail.mailers.smtp.port' => $setting->mail_port ?: 587,
                'mail.mailers.smtp.encryption' => $setting->mail_encryption ?: null,
                'mail.mailers.smtp.username' => $setting->mail_username,
            ]);

            if (filled($setting->mail_password)) {
                config(['mail.mailers.smtp.password' => $setting->mail_password]);
            }
        }

        $fromAddress = $setting->mail_from_address ?: config('mail.from.address');
        $fromName = $setting->mail_from_name ?: PlatformSettings::appName();

        if ($fromAddress) {
            config([
                'mail.from.address' => $fromAddress,
                'mail.from.name' => $fromName,
            ]);
        }

        // Força o Mail Manager a recriar o transport com as novas configurações
        try {
            app('mail.manager')->purge('smtp');
        } catch (\Throwable) {
            // ignora se o mailer ainda não foi instanciado
        }
    }

    /**
     * @return array{assunto: string, corpo: string, expira: int, ativo: bool}
     */
    public static function configuracaoRecuperacaoSenha(): array
    {
        $setting = PlatformSettings::get();
        $appName = PlatformSettings::appName();

        $assunto = $setting->mail_reset_assunto ?: 'Redefinição de senha — {app_name}';
        $corpo = $setting->mail_reset_corpo ?: implode("\n", [
            'Olá {nome},',
            '',
            'Recebemos uma solicitação para redefinir sua senha na plataforma {app_name}.',
            '',
            'Acesse o link abaixo (válido por {expira}):',
            '{link}',
            '',
            'Se você não solicitou esta alteração, ignore este e-mail.',
        ]);

        return [
            'ativo' => (bool) ($setting->mail_reset_ativo ?? true),
            'assunto' => str_replace('{app_name}', $appName, $assunto),
            'corpo' => $corpo,
            'expira' => (int) ($setting->mail_reset_expira_minutos ?: 60),
        ];
    }

    public static function substituirPlaceholders(string $texto, array $vars): string
    {
        foreach ($vars as $chave => $valor) {
            $texto = str_replace('{'.$chave.'}', (string) $valor, $texto);
        }

        return $texto;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultsMail(): array
    {
        return [
            'mail_mailer' => config('mail.default', 'smtp'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_port' => (int) config('mail.mailers.smtp.port', 587),
            'mail_encryption' => config('mail.mailers.smtp.encryption') ?: 'tls',
            'mail_username' => config('mail.mailers.smtp.username'),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
            'mail_reset_ativo' => true,
            'mail_reset_expira_minutos' => 60,
            'mail_reset_assunto' => 'Redefinição de senha — {app_name}',
            'mail_reset_corpo' => null,
        ];
    }
}
