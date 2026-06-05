<?php

namespace Database\Seeders;

use App\Models\Chamado;
use App\Models\ChamadoHistorico;
use App\Models\ChamadoMensagem;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class ChamadoTesteSeeder extends Seeder
{
    public function run(): void
    {
        $autor = Usuario::where('tipo', '!=', 'admin')->first();

        if (! $autor) {
            $this->command?->warn('Nenhum usuario comercial encontrado. Crie master/marketplace/revenda antes.');

            return;
        }

        $ano = now()->format('Y');
        $base = (int) Chamado::whereYear('created_at', $ano)->max('id');

        $amostras = [
            [
                'titulo' => 'Divergencia no repasse de vendas do dia 15/05',
                'categoria' => 'financeiro',
                'prioridade' => 'alta',
                'status' => 'aberto',
                'visualizado_admin' => false,
                'mensagem' => 'O valor liquidado no extrato nao bate com o total de transacoes no painel. Podem verificar?',
            ],
            [
                'titulo' => 'Maquininha sem sincronizar transacoes',
                'categoria' => 'tecnico',
                'prioridade' => 'urgente',
                'status' => 'em_atendimento',
                'visualizado_admin' => true,
                'mensagem' => 'Desde ontem a maquininha aceita pagamentos mas nada aparece no sistema.',
            ],
            [
                'titulo' => 'Duvida sobre taxa do plano Premium',
                'categoria' => 'comercial',
                'prioridade' => 'media',
                'status' => 'aguardando_cliente',
                'visualizado_admin' => true,
                'mensagem' => 'Preciso confirmar se a taxa de debito 1x ja esta aplicada no estabelecimento 123.',
            ],
            [
                'titulo' => 'Atualizacao de CNPJ do estabelecimento',
                'categoria' => 'cadastro',
                'prioridade' => 'baixa',
                'status' => 'resolvido',
                'visualizado_admin' => true,
                'fechado_em' => now()->subDays(2),
                'mensagem' => 'Alteramos o CNPJ na Receita e precisamos atualizar o cadastro.',
            ],
            [
                'titulo' => 'Token EDI retornando erro 401',
                'categoria' => 'integracao',
                'prioridade' => 'alta',
                'status' => 'fechado',
                'visualizado_admin' => true,
                'fechado_em' => now()->subDay(),
                'avaliacao' => 5,
                'avaliacao_comentario' => 'Problema resolvido rapidamente, obrigado.',
                'mensagem' => 'A importacao EDI parou apos troca de credencial PagBank.',
            ],
        ];

        foreach ($amostras as $i => $dados) {
            $numero = 'CHM-'.$ano.'-'.str_pad($base + $i + 1, 6, '0', STR_PAD_LEFT);

            if (Chamado::where('numero', $numero)->exists()) {
                continue;
            }

            $chamado = Chamado::create([
                'aberto_por_id' => $autor->id,
                'aberto_por_tipo' => 'usuario',
                'aberto_por_nivel' => $autor->tipo,
                'master_id' => null,
                'marketplace_id' => $autor->tipo === 'marketplace' ? $autor->id : null,
                'revenda_id' => $autor->tipo === 'revenda' ? $autor->id : null,
                'titulo' => $dados['titulo'],
                'categoria' => $dados['categoria'],
                'prioridade' => $dados['prioridade'],
                'status' => $dados['status'],
                'numero' => $numero,
                'visualizado_admin' => $dados['visualizado_admin'],
                'avaliacao' => $dados['avaliacao'] ?? null,
                'avaliacao_comentario' => $dados['avaliacao_comentario'] ?? null,
                'fechado_em' => $dados['fechado_em'] ?? null,
            ]);

            ChamadoMensagem::create([
                'chamado_id' => $chamado->id,
                'autor_id' => $autor->id,
                'autor_tipo' => 'usuario',
                'autor_nome' => $autor->nomeExibicao(),
                'mensagem' => $dados['mensagem'],
                'interno' => false,
                'visualizado' => true,
            ]);

            ChamadoHistorico::create([
                'chamado_id' => $chamado->id,
                'autor_id' => $autor->id,
                'autor_nome' => $autor->nomeExibicao(),
                'acao' => 'chamado_aberto',
                'valor_anterior' => null,
                'valor_novo' => 'aberto',
            ]);

            if ($dados['status'] !== 'aberto') {
                ChamadoHistorico::create([
                    'chamado_id' => $chamado->id,
                    'autor_id' => 1,
                    'autor_nome' => 'Admin',
                    'acao' => 'status_alterado',
                    'valor_anterior' => 'aberto',
                    'valor_novo' => $dados['status'],
                ]);
            }
        }
    }
}
