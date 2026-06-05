<?php

namespace App\Services;

use App\Jobs\CriarEmailEstabelecimentoJob;
use App\Models\Estabelecimento;
use App\Models\EstabelecimentoEmail;
use Illuminate\Support\Str;
use RuntimeException;

class EstabelecimentoEmailService
{
    public function __construct(
        private DirectAdminService $directAdmin,
        private LogService $log,
    ) {}

    public function provisionarAutomatico(Estabelecimento $estabelecimento, bool $forcar = false): ?EstabelecimentoEmail
    {
        if (! $forcar && $estabelecimento->emails()->where('criado_automaticamente', true)->exists()) {
            return $estabelecimento->emails()->where('criado_automaticamente', true)->first();
        }

        if (blank($estabelecimento->subdominio)) {
            throw new RuntimeException('Defina o subdomínio do estabelecimento antes de criar o e-mail.');
        }

        $prefixo = $this->gerarPrefixo($estabelecimento);
        $senha = $this->gerarSenha();

        return $this->criarConta($estabelecimento, $prefixo, $senha, true);
    }

    public function criarManual(Estabelecimento $estabelecimento, string $prefixo, ?string $senha = null): EstabelecimentoEmail
    {
        $prefixo = $this->normalizarPrefixo($prefixo);
        $senha = $senha ?: $this->gerarSenha();

        return $this->criarConta($estabelecimento, $prefixo, $senha, false);
    }

    public function alterarSenha(EstabelecimentoEmail $conta, string $novaSenha): void
    {
        $estab = $conta->estabelecimento;

        if ($this->directAdminConfigurado() && $estab?->subdominio) {
            if (! $this->directAdmin->alterarSenhaEmail($estab->subdominio, $conta->nome_email, $novaSenha)) {
                throw new RuntimeException('Não foi possível alterar a senha no servidor de e-mail.');
            }
        }

        $conta->update(['senha_criptografada' => $novaSenha]);

        $this->log->registrar('Estabelecimento', $estab->id, 'email_senha_alterada', "Senha do e-mail {$conta->email_completo} alterada.");
    }

    public function redirecionar(EstabelecimentoEmail $conta, string $destino): void
    {
        $estab = $conta->estabelecimento;

        if ($this->directAdminConfigurado() && $estab?->subdominio) {
            if (! $this->directAdmin->redirecionarEmail($estab->subdominio, $conta->nome_email, $destino)) {
                throw new RuntimeException('Não foi possível configurar o redirecionamento no servidor.');
            }
        }

        $conta->update(['redirecionamento_para' => $destino]);

        $this->log->registrar('Estabelecimento', $estab->id, 'email_redirecionado', "E-mail {$conta->email_completo} redirecionado para {$destino}.");
    }

    public function excluir(EstabelecimentoEmail $conta): void
    {
        $estab = $conta->estabelecimento;

        if ($this->directAdminConfigurado() && $estab?->subdominio) {
            $this->directAdmin->excluirEmail($estab->subdominio, $conta->nome_email);
        }

        $email = $conta->email_completo;
        $estabId = $estab->id;
        $conta->delete();

        $this->log->registrar('Estabelecimento', $estabId, 'email_removido', "Conta de e-mail {$email} removida.");
    }

    public function enfileirarProvisionamento(Estabelecimento $estabelecimento): void
    {
        CriarEmailEstabelecimentoJob::dispatch($estabelecimento);
    }

    public function emailCompleto(Estabelecimento $estabelecimento, string $prefixo): string
    {
        $dominio = (string) config('directadmin.dominio', 'localhost');

        return "{$prefixo}@{$estabelecimento->subdominio}.{$dominio}";
    }

    public function hostsPadrao(): array
    {
        $dominio = (string) config('directadmin.dominio', 'localhost');
        $mailHost = 'mail.'.$dominio;

        return [
            'imap_host' => config('directadmin.imap_host') ?: $mailHost,
            'imap_porta' => (int) config('directadmin.imap_porta', 993),
            'imap_ssl' => true,
            'smtp_host' => config('directadmin.smtp_host') ?: $mailHost,
            'smtp_porta' => (int) config('directadmin.smtp_porta', 587),
            'smtp_ssl' => true,
        ];
    }

    private function criarConta(Estabelecimento $estabelecimento, string $prefixo, string $senha, bool $automatico): EstabelecimentoEmail
    {
        if (blank($estabelecimento->subdominio)) {
            throw new RuntimeException('Subdomínio obrigatório para criar e-mail.');
        }

        $emailCompleto = $this->emailCompleto($estabelecimento, $prefixo);

        if (EstabelecimentoEmail::where('email_completo', $emailCompleto)->exists()) {
            throw new RuntimeException('Este endereço de e-mail já está cadastrado.');
        }

        if ($this->directAdminConfigurado()) {
            $this->directAdmin->criarSubdominio($estabelecimento->subdominio);

            if (! $this->directAdmin->criarEmail($estabelecimento->subdominio, $prefixo, $senha)) {
                throw new RuntimeException('Falha ao criar a conta no DirectAdmin.');
            }
        }

        $hosts = $this->hostsPadrao();

        $conta = EstabelecimentoEmail::create([
            'estabelecimento_id' => $estabelecimento->id,
            'nome_email' => $prefixo,
            'email_completo' => $emailCompleto,
            'senha_criptografada' => $senha,
            'redirecionamento_para' => null,
            ...$hosts,
            'criado_automaticamente' => $automatico,
            'ativo' => true,
        ]);

        $this->log->registrar(
            'Estabelecimento',
            $estabelecimento->id,
            'email_criado',
            $automatico
                ? "E-mail {$emailCompleto} criado automaticamente ao habilitar."
                : "E-mail {$emailCompleto} criado manualmente.",
        );

        return $conta;
    }

    private function gerarPrefixo(Estabelecimento $estabelecimento): string
    {
        $slug = Str::slug($estabelecimento->nome_fantasia ?: $estabelecimento->razao_social ?: $estabelecimento->nome_completo, '');
        $slug = substr((string) $slug, 0, 20);

        return $slug !== '' ? $slug : 'contato'.$estabelecimento->id;
    }

    private function normalizarPrefixo(string $prefixo): string
    {
        $prefixo = Str::slug($prefixo, '');

        if ($prefixo === '' || strlen($prefixo) > 20) {
            throw new RuntimeException('Use um prefixo de e-mail válido (até 20 caracteres, sem espaços).');
        }

        return $prefixo;
    }

    private function gerarSenha(): string
    {
        return Str::password(16).'!A1';
    }

    private function directAdminConfigurado(): bool
    {
        return filled(config('directadmin.url'))
            && filled(config('directadmin.usuario'))
            && filled(config('directadmin.senha'))
            && filled(config('directadmin.dominio'));
    }
}
