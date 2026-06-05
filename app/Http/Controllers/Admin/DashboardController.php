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
        $query = AggregatedRevenue::query()
            ->where('ano', now()->year)
            ->where('mes', now()->month);

        if ($usuario instanceof Usuario && $usuario->tipo !== 'admin') {
            return (float) DB::table('transacao_royalties')
                ->join('edi_movimentos', 'edi_movimentos.id', '=', 'transacao_royalties.edi_movimento_id')
                ->whereYear('edi_movimentos.data_inicial_transacao', now()->year)
                ->whereMonth('edi_movimentos.data_inicial_transacao', now()->month)
                ->where('transacao_royalties.usuario_id', $usuario->id)
                ->sum('transacao_royalties.valor_royalty');
        }

        return (float) $query->sum('total_royalty');
    }
}
