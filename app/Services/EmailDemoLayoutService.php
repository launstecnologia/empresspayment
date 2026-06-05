<?php

namespace App\Services;

use App\Models\EmailCaixaEntrada;
use App\Models\EmailEnviado;
use App\Models\Estabelecimento;
use App\Models\EstabelecimentoEmail;
use Carbon\Carbon;
use Illuminate\Support\Str;

class EmailDemoLayoutService
{
    public function ativo(): bool
    {
        return (bool) config('email.demo_layout', false);
    }

    /**
     * @return array{conta: EstabelecimentoEmail|null, modo_demo: bool}
     */
    public function garantir(Estabelecimento $estabelecimento): array
    {
        if (! $this->ativo()) {
            return ['conta' => $estabelecimento->emails()->first(), 'modo_demo' => false];
        }

        if (blank($estabelecimento->subdominio)) {
            $estabelecimento->forceFill([
                'subdominio' => 'demo'.$estabelecimento->id,
            ])->save();
        }

        $dominio = (string) config('directadmin.dominio', 'plataforma.local');
        $sub = (string) $estabelecimento->subdominio;
        $prefixo = 'contato';
        $emailCompleto = "{$prefixo}@{$sub}.{$dominio}";

        $conta = EstabelecimentoEmail::query()->firstOrCreate(
            [
                'estabelecimento_id' => $estabelecimento->id,
                'email_completo' => $emailCompleto,
            ],
            [
                'nome_email' => $prefixo,
                'senha_criptografada' => 'demo-layout-'.Str::random(8),
                'imap_host' => 'mail.'.$dominio,
                'imap_porta' => 993,
                'imap_ssl' => true,
                'smtp_host' => 'mail.'.$dominio,
                'smtp_porta' => 587,
                'smtp_ssl' => true,
                'criado_automaticamente' => false,
                'ativo' => true,
                'ultimo_sync' => now(),
            ],
        );

        if (! $conta->mensagens()->where('uid', 'like', 'demo-%')->exists()) {
            $this->popularCaixa($conta);
        }

        if ($conta->enviados()->count() === 0) {
            $this->popularEnviados($conta);
        }

        return ['conta' => $conta->fresh(), 'modo_demo' => true];
    }

    private function popularCaixa(EstabelecimentoEmail $conta): void
    {
        foreach ($this->mensagensDemonstracao() as $dados) {
            EmailCaixaEntrada::create([
                'estabelecimento_email_id' => $conta->id,
                'uid' => $dados['uid'],
                'message_id' => '<'.$dados['uid'].'@demo.local>',
                'pasta' => $dados['pasta'],
                'de_nome' => $dados['de_nome'],
                'de_email' => $dados['de_email'],
                'para' => $conta->email_completo,
                'assunto' => $dados['assunto'],
                'corpo_texto' => $dados['corpo_texto'],
                'corpo_html' => $dados['corpo_html'],
                'tem_anexo' => $dados['tem_anexo'],
                'lido' => $dados['lido'],
                'favorito' => $dados['favorito'],
                'spam' => $dados['spam'],
                'deletado' => false,
                'data_email' => $dados['data_email'],
            ]);
        }
    }

