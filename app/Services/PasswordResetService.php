<?php

namespace App\Services;

use App\Mail\PasswordResetMail;
use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Services\NotificacaoEmailService;
use App\Support\PlatformMail;
use App\Support\PlatformSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetService
{
    /**
     * @return string|null Link gerado (útil em ambiente local com driver log)
     */
    public function solicitar(string $email): ?string
    {
        $email = $this->normalizarEmail($email);
        $usuario = $this->buscarPorEmail($email);

        if (! $usuario) {
            return null;
        }

        $config = PlatformMail::configuracaoRecuperacaoSenha();

        if (! $config['ativo']) {
            return null;
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $link = route('password.reset', ['token' => $token, 'email' => $email]);
        $nome = method_exists($usuario, 'nomeExibicao') ? $usuario->nomeExibicao() : ($usuario->nome ?? $email);
        $expira = $config['expira'].' minutos';

        $vars = [
            'nome' => $nome,
            'link' => $link,
            'app_name' => PlatformSettings::appName(),
            'expira' => $expira,
        ];

        $notificacao = app(NotificacaoEmailService::class);

        if ($notificacao->enviar('auth.reset_senha', $email, $vars, $link)) {
            return $link;
        }

        $config = PlatformMail::configuracaoRecuperacaoSenha();

        PlatformMail::apply();

        try {
            Mail::to($email)->send(new PasswordResetMail(
                assunto: PlatformMail::substituirPlaceholders($config['assunto'], $vars),
                corpoTexto: PlatformMail::substituirPlaceholders($config['corpo'], $vars),
                link: $link,
            ));
        } catch (Throwable $e) {
            Log::error('Falha ao enviar e-mail de redefinição de senha', [
                'email' => $email,
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $link;
    }

    public function redefinir(string $email, string $token, string $password): bool
    {
        $email = $this->normalizarEmail($email);
        $registro = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $registro || ! Hash::check($token, $registro->token)) {
            return false;
        }

        $config = PlatformMail::configuracaoRecuperacaoSenha();

        if (Carbon::parse($registro->created_at)->addMinutes($config['expira'])->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return false;
        }

        $usuario = $this->buscarPorEmail($email);

        if (! $usuario) {
            return false;
        }

        $usuario->forceFill(['password' => $password])->save();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return true;
    }

    public function buscarPorEmail(string $email): ?Authenticatable
    {
        $email = $this->normalizarEmail($email);

        return Usuario::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first()
            ?: SubUsuario::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
    }

    private function normalizarEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
