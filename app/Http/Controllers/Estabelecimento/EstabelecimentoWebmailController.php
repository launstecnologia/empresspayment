<?php

namespace App\Http\Controllers\Estabelecimento;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
use App\Services\DirectAdminService;
use App\Services\EmailPlataformaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EstabelecimentoWebmailController extends Controller
{
    public function __construct(
        private readonly EmailPlataformaService $emailPlataforma,
    ) {}

    /**
     * Cria manualmente a conta de e-mail da plataforma para o estabelecimento.
     */
    public function criar(Request $request, Estabelecimento $estabelecimento)
    {
        abort_unless(blank($estabelecimento->webmail_email), 422, 'Este estabelecimento já possui e-mail da plataforma.');

        $dados = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/i'],
        ]);

        try {
            $this->emailPlataforma->provisionar($estabelecimento, $dados['username']);
        } catch (\Throwable $e) {
            return back()->withErrors(['username' => $e->getMessage()])->withInput();
        }

        $email = $estabelecimento->fresh()->webmail_email;

        return redirect()->route('estabelecimentos.show', $estabelecimento)
            ->with('status', "E-mail {$email} criado com sucesso.");
    }

    /**
     * Abre uma página intermediária que faz login automático (SSO) no Roundcube.
     * O servidor busca o token CSRF do Roundcube antes de passar ao browser.
     */
    public function sso(Estabelecimento $estabelecimento)
    {
        abort_unless(filled($estabelecimento->webmail_email), 404, 'Nenhuma caixa de e-mail configurada.');
        abort_unless(filled($estabelecimento->webmail_senha), 404, 'Senha do e-mail não disponível.');

        $webmailUrl = rtrim((string) config('directadmin.webmail_url'), '/');
        $loginUrl   = $webmailUrl . '/';

        // Busca a página de login do Roundcube para extrair o token CSRF
        $rcToken = null;
        try {
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->timeout(8)
                ->get($loginUrl);

            if ($response->ok()) {
                // Roundcube embeds: <input type="hidden" name="_token" value="...">
                if (preg_match('/name="_token"\s+value="([^"]+)"/', $response->body(), $m)) {
                    $rcToken = $m[1];
                }
            }
        } catch (\Throwable) {
            // Se não conseguir buscar o token, tenta sem ele (versões antigas do RC)
        }

        return view('estabelecimento.webmail-sso', [
            'webmailUrl' => $webmailUrl,
            'email'      => $estabelecimento->webmail_email,
            'senha'      => $estabelecimento->webmail_senha,
            'rcToken'    => $rcToken,
        ]);
    }

    /**
     * Reconfigura o forwarder do e-mail da plataforma:
     * deleta o forwarder antigo e recria com cópia local.
     */
    public function reconfigurarForwarder(Request $request, Estabelecimento $estabelecimento)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);
        abort_unless(filled($estabelecimento->webmail_email), 422, 'Nenhum e-mail da plataforma configurado.');
        abort_unless(filled($estabelecimento->email), 422, 'E-mail original do estabelecimento não informado.');

        $da       = app(DirectAdminService::class);
        $username = Str::before($estabelecimento->webmail_email, '@');

        // Deleta forwarder existente (ignora erro se não existir)
        try {
            $da->excluirForwarderPlataforma($username);
        } catch (\Throwable) {}

        // Recria com cópia local
        $ok = $da->redirecionarEmailPlataforma($username, $estabelecimento->email);

        if (! $ok) {
            return redirect()->route('estabelecimentos.show', $estabelecimento)
                ->withErrors(['webmail' => 'Não foi possível reconfigurar o forwarder no servidor.']);
        }

        return redirect()->route('estabelecimentos.show', $estabelecimento)
            ->with('status', 'Forwarder reconfigurado com sucesso. Agora e-mails ficam com cópia no Roundcube e são encaminhados para ' . $estabelecimento->email);
    }

    /**
     * Altera a senha da caixa de e-mail no DirectAdmin e atualiza o banco.
     */
    public function trocarSenha(Request $request, Estabelecimento $estabelecimento)
    {
        abort_unless(filled($estabelecimento->webmail_email), 422, 'Nenhuma caixa de e-mail configurada.');

        $dados = $request->validate([
            'senha'              => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'senha_confirmation' => ['required', 'string'],
        ]);

        try {
            $this->emailPlataforma->alterarSenha($estabelecimento, $dados['senha']);
        } catch (\Throwable $e) {
            return back()->withErrors(['senha_webmail' => $e->getMessage()]);
        }

        return redirect()->route('estabelecimentos.show', $estabelecimento)
            ->with('status', 'Senha do e-mail da plataforma alterada com sucesso.');
    }
}