    private function popularEnviados(EstabelecimentoEmail $conta): void
    {
        EmailEnviado::create([
            'estabelecimento_email_id' => $conta->id,
            'para' => 'cliente@exemplo.com.br',
            'assunto' => 'Orçamento maquininha — confirmação',
            'corpo_html' => '<p>Olá! Segue confirmação do orçamento solicitado.</p>',
            'status' => 'enviado',
            'created_at' => now()->subDays(2),
        ]);

        EmailEnviado::create([
            'estabelecimento_email_id' => $conta->id,
            'para' => 'financeiro@loja.com.br',
            'assunto' => 'Dúvida sobre taxas',
            'corpo_html' => '<p>Bom dia, poderiam detalhar as taxas do plano?</p>',
            'status' => 'enviado',
            'created_at' => now()->subDays(5),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function mensagensDemonstracao(): array
    {
        $agora = Carbon::now();

        return [
            [
                'uid' => 'demo-001',
                'pasta' => 'INBOX',
                'de_nome' => 'José Silva',
                'de_email' => 'jose.silva@gmail.com',
                'assunto' => 'Orçamento maquininha para delivery',
                'corpo_texto' => "Olá,\n\nGostaria de receber um orçamento para 2 maquininhas Smart.\n\nAtt,\nJosé",
                'corpo_html' => '<p>Olá,</p><p>Gostaria de receber um <strong>orçamento</strong> para 2 maquininhas Smart para operação delivery.</p><p>Att,<br>José</p>',
                'tem_anexo' => false,
                'lido' => false,
                'favorito' => false,
                'spam' => false,
                'data_email' => $agora->copy()->subHours(2),
            ],
            [
                'uid' => 'demo-002',
                'pasta' => 'INBOX',
                'de_nome' => 'PagBank',
                'de_email' => 'noreply@pagbank.com.br',
                'assunto' => 'Extrato consolidado — maio/2026',
                'corpo_texto' => 'Seu extrato mensal já está disponível para download.',
                'corpo_html' => '<p>Seu <strong>extrato mensal</strong> já está disponível.</p><p><a href="#">Baixar PDF</a></p>',
                'tem_anexo' => true,
                'lido' => false,
                'favorito' => false,
                'spam' => false,
                'data_email' => $agora->copy()->subHours(5),
            ],
            [
                'uid' => 'demo-003',
                'pasta' => 'INBOX',
                'de_nome' => 'Maria Costa',
                'de_email' => 'maria@padariacentral.com',
                'assunto' => 'Dúvida sobre taxa de débito',
                'corpo_texto' => "Boa tarde,\n\nQual a taxa atual para débito no plano contratado?\n\nMaria",
                'corpo_html' => '<p>Boa tarde,</p><p>Qual a taxa atual para <em>débito</em> no plano contratado?</p>',
                'tem_anexo' => false,
                'lido' => true,
                'favorito' => true,
                'spam' => false,
                'data_email' => $agora->copy()->subDay(),
            ],
            [
                'uid' => 'demo-004',
                'pasta' => 'INBOX',
                'de_nome' => 'Suporte Express',
                'de_email' => 'suporte@express.local',
                'assunto' => 'Cadastro habilitado com sucesso',
                'corpo_texto' => 'Parabéns! Seu estabelecimento foi habilitado na plataforma.',
                'corpo_html' => '<p>Parabéns! Seu estabelecimento foi <strong>habilitado</strong> na plataforma.</p><p>Acesse o painel para configurar usuários e documentos.</p>',
                'tem_anexo' => false,
                'lido' => true,
                'favorito' => false,
                'spam' => false,
                'data_email' => $agora->copy()->subDays(2),
            ],
            [
                'uid' => 'demo-005',
                'pasta' => 'INBOX',
                'de_nome' => 'Carlos Mendes',
                'de_email' => 'carlos.mendes@outlook.com',
                'assunto' => 'Re: Proposta comercial',
                'corpo_texto' => "Podemos agendar uma call amanhã às 10h?\n\nCarlos",
                'corpo_html' => '<p>Podemos agendar uma call <strong>amanhã às 10h</strong>?</p>',
                'tem_anexo' => false,
                'lido' => true,
                'favorito' => false,
                'spam' => false,
                'data_email' => $agora->copy()->subDays(3),
            ],
            [
                'uid' => 'demo-spam-001',
                'pasta' => 'INBOX',
                'de_nome' => 'Promoções',
                'de_email' => 'spam@ofertas.xyz',
                'assunto' => 'Você ganhou um prêmio!!!',
                'corpo_texto' => 'Clique aqui para resgatar.',
                'corpo_html' => '<p>Clique aqui para resgatar.</p>',
                'tem_anexo' => false,
                'lido' => true,
                'favorito' => false,
                'spam' => true,
                'data_email' => $agora->copy()->subDays(4),
            ],
        ];
    }
}
