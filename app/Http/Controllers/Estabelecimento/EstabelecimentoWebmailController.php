<?php

namespace App\Http\Controllers\Estabelecimento;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
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
     * A senha nunca trafega pela URL — é enviada via POST da página intermediária.
     */
    public function sso(Estabelecimento $estabelecimento)
    {
        abort_unless(filled($estabelecimento->webmail_email), 404, 'Nenhuma caixa de e-mail configurada.');
        abort_unless(filled($estabelecimento->webmail_senha), 404, 'Senha do e-mail não disponível.');

        $webmailUrl = rtrim((string) config('directadmin.webmail_url'), '/');

        return view('estabelecimento.webmail-sso', [
            'webmailUrl' => $webmailUrl,
            'email'      => $estabelecimento->webmail_email,
            'senha'      => $estabelecimento->webmail_senha,
        ]);
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
