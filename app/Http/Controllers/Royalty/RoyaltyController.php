<?php

namespace App\Http\Controllers\Royalty;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoyaltyController extends Controller
{
    private const MESES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function index(Request $request)
    {
        $estabelecimentoIds = Estabelecimento::query()->pluck('id');

        if ($estabelecimentoIds->isEmpty()) {
            return view('relatorio.royalties', [
                'linhas' => new LengthAwarePaginator([], 0, 50),
            ]);
        }

        $faturamentos = DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereIn('e.id', $estabelecimentoIds)
            ->whereNotNull('e.marketplace_id')
            ->whereNotNull('em.data_inicial_transacao')
            ->selectRaw('
                e.marketplace_id,
                YEAR(em.data_inicial_transacao) as ano,
                MONTH(em.data_inicial_transacao) as mes,
                SUM(em.valor_total_transacao) as total_faturamento
            ')
            ->groupBy('e.marketplace_id', 'ano', 'mes')
            ->get();

        $comissoes = DB::table('transacao_royalties as tr')
            ->join('edi_movimentos as em', 'em.id', '=', 'tr.edi_movimento_id')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->join('usuarios as u', 'u.id', '=', 'tr.usuario_id')
            ->whereIn('e.id', $estabelecimentoIds)
            ->where('u.tipo', 'marketplace')
            ->whereColumn('tr.usuario_id', 'e.marketplace_id')
            ->whereNotNull('em.data_inicial_transacao')
            ->selectRaw('
                tr.usuario_id as marketplace_id,
                YEAR(em.data_inicial_transacao) as ano,
                MONTH(em.data_inicial_transacao) as mes,
                SUM(tr.valor_royalty) as total_comissao
            ')
            ->groupBy('tr.usuario_id', 'ano', 'mes')
            ->get()
            ->keyBy(fn ($row) => "{$row->marketplace_id}-{$row->ano}-{$row->mes}");

        $marketplaceIds = $faturamentos->pluck('marketplace_id')
            ->merge($comissoes->pluck('marketplace_id'))
            ->unique()
            ->filter();

        $marketplaces = Usuario::whereIn('id', $marketplaceIds)->get()->keyBy('id');

        $linhas = $this->montarLinhas($faturamentos, $comissoes, $marketplaces)
            ->sortBy([
                ['ano', 'desc'],
                ['mes', 'desc'],
                ['marketplace_nome', 'asc'],
            ])
            ->values();

        $page = $request->integer('page', 1);
        $perPage = 50;
        $paginado = new LengthAwarePaginator(
            $linhas->forPage($page, $perPage)->values(),
            $linhas->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('relatorio.royalties', [
            'linhas' => $paginado,
        ]);
    }

    private function montarLinhas(Collection $faturamentos, Collection $comissoes, Collection $marketplaces): Collection
    {
        $chaves = [];

        foreach ($faturamentos as $row) {
            $chaves["{$row->marketplace_id}-{$row->ano}-{$row->mes}"] = $row;
        }

        foreach ($comissoes as $row) {
            $key = "{$row->marketplace_id}-{$row->ano}-{$row->mes}";
            if (! isset($chaves[$key])) {
                $chaves[$key] = (object) [
                    'marketplace_id' => $row->marketplace_id,
                    'ano' => $row->ano,
                    'mes' => $row->mes,
                    'total_faturamento' => 0,
                ];
            }
        }

        return collect($chaves)->map(function ($row) use ($comissoes, $marketplaces) {
            $key = "{$row->marketplace_id}-{$row->ano}-{$row->mes}";
            $marketplace = $marketplaces->get($row->marketplace_id);
            $comissao = $comissoes->get($key);

            return (object) [
                'marketplace_id' => $row->marketplace_id,
                'marketplace_nome' => $marketplace?->nomeExibicao() ?? '—',
                'ano' => (int) $row->ano,
                'mes' => (int) $row->mes,
                'periodo' => (self::MESES[(int) $row->mes] ?? $row->mes).'/'.(int) $row->ano,
                'total_faturamento' => (float) ($row->total_faturamento ?? 0),
                'total_comissao' => (float) ($comissao->total_comissao ?? 0),
            ];
        });
    }
}
