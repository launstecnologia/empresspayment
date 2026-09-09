<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\EdiStatusPagamento;
use App\Support\InstituicaoFinanceira;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EdiTransacaoRelatorioController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'mes_numero' => ['nullable', 'integer', 'between:1,12'],
            'ano' => ['nullable', 'integer', 'between:2020,2100'],
            'status' => ['nullable', 'in:todos,faturaveis,canceladas,01,02,03,04'],
            'tipo_transacao' => ['nullable', 'in:debito,credito,pix'],
            'instituicao' => ['nullable', 'string', 'max:32'],
            'busca' => ['nullable', 'string', 'max:120'],
            'por_pagina' => ['nullable', 'integer', 'in:50,100,200'],
        ]);

        $filtros = $this->filtros($request);
        $base = $this->baseQuery($filtros);

        $totais = $this->totais(clone $base);
        $porStatus = $this->porStatus(clone $base);
        $porTipo = $this->porTipo(clone $base);

        $transacoes = (clone $base)
            ->select([
                'em.id',
                'em.movimento_api_codigo',
                'em.estabelecimento',
                'em.data_inicial_transacao',
                'em.hora_inicial_transacao',
                'em.data_venda_ajuste',
                'em.data_prevista_pagamento',
                'em.tipo_evento',
                'em.tipo_transacao',
                'em.status_pagamento',
                'em.instituicao_financeira',
                'em.meio_pagamento',
                'em.arranjo_ur',
                'em.valor_total_transacao',
                'em.valor_liquido_transacao',
                'em.valor_original_transacao',
                'em.taxa_intermediacao',
                'em.tarifa_intermediacao',
                'em.parcela',
                'em.quantidade_parcela',
                'em.nsu',
                'em.tx_id',
                'em.codigo_transacao',
                'em.codigo_venda',
                'em.codigo_autorizacao',
                'em.num_logico',
                'em.numero_serie_leitor',
                'e.nome_fantasia',
                'e.razao_social',
                'e.nome_completo',
                'e.cnpj',
                'e.cpf',
            ])
            ->orderByDesc('em.data_inicial_transacao')
            ->orderByDesc('em.hora_inicial_transacao')
            ->orderByDesc('em.id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString();

        return view('admin.relatorios.edi-transacoes', [
            'filtros' => $filtros,
            'totais' => $totais,
            'porStatus' => $porStatus,
            'porTipo' => $porTipo,
            'transacoes' => $transacoes,
            'instituicoes' => InstituicaoFinanceira::codigos(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    private function filtros(Request $request): array
    {
        $mes = $this->mesSelecionado($request);

        $inicio = $mes->copy()->startOfMonth()->toDateString();
        $fim = $mes->copy()->endOfMonth()->toDateString();
        $porPagina = (int) $request->input('por_pagina', 100);

        return [
            'mes' => $mes->format('Y-m'),
            'mes_numero' => (int) $mes->format('n'),
            'ano' => (int) $mes->format('Y'),
            'inicio' => $inicio,
            'fim' => $fim,
            'status' => in_array($request->input('status'), ['todos', 'faturaveis', 'canceladas', '01', '02', '03', '04'], true)
                ? (string) $request->input('status')
                : 'todos',
            'tipo_transacao' => in_array($request->input('tipo_transacao'), ['debito', 'credito', 'pix'], true)
                ? (string) $request->input('tipo_transacao')
                : '',
            'instituicao' => trim((string) $request->input('instituicao')),
            'busca' => trim((string) $request->input('busca')),
            'por_pagina' => in_array($porPagina, [50, 100, 200], true) ? $porPagina : 100,
        ];
    }

    private function mesSelecionado(Request $request): Carbon
    {
        if (filled($request->input('mes_numero')) || filled($request->input('ano'))) {
            $ano = filled($request->input('ano')) ? (int) $request->input('ano') : (int) now()->format('Y');
            $mes = filled($request->input('mes_numero')) ? (int) $request->input('mes_numero') : (int) now()->format('n');

            return Carbon::create($ano, $mes, 1)->startOfMonth();
        }

        if (filled($request->input('mes'))) {
            return Carbon::parse($request->input('mes').'-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function baseQuery(array $filtros): Builder
    {
        $query = DB::table('edi_movimentos as em')
            ->leftJoin('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereBetween('em.data_inicial_transacao', [$filtros['inicio'], $filtros['fim']]);

        if ($filtros['status'] === 'faturaveis') {
            EdiStatusPagamento::aplicarSomenteFaturaveis($query, 'em.status_pagamento');
        } elseif ($filtros['status'] === 'canceladas') {
            EdiStatusPagamento::aplicarSomenteCanceladas($query, 'em.status_pagamento');
        } elseif (in_array($filtros['status'], ['01', '02', '03', '04'], true)) {
            $query->where('em.status_pagamento', $filtros['status']);
        }

        if ($filtros['tipo_transacao'] !== '') {
            $query->where('em.tipo_transacao', $filtros['tipo_transacao']);
        }

        if ($filtros['instituicao'] !== '') {
            $query->where('em.instituicao_financeira', $filtros['instituicao']);
        }

        if ($filtros['busca'] !== '') {
            $busca = $filtros['busca'];
            $like = '%'.$busca.'%';

            $query->where(function (Builder $query) use ($busca, $like) {
                $query->where('em.movimento_api_codigo', $busca)
                    ->orWhere('em.tx_id', $busca)
                    ->orWhere('em.codigo_transacao', $busca)
                    ->orWhere('em.codigo_venda', $busca)
                    ->orWhere('em.nsu', $busca)
                    ->orWhere('em.estabelecimento', $busca)
                    ->orWhere('em.codigo_autorizacao', $busca)
                    ->orWhere('e.cnpj', $busca)
                    ->orWhere('e.cpf', $busca)
                    ->orWhere('e.nome_fantasia', 'like', $like)
                    ->orWhere('e.razao_social', 'like', $like)
                    ->orWhere('e.nome_completo', 'like', $like);
            });
        }

        return $query;
    }

    private function totais(Builder $query): object
    {
        return $query
            ->selectRaw('
                COUNT(*) as total_transacoes,
                COALESCE(SUM(em.valor_total_transacao), 0) as valor_total,
                COALESCE(SUM(CASE WHEN em.status_pagamento IN ("04", "4") OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%cancel%" OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%estorn%" OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%chargeback%" OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%refund%" OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%devol%" THEN em.valor_total_transacao ELSE 0 END), 0) as valor_cancelado,
                SUM(CASE WHEN em.status_pagamento IN ("04", "4") OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%cancel%" OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%estorn%" OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%chargeback%" OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%refund%" OR LOWER(COALESCE(em.status_pagamento, "")) LIKE "%devol%" THEN 1 ELSE 0 END) as qtd_canceladas
            ')
            ->first();
    }

    private function porStatus(Builder $query)
    {
        return $query
            ->selectRaw('COALESCE(NULLIF(em.status_pagamento, ""), "sem") as status, COUNT(*) as quantidade, COALESCE(SUM(em.valor_total_transacao), 0) as total')
            ->groupByRaw('COALESCE(NULLIF(em.status_pagamento, ""), "sem")')
            ->orderByDesc('quantidade')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'status' => (string) $row->status,
                'label' => $this->statusLabel((string) $row->status),
                'quantidade' => (int) $row->quantidade,
                'total' => (float) $row->total,
            ]);
    }

    private function porTipo(Builder $query)
    {
        return $query
            ->selectRaw('COALESCE(NULLIF(em.tipo_transacao, ""), "sem") as tipo, COUNT(*) as quantidade, COALESCE(SUM(em.valor_total_transacao), 0) as total')
            ->groupByRaw('COALESCE(NULLIF(em.tipo_transacao, ""), "sem")')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'tipo' => (string) $row->tipo,
                'quantidade' => (int) $row->quantidade,
                'total' => (float) $row->total,
            ]);
    }

    private function statusOptions(): array
    {
        return [
            'todos' => 'Todos',
            'faturaveis' => 'Somente faturáveis',
            'canceladas' => 'Somente canceladas',
            '03' => 'Concluído (03)',
            '01' => 'Novo (01)',
            '02' => 'Agendado (02)',
            '04' => 'Cancelado (04)',
        ];
    }

    public function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            '03', '3' => 'Concluído',
            '01', '1' => 'Novo',
            '02', '2' => 'Agendado',
            '04', '4' => 'Cancelado',
            'sem', '' => 'Sem status',
            default => 'Status '.$status,
        };
    }
}
