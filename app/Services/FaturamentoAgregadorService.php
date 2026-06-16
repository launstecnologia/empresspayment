<?php

namespace App\Services;

use App\Models\AggregatedRevenue;
use App\Models\EdiMovimento;
use App\Models\Estabelecimento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FaturamentoAgregadorService
{
    public function agregar(?string $data = null): int
    {
        $query = EdiMovimento::withoutGlobalScopes()
            ->selectRaw('
                DATE(data_inicial_transacao) as data,
                YEAR(data_inicial_transacao) as ano,
                MONTH(data_inicial_transacao) as mes,
                estabelecimento_id,
                instituicao_financeira as instituicao,
                tipo_transacao,
                status_pagamento,
                SUM(valor_total_transacao) as total_valor,
                COUNT(*) as total_transacoes
            ')
            ->whereNotNull('data_inicial_transacao')
            ->whereNotNull('estabelecimento_id')
            ->groupBy('data', 'ano', 'mes', 'estabelecimento_id', 'instituicao', 'tipo_transacao', 'status_pagamento');

        if ($data) {
            $query->whereDate('data_inicial_transacao', $data);
        }

        $linhas = $query->get();

        if ($linhas->isEmpty()) {
            return 0;
        }

        $estabelecimentos = Estabelecimento::withoutGlobalScopes()
            ->whereIn('id', $linhas->pluck('estabelecimento_id')->unique())
            ->get(['id', 'master_id', 'marketplace_id', 'revenda_id'])
            ->keyBy('id');

        $royalties = $this->totaisRoyaltyPorGrupo($data);

        foreach ($linhas as $linha) {
            $estabelecimento = $estabelecimentos->get($linha->estabelecimento_id);
            $chave = $this->chaveGrupo($linha->data, $linha->estabelecimento_id, $linha->instituicao, $linha->tipo_transacao, $linha->status_pagamento);

            AggregatedRevenue::withoutGlobalScopes()->updateOrCreate(
                [
                    'data' => $linha->data,
                    'estabelecimento_id' => $linha->estabelecimento_id,
                    'instituicao' => $linha->instituicao,
                    'tipo_transacao' => $linha->tipo_transacao,
                    'status_pagamento' => $linha->status_pagamento,
                ],
                [
                    'ano' => $linha->ano,
                    'mes' => $linha->mes,
                    'master_id' => $estabelecimento?->master_id,
                    'marketplace_id' => $estabelecimento?->marketplace_id,
                    'revenda_id' => $estabelecimento?->revenda_id,
                    'total_valor' => $linha->total_valor,
                    'total_royalty' => $royalties->get($chave, 0),
                    'total_transacoes' => $linha->total_transacoes,
                    'atualizado_em' => now(),
                ]
            );
        }

        return $linhas->count();
    }

    private function totaisRoyaltyPorGrupo(?string $data): Collection
    {
        $query = DB::table('transacao_royalties')
            ->join('edi_movimentos', 'edi_movimentos.id', '=', 'transacao_royalties.edi_movimento_id')
            ->whereNotNull('edi_movimentos.data_inicial_transacao')
            ->whereNotNull('edi_movimentos.estabelecimento_id');

        if ($data) {
            $query->whereDate('edi_movimentos.data_inicial_transacao', $data);
        }

        return $query
            ->selectRaw('
                DATE(edi_movimentos.data_inicial_transacao) as data,
                edi_movimentos.estabelecimento_id,
                edi_movimentos.instituicao_financeira as instituicao,
                edi_movimentos.tipo_transacao,
                edi_movimentos.status_pagamento,
                SUM(transacao_royalties.valor_royalty) as total_royalty
            ')
            ->groupBy('data', 'estabelecimento_id', 'instituicao', 'tipo_transacao', 'status_pagamento')
            ->get()
            ->keyBy(fn ($linha) => $this->chaveGrupo(
                $linha->data,
                $linha->estabelecimento_id,
                $linha->instituicao,
                $linha->tipo_transacao,
                $linha->status_pagamento,
            ))
            ->map(fn ($linha) => (float) $linha->total_royalty);
    }

    private function chaveGrupo(
        string $data,
        int $estabelecimentoId,
        ?string $instituicao,
        ?string $tipoTransacao,
        ?string $statusPagamento,
    ): string {
        return implode('|', [$data, $estabelecimentoId, $instituicao, $tipoTransacao, $statusPagamento]);
    }
}
