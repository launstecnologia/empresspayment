<?php

namespace App\Services;

use App\Models\AggregatedRevenue;
use App\Models\EdiMovimento;
use App\Models\Estabelecimento;
use Carbon\CarbonInterface;
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

    public function limparPeriodo(string $de, string $ate): int
    {
        return AggregatedRevenue::withoutGlobalScopes()
            ->whereBetween('data', [$de, $ate])
            ->delete();
    }

    /**
     * @return array{
     *     removidos: int,
     *     dias_com_dados: int,
     *     grupos: int,
     *     agg_antes: float,
     *     agg_depois: float,
     *     edi: float
     * }
     */
    public function reagregarPeriodo(CarbonInterface $de, CarbonInterface $ate): array
    {
        $inicio = $de->copy()->startOfDay()->format('Y-m-d');
        $fim = $ate->copy()->startOfDay()->format('Y-m-d');

        $aggAntes = (float) AggregatedRevenue::withoutGlobalScopes()
            ->whereBetween('data', [$inicio, $fim])
            ->sum('total_valor');

        $edi = (float) EdiMovimento::withoutGlobalScopes()
            ->whereNotNull('estabelecimento_id')
            ->whereBetween('data_inicial_transacao', [$inicio, $fim])
            ->sum('valor_total_transacao');

        $removidos = $this->limparPeriodo($inicio, $fim);

        $grupos = 0;
        $diasComDados = 0;

        for ($data = $de->copy()->startOfDay(); $data->lte($ate->copy()->startOfDay()); $data->addDay()) {
            $gruposDia = $this->agregar($data->format('Y-m-d'));

            if ($gruposDia === 0) {
                continue;
            }

            $diasComDados++;
            $grupos += $gruposDia;
        }

        $aggDepois = (float) AggregatedRevenue::withoutGlobalScopes()
            ->whereBetween('data', [$inicio, $fim])
            ->sum('total_valor');

        return [
            'removidos' => $removidos,
            'dias_com_dados' => $diasComDados,
            'grupos' => $grupos,
            'agg_antes' => $aggAntes,
            'agg_depois' => $aggDepois,
            'edi' => $edi,
        ];
    }

    /**
     * @return array{linhas: int, total: float, por_tipo: list<array{tipo: string, total: float}>}
     */
    public function resumoAgregadoPeriodo(string $de, string $ate): array
    {
        $porTipo = AggregatedRevenue::withoutGlobalScopes()
            ->whereBetween('data', [$de, $ate])
            ->selectRaw('COALESCE(tipo_transacao, "(null)") as tipo, SUM(total_valor) as total')
            ->groupBy('tipo_transacao')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['tipo' => (string) $row->tipo, 'total' => (float) $row->total])
            ->all();

        return [
            'linhas' => AggregatedRevenue::withoutGlobalScopes()->whereBetween('data', [$de, $ate])->count(),
            'total' => (float) AggregatedRevenue::withoutGlobalScopes()->whereBetween('data', [$de, $ate])->sum('total_valor'),
            'por_tipo' => $porTipo,
        ];
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
