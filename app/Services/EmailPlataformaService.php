<?php

namespace App\Services;

use App\Models\Estabelecimento;
use Illuminate\Support\Str;

class EmailPlataformaService
{
    public function __construct(
        private readonly DirectAdminService $da,
    ) {}

    /**
     * Deriva o username do e-mail informado.
     * Ex: lucasmoraes.lrm@gmail.com → lucasmoraes.lrm
     */
    public function derivarUsername(string $email): string
    {
        $prefixo = Str::before($email, '@');

        return strtolower(preg_replace('/[^a-z0-9._-]/i', '', $prefixo));
    }

    public function usernameOcupado(string $username): bool
    {
        return $this->da->emailExistePlataforma($username);
    }

    /**
     * Cria a conta de e-mail da plataforma e o redirecionamento.
     * Salva webmail_email e webmail_senha (encriptada) no banco.
     *
     * @throws \RuntimeException se o username estiver inválido, já existir ou a criação falhar.
     */
    public function provisionar(Estabelecimento $estabelecimento, string $username): void
    {
        $username = strtolower(trim($username));

        if (blank($username) || ! preg_match('/^[a-z0-9._-]+$/', $username)) {
            throw new \RuntimeException('Nome de usuário inválido. Use apenas letras, números, ponto, hífen ou sublinhado.');
        }

        if ($this->usernameOcupado($username)) {
            throw new \RuntimeException("O nome \"{$username}\" já está em uso no servidor de e-mail.");
        }

        $senha = Str::password(16, true, true, false);
        $dominio = config('directadmin.dominio');
        $emailPlataforma = "{$username}@{$dominio}";

        $criou = $this->da->criarEmailPlataforma($username, $senha);

        if (! $criou) {
            throw new \RuntimeException("Não foi possível criar a conta {$emailPlataforma} no servidor.");
        }

        // Forwarder NÃO é criado aqui intencionalmente:
        // o e-mail de validação do PagBank precisa chegar no Roundcube para a automação ler.
        // O forwarder é ativado em ativarForwarder() após a automação concluir.

        $estabelecimento->update([
            'webmail_email'           => $emailPlataforma,
            'webmail_senha'           => $senha,
            'webmail_forwarder_ativo' => false,
        ]);
    }

    /**
     * Cria o forwarder para o e-mail original do estabelecimento.
     * Deve ser chamado após a automação concluir com sucesso.
     */
    public function ativarForwarder(Estabelecimento $estabelecimento): void
    {
        if (blank($estabelecimento->webmail_email) || blank($estabelecimento->email)) {
            return;
        }

        $username = Str::before($estabelecimento->webmail_email, '@');
        $this->da->redirecionarEmailPlataforma($username, $estabelecimento->email);
        $estabelecimento->update(['webmail_forwarder_ativo' => true]);
    }

    /**
     * Remove o forwarder (para que e-mails fiquem no Roundcube durante retentativa de automação).
     */
    public function desativarForwarder(Estabelecimento $estabelecimento): void
    {
        if (blank($estabelecimento->webmail_email)) {
            return;
        }

        $username = Str::before($estabelecimento->webmail_email, '@');
        $this->da->excluirForwarderPlataforma($username);
        $estabelecimento->update(['webmail_forwarder_ativo' => false]);
    }

    /**
     * Tenta provisionar automaticamente ao cadastrar.
     * Retorna null em caso de sucesso, ou o username sugerido caso esteja ocupado
     * (indicando que o admin precisa escolher manualmente).
     */
    public function provisionarAutomatico(Estabelecimento $estabelecimento): ?string
    {
        if (blank($estabelecimento->email)) {
            return null;
        }

        $username = $this->derivarUsername($estabelecimento->email);

        if (blank($username)) {
            return null;
        }

        if ($this->usernameOcupado($username)) {
            return $username;
        }

        try {
            $this->provisionar($estabelecimento, $username);
        } catch (\Throwable) {
            return $username;
        }

        return null;
    }

    /**
     * Altera a senha da caixa no DirectAdmin e atualiza o banco.
     */
    public function alterarSenha(Estabelecimento $estabelecimento, string $novaSenha): void
    {
        $username = Str::before($estabelecimento->webmail_email, '@');

        $ok = $this->da->alterarSenhaEmailPlataforma($username, $novaSenha);

        if (! $ok) {
            throw new \RuntimeException('Não foi possível alterar a senha no servidor.');
        }

        $estabelecimento->update(['webmail_senha' => $novaSenha]);
    }
}
