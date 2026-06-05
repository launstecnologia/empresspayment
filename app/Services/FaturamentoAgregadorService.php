<?php

namespace App\Services;

use App\Models\AggregatedRevenue;
use App\Models\EdiMovimento;
use App\Models\Estabelecimento;
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
            ->groupBy('data', 'ano', 'mes', 'estabelecimento_id', 'instituicao', 'tipo_transacao', 'status_pagamento');

        if ($data) {
            $query->whereDate('data_inicial_transacao', $data);
        }

        $linhas = $query->get();

        foreach ($linhas as $linha) {
            $estabelecimento = Estabelecimento::withoutGlobalScopes()->find($linha->estabelecimento_id);
            $totalRoyalty = DB::table('transacao_royalties')
                ->join('edi_movimentos', 'edi_movimentos.id', '=', 'transacao_royalties.edi_movimento_id')
                ->whereDate('edi_movimentos.data_inicial_transacao', $linha->data)
                ->where('edi_movimentos.estabelecimento_id', $linha->estabelecimento_id)
                ->where('edi_movimentos.instituicao_financeira', $linha->instituicao)
                ->where('edi_movimentos.tipo_transacao', $linha->tipo_transacao)
                ->where('edi_movimentos.status_pagamento', $linha->status_pagamento)
                ->sum('transacao_royalties.valor_royalty');

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
                    'total_royalty' => $totalRoyalty,
                    'total_transacoes' => $linha->total_transacoes,
                    'atualizado_em' => now(),
                ]
            );
        }

        return $linhas->count();
    }
}
