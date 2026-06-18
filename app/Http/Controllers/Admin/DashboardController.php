<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AggregatedRevenue;
use App\Models\Estabelecimento;
use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Services\DashboardApuracaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardApuracaoService $apuracaoService)
    {
        $periodo = $apuracaoService->periodoValido($request->integer('periodo', 30));
        $apuracao = $apuracaoService->apurar($periodo, $request->user());

        $usuario = $request->user();
        if ($usuario instanceof SubUsuario) {
            $usuario = $usuario->dono;
        }

        return view('admin.dashboard', [
            'periodo' => $periodo,
            'totalEstabelecimentos' => Estabelecimento::count(),
            'faturamentoMes' => AggregatedRevenue::where('ano', now()->year)->where('mes', now()->month)->sum('total_valor'),
            'royaltiesMes' => $this->royaltiesMes($usuario),
            'planosResumo' => $apuracao['planos'],
            'resumoPlanos' => $apuracao['resumo'],
            'transacoesStatus' => $apuracao['transacoes_status'],
            'faturamentoBandeiras' => $apuracao['faturamento_bandeiras'],
        ]);
    }

    private function royaltiesMes(mixed $usuario): float
    {
        // Parceiro (MKT/revenda): soma o próprio royalty repassado.
        if ($usuario instanceof Usuario && $usuario->tipo !== 'admin') {
            return (float) DB::table('transacao_royalties')
                ->join('edi_movimentos', 'edi_movimentos.id', '=', 'transacao_royalties.edi_movimento_id')
                ->whereYear('edi_movimentos.data_inicial_transacao', now()->year)
                ->whereMonth('edi_movimentos.data_inicial_transacao', now()->month)
                ->where('transacao_royalties.usuario_id', $usuario->id)
                ->sum('transacao_royalties.valor_royalty');
        }

        // Admin: comissão da plataforma (comissao_percentual da taxa do plano × faturamento).
        return (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->join('plano_taxas as pt', function ($join) {
                $join->on('pt.plano_id', '=', 'e.plano_id')
                    ->on('pt.arranjo_ur', '=', 'em.arranjo_ur')
                    ->on('pt.parcelas', '=', DB::raw('COALESCE(NULLIF(em.quantidade_parcela, 0), 1)'))
                    ->where('pt.ativo', true);
            })
            ->whereYear('em.data_inicial_transacao', now()->year)
            ->whereMonth('em.data_inicial_transacao', now()->month)
            ->whereNotNull('e.plano_id')
            ->whereNotNull('pt.comissao_percentual')
            ->sum(DB::raw('em.valor_total_transacao * pt.comissao_percentual / 100'));
    }
}
