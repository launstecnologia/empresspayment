<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\AggregatedRevenue;
use App\Models\EdiMovimento;
use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Support\EdiMovimentoDetalhe;
use App\Support\InstituicaoFinanceira;
use App\Services\RoyaltyCalculadorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    public function faturamento(Request $request)
    {
        $query = AggregatedRevenue::query()
            ->with([
                'estabelecimento.plano',
                'marketplace',
                'master',
                'revenda',
            ])
            ->latest('data');

        $this->aplicarFiltrosFaturamento($query, $request);

        $usuario = $request->user();
        if ($usuario instanceof SubUsuario) {
            $usuario = $usuario->dono;
        }

        $totais = (clone $query)->selectRaw('
            COALESCE(SUM(total_transacoes), 0) as total_transacoes,
            COALESCE(SUM(total_valor), 0) as total_valor,
            COALESCE(SUM(total_royalty), 0) as total_royalty
        ')->first();

        $linhas = $query->paginate(50)->withQueryString();
        $linhas->getCollection()->transform(function (AggregatedRevenue $linha) use ($usuario) {
            $linha->setAttribute('comissao_exibida', $this->comissaoExibida($linha, $usuario));

            return $linha;
        });

        $totalRoyaltyExibido = $linhas->getCollection()->sum(fn ($linha) => $linha->comissao_exibida ?? $linha->total_royalty);

        return view('relatorio.faturamento', [
            'linhas' => $linhas,
            'totais' => $totais,
            'totalRoyaltyExibido' => $totalRoyaltyExibido,
            'filtros' => $request->only([
                'estabelecimento',
                'master_id',
                'marketplace_id',
                'revenda_id',
                'tipo_transacao',
                'instituicao',
                'status_pagamento',
                'data_inicio',
                'data_fim',
            ]),
            'masters' => $this->usuariosPorTipo('master'),
            'marketplaces' => $this->usuariosPorTipo('marketplace'),
            'revendas' => $this->usuariosPorTipo('revenda'),
            'instituicoes' => InstituicaoFinanceira::codigos(),
            'tiposTransacao' => ['debito', 'credito', 'pix'],
        ]);
    }

    public function faturamentoDetalhe(AggregatedRevenue $linha, Request $request, RoyaltyCalculadorService $royaltyService)
    {
        $usuario = $request->user();
        if ($usuario instanceof SubUsuario) {
            $usuario = $usuario->dono;
        }

        $movimentos = EdiMovimento::withoutGlobalScopes()
            ->with(['estabelecimento.plano', 'royalties.usuario'])
            ->where('estabelecimento_id', $linha->estabelecimento_id)
            ->whereDate('data_inicial_transacao', $linha->data)
            ->where('instituicao_financeira', $linha->instituicao)
            ->where('tipo_transacao', $linha->tipo_transacao)
            ->where('status_pagamento', $linha->status_pagamento)
            ->orderBy('hora_inicial_transacao')
            ->get()
            ->map(function (EdiMovimento $movimento) use ($royaltyService) {
                $taxa = $royaltyService->planoTaxaDoMovimento($movimento);

                return [
                    'id' => $movimento->id,
                    'codigo' => $movimento->movimento_api_codigo,
                    'valor_total' => (float) $movimento->valor_total_transacao,
                    'campos_edi' => EdiMovimentoDetalhe::campos($movimento),
                    'plano_taxa' => $taxa ? [
                        'id' => $taxa->id,
                        'taxa_percentual' => (float) $taxa->taxa_percentual,
                        'arranjo_ur' => $taxa->arranjo_ur,
                        'instituicao' => $taxa->instituicao,
                        'tipo_transacao' => $taxa->tipo_transacao,
                        'parcelas' => $taxa->parcelas,
                    ] : null,
                    'comissoes' => $movimento->royalties->map(fn ($royalty) => [
                        'usuario' => $royalty->usuario?->nomeExibicao() ?? '—',
                        'nivel' => $royalty->nivel,
                        'percentual' => (float) $royalty->percentual_royalty,
                        'valor' => (float) $royalty->valor_royalty,
                    ])->values(),
                ];
            });

        return response()->json([
            'resumo' => [
                'data' => $linha->data?->format('d/m/Y'),
                'instituicao' => $linha->instituicao,
                'tipo_transacao' => $linha->tipo_transacao,
                'total_transacoes' => $linha->total_transacoes,
                'total_valor' => (float) $linha->total_valor,
                'comissao' => $this->comissaoExibida($linha, $usuario),
                'estabelecimento' => $linha->estabelecimento?->nome_fantasia
                    ?: $linha->estabelecimento?->razao_social
                    ?: $linha->estabelecimento?->nome_completo,
                'plano' => $linha->estabelecimento?->plano?->nome,
                'marketplace' => $linha->marketplace?->nomeExibicao(),
            ],
            'movimentos' => $movimentos,
        ]);
    }

    private function aplicarFiltrosFaturamento(Builder $query, Request $request): void
    {
        if ($request->filled('estabelecimento')) {
            $termo = '%'.$request->string('estabelecimento')->trim().'%';
            $query->whereHas('estabelecimento', function (Builder $estabelecimento) use ($termo) {
                $estabelecimento->where(function (Builder $q) use ($termo) {
                    $q->where('nome_fantasia', 'like', $termo)
                        ->orWhere('razao_social', 'like', $termo)
                        ->orWhere('nome_completo', 'like', $termo);
                });
            });
        }

        if ($request->filled('master_id')) {
            $query->where('master_id', $request->integer('master_id'));
        }

        if ($request->filled('marketplace_id')) {
            $query->where('marketplace_id', $request->integer('marketplace_id'));
        }

        if ($request->filled('revenda_id')) {
            $query->where('revenda_id', $request->integer('revenda_id'));
        }

        if ($request->filled('tipo_transacao')) {
            $query->where('tipo_transacao', $request->string('tipo_transacao'));
        }

        if ($request->filled('instituicao')) {
            $query->where('instituicao', $request->string('instituicao'));
        }

        if ($request->filled('status_pagamento')) {
            $query->where('status_pagamento', $request->string('status_pagamento'));
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('data', '>=', $request->date('data_inicio'));
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('data', '<=', $request->date('data_fim'));
        }

        if ($request->filled('ano')) {
            $query->where('ano', $request->integer('ano'));
        }

        if ($request->filled('mes')) {
            $query->where('mes', $request->integer('mes'));
        }
    }

    private function usuariosPorTipo(string $tipo)
    {
        return Usuario::query()
            ->where('tipo', $tipo)
            ->where('ativo', true)
            ->orderByRaw('COALESCE(nome_fantasia, razao_social, nome_completo, email)')
            ->get()
            ->map(fn (Usuario $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->nomeExibicao(),
            ]);
    }

    private function comissaoExibida(AggregatedRevenue $linha, Usuario|SubUsuario|null $usuario): float
    {
        $query = DB::table('transacao_royalties')
            ->join('edi_movimentos', 'edi_movimentos.id', '=', 'transacao_royalties.edi_movimento_id')
            ->whereDate('edi_movimentos.data_inicial_transacao', $linha->data)
            ->where('edi_movimentos.estabelecimento_id', $linha->estabelecimento_id)
            ->where('edi_movimentos.instituicao_financeira', $linha->instituicao)
            ->where('edi_movimentos.tipo_transacao', $linha->tipo_transacao)
            ->where('edi_movimentos.status_pagamento', $linha->status_pagamento);

        if ($usuario instanceof SubUsuario) {
            $usuario = $usuario->dono;
        }

        if ($usuario instanceof Usuario && $usuario->tipo !== 'admin') {
            $query->where('transacao_royalties.usuario_id', $usuario->id);
        }

        return (float) $query->sum('transacao_royalties.valor_royalty');
    }
}
