<?php

namespace App\Services;

use App\Jobs\EnviarNotificacaoEmailJob;
use App\Models\EmailNotificacaoLog;
use App\Models\EmailTemplate;
use App\Models\Usuario;
use App\Support\PlatformMail;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Mail;

class NotificacaoEmailService
{
    public function enfileirar(string $slug, string $destinatario, array $vars = [], ?string $link = null): void
    {
        if (blank($destinatario)) {
            return;
        }

        EnviarNotificacaoEmailJob::dispatch($slug, $destinatario, $vars, $link);
    }

    public function enfileirarParaAdmins(string $slug, array $vars = [], ?string $link = null): void
    {
        Usuario::query()
            ->where('tipo', 'admin')
            ->where('ativo', true)
            ->pluck('email')
            ->filter()
            ->unique()
            ->each(fn (string $email) => $this->enfileirar($slug, $email, $vars, $link));
    }

    public function enviar(string $slug, string $destinatario, array $vars = [], ?string $link = null): bool
    {
        if (blank($destinatario)) {
            return false;
        }

        $template = EmailTemplate::porSlug($slug);

        if (! $template || ! $template->ativo) {
            EmailNotificacaoLog::create([
                'template_slug' => $slug,
                'destinatario' => $destinatario,
                'assunto' => '',
                'status' => 'ignorado',
                'erro' => 'Template inativo ou inexistente.',
            ]);

            return false;
        }

        $vars = $this->varsPadrao($vars, $link);
        $assunto = PlatformMail::substituirPlaceholders($template->assunto, $vars);
        $corpo = PlatformMail::substituirPlaceholders($template->corpo, $vars);
        $botaoTexto = filled($template->botao_texto)
            ? PlatformMail::substituirPlaceholders($template->botao_texto, $vars)
            : null;
        $botaoUrl = $link ?: ($vars['link'] ?? null);

        try {
            PlatformMail::apply();

            Mail::to($destinatario)->send(new \App\Mail\NotificacaoPlataformaMail(
                assunto: $assunto,
                corpoTexto: $corpo,
                botaoTexto: $botaoTexto,
                botaoUrl: filled($botaoUrl) ? (string) $botaoUrl : null,
            ));

            EmailNotificacaoLog::create([
                'template_slug' => $slug,
                'destinatario' => $destinatario,
                'assunto' => $assunto,
                'status' => 'enviado',
            ]);

            return true;
        } catch (\Throwable $e) {
            EmailNotificacaoLog::create([
                'template_slug' => $slug,
                'destinatario' => $destinatario,
                'assunto' => $assunto,
                'status' => 'erro',
                'erro' => $e->getMessage(),
            ]);

            report($e);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $vars
     * @return array<string, mixed>
     */
    private function varsPadrao(array $vars, ?string $link): array
    {
        return array_merge([
            'app_name' => PlatformSettings::appName(),
            'ano' => now()->format('Y'),
            'data' => now()->format('d/m/Y H:i'),
            'link' => $link ?? config('app.url'),
        ], $vars);
    }
}
