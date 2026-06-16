<?php

namespace App\Console\Commands;

use App\Models\AggregatedRevenue;
use App\Models\EdiMovimento;
use App\Models\Estabelecimento;
use App\Models\Plano;
use App\Support\EdiTransacaoCategoria;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EdiValidarFaturamentoCommand extends Command
{
    protected $signature = 'edi:validar-faturamento
                            {--dias=30 : Período rolling da apuração do dashboard (7, 30 ou 90)}
                            {--mes= : Mês calendário para o card do dashboard (Y-m, padrão: mês atual)}
                            {--top=15 : Quantidade de estabelecimentos sem plano no ranking}';

    protected $description = 'Diagnostica discrepâncias entre faturamento do dashboard, EDI e apuração por plano';

    public function handle(): int
    {
        $dias = in_array((int) $this->option('dias'), [7, 30, 90], true)
            ? (int) $this->option('dias')
            : 30;

        $mesRef = filled($this->option('mes'))
            ? Carbon::parse($this->option('mes').'-01')->startOfMonth()
            : now()->startOfMonth();

        $desdeApuracao = now()->subDays($dias)->toDateString();
        $inicioMes = $mesRef->copy()->startOfMonth()->toDateString();
        $fimMes = $mesRef->copy()->endOfMonth()->toDateString();

        $this->info('Validação de faturamento — Express Payments');
        $this->line("Apuração dashboard: últimos {$dias} dias (desde {$desdeApuracao})");
        $this->line("Card \"Faturamento do mês\": {$mesRef->translatedFormat('F/Y')} ({$inicioMes} → {$fimMes})");
        $this->newLine();

        $this->secaoCardMes($mesRef, $inicioMes, $fimMes);
        $this->newLine();
        $this->secaoApuracaoPlanos($desdeApuracao, $dias);
        $this->newLine();
        $this->secaoEstabelecimentosSemPlano($desdeApuracao, (int) $this->option('top'));
        $this->newLine();
        $this->secaoMovimentosOrfaos($desdeApuracao);
        $this->newLine();
        $this->secaoResumoDiagnostico($mesRef, $inicioMes, $fimMes, $desdeApuracao, $dias);

        return self::SUCCESS;
    }

    private function secaoCardMes(Carbon $mesRef, string $inicioMes, string $fimMes): void
    {
        $this->comment('── Card "Faturamento do mês" vs EDI ──');

        $cardMes = (float) AggregatedRevenue::withoutGlobalScopes()
            ->where('ano', $mesRef->year)
            ->where('mes', $mesRef->month)
            ->sum('total_valor');

        $aggPorData = (float) AggregatedRevenue::withoutGlobalScopes()
            ->whereBetween('data', [$inicioMes, $fimMes])
            ->sum('total_valor');

        $ediMes = (float) EdiMovimento::withoutGlobalScopes()
            ->whereNotNull('estabelecimento_id')
            ->whereBetween('data_inicial_transacao', [$inicioMes, $fimMes])
            ->sum('valor_total_transacao');

        $ediMesTodos = (float) EdiMovimento::withoutGlobalScopes()
            ->whereBetween('data_inicial_transacao', [$inicioMes, $fimMes])
            ->sum('valor_total_transacao');

        $this->table(
            ['Fonte', 'Valor', 'Observação'],
            [
                ['aggregated_revenue (ano/mês)', $this->moeda($cardMes), 'Valor exibido no card do dashboard'],
                ['aggregated_revenue (por data)', $this->moeda($aggPorData), 'Soma por campo data no intervalo do mês'],
                ['edi_movimentos (vinculados)', $this->moeda($ediMes), 'Soma direta EDI com estabelecimento_id'],
                ['edi_movimentos (todos)', $this->moeda($ediMesTodos), 'Inclui movimentos sem vínculo'],
            ],
        );

        $diffAggEdi = $cardMes - $ediMes;
        if (abs($diffAggEdi) > 0.01) {
            $this->warn(sprintf(
                'Diferença aggregated vs EDI no mês: %s (%s)',
                $this->moeda(abs($diffAggEdi)),
                $diffAggEdi > 0 ? 'aggregated maior — verificar duplicatas ou dados fora do EDI' : 'EDI maior — reagregar faturamento',
            ));
        } else {
            $this->info('✓ aggregated_revenue e EDI do mês calendário estão alinhados.');
        }
    }

    private function secaoApuracaoPlanos(string $desdeApuracao, int $dias): void
    {
        $this->comment("── Apuração por plano (últimos {$dias} dias) ──");

        $categoriaSql = EdiTransacaoCategoria::sqlCategoria('em');

        $totalEdiVinculado = (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->sum('em.valor_total_transacao');

        $comPlano = (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->whereNotNull('e.plano_id')
            ->sum('em.valor_total_transacao');

        $semPlano = (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->whereNull('e.plano_id')
            ->sum('em.valor_total_transacao');

        $dashboardPlanos = (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->whereNotNull('e.plano_id')
            ->whereRaw("({$categoriaSql}) <> 'outros'")
            ->sum('em.valor_total_transacao');

        $categoriaOutros = (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->whereNotNull('e.plano_id')
            ->whereRaw("({$categoriaSql}) = 'outros'")
            ->sum('em.valor_total_transacao');

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['EDI vinculado (total)', $this->moeda($totalEdiVinculado)],
                ['Com plano_id', $this->moeda($comPlano)],
                ['Sem plano_id (fora da apuração)', $this->moeda($semPlano)],
                ['Dashboard planos (com plano, exc. "outros")', $this->moeda($dashboardPlanos)],
                ['Com plano mas categoria "outros"', $this->moeda($categoriaOutros)],
            ],
        );

        $porPlano = DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->leftJoin('planos as p', 'p.id', '=', 'e.plano_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->selectRaw('
                COALESCE(e.plano_id, 0) as plano_id,
                COALESCE(p.nome, "(sem plano)") as plano_nome,
                COUNT(DISTINCT e.id) as estabelecimentos,
                SUM(em.valor_total_transacao) as faturamento,
                SUM(CASE WHEN ('.$categoriaSql.') <> "outros" THEN em.valor_total_transacao ELSE 0 END) as faturamento_dashboard
            ')
            ->groupBy('e.plano_id', 'p.nome')
            ->orderByDesc('faturamento')
            ->get();

        $linhasPlano = $porPlano->map(fn ($row) => [
            $row->plano_id ?: '—',
            $row->plano_nome,
            number_format((int) $row->estabelecimentos, 0, ',', '.'),
            $this->moeda((float) $row->faturamento),
            $this->moeda((float) $row->faturamento_dashboard),
        ])->all();

        $this->newLine();
        $this->line('Faturamento por plano (inclui sem plano):');
        $this->table(
            ['Plano ID', 'Nome', 'Estab.', 'Faturamento EDI', 'No dashboard'],
            $linhasPlano,
        );

        $planosAtivosComDados = Plano::query()
            ->where('ativo', true)
            ->count();

        $planosComFaturamento = $porPlano
            ->filter(fn ($row) => $row->plano_id && (float) $row->faturamento_dashboard > 0)
            ->count();

        $this->line("Planos ativos no sistema: {$planosAtivosComDados}");
        $this->line("Planos com faturamento no dashboard: {$planosComFaturamento}");
    }

    private function secaoEstabelecimentosSemPlano(string $desdeApuracao, int $top): void
    {
        $this->comment('── Estabelecimentos sem plano_id ──');

        $totalSemPlano = Estabelecimento::withoutGlobalScopes()
            ->whereNull('plano_id')
            ->count();

        $ativosSemPlano = Estabelecimento::withoutGlobalScopes()
            ->whereNull('plano_id')
            ->where('ativo', true)
            ->count();

        $comMovimentoSemPlano = (int) DB::table('estabelecimentos as e')
            ->join('edi_movimentos as em', 'em.estabelecimento_id', '=', 'e.id')
            ->whereNull('e.plano_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->distinct('e.id')
            ->count('e.id');

        $this->line("Total sem plano_id: {$totalSemPlano}");
        $this->line("Ativos sem plano_id: {$ativosSemPlano}");
        $this->line("Com movimentos EDI no período: {$comMovimentoSemPlano}");

        $ranking = DB::table('estabelecimentos as e')
            ->join('edi_movimentos as em', 'em.estabelecimento_id', '=', 'e.id')
            ->whereNull('e.plano_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->selectRaw('
                e.id,
                COALESCE(NULLIF(e.nome_fantasia, ""), e.razao_social, e.nome_completo, CONCAT("ID ", e.id)) as nome,
                e.token_pagseguro,
                SUM(em.valor_total_transacao) as faturamento,
                COUNT(em.id) as movimentos
            ')
            ->groupBy('e.id', 'e.nome_fantasia', 'e.razao_social', 'e.nome_completo', 'e.token_pagseguro')
            ->orderByDesc('faturamento')
            ->limit($top)
            ->get();

        if ($ranking->isEmpty()) {
            $this->info('Nenhum estabelecimento sem plano com movimentos no período.');

            return;
        }

        $this->newLine();
        $this->line("Top {$top} estabelecimentos sem plano (por faturamento no período):");
        $this->table(
            ['ID', 'Nome', 'Token PagSeguro', 'Faturamento', 'Movimentos'],
            $ranking->map(fn ($row) => [
                $row->id,
                mb_strimwidth((string) $row->nome, 0, 40, '…'),
                $row->token_pagseguro ?: '—',
                $this->moeda((float) $row->faturamento),
                number_format((int) $row->movimentos, 0, ',', '.'),
            ])->all(),
        );
    }

    private function secaoMovimentosOrfaos(string $desdeApuracao): void
    {
        $this->comment('── Movimentos EDI sem vínculo ──');

        $query = EdiMovimento::withoutGlobalScopes()
            ->whereNull('estabelecimento_id')
            ->whereDate('data_inicial_transacao', '>=', $desdeApuracao);

        $qtd = (clone $query)->count();
        $valor = (float) (clone $query)->sum('valor_total_transacao');

        $tokens = DB::table('edi_movimentos')
            ->whereNull('estabelecimento_id')
            ->whereDate('data_inicial_transacao', '>=', $desdeApuracao)
            ->selectRaw('estabelecimento as token_edi, COUNT(*) as qtd, SUM(valor_total_transacao) as valor')
            ->groupBy('estabelecimento')
            ->orderByDesc('valor')
            ->limit(10)
            ->get();

        $this->line("Movimentos órfãos: ".number_format($qtd, 0, ',', '.')." — {$this->moeda($valor)}");

        if ($tokens->isNotEmpty()) {
            $this->newLine();
            $this->line('Tokens EDI sem match em estabelecimentos (top 10):');
            $this->table(
                ['Token EDI', 'Movimentos', 'Faturamento'],
                $tokens->map(fn ($row) => [
                    $row->token_edi ?: '—',
                    number_format((int) $row->qtd, 0, ',', '.'),
                    $this->moeda((float) $row->valor),
                ])->all(),
            );
        }
    }

    private function secaoResumoDiagnostico(
        Carbon $mesRef,
        string $inicioMes,
        string $fimMes,
        string $desdeApuracao,
        int $dias,
    ): void {
        $this->comment('── Diagnóstico ──');

        $cardMes = (float) AggregatedRevenue::withoutGlobalScopes()
            ->where('ano', $mesRef->year)
            ->where('mes', $mesRef->month)
            ->sum('total_valor');

        $categoriaSql = EdiTransacaoCategoria::sqlCategoria('em');

        $dashboardPlanos = (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->whereNotNull('e.plano_id')
            ->whereRaw("({$categoriaSql}) <> 'outros'")
            ->sum('em.valor_total_transacao');

        $semPlano = (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->whereDate('em.data_inicial_transacao', '>=', $desdeApuracao)
            ->whereNull('e.plano_id')
            ->sum('em.valor_total_transacao');

        $ediMes = (float) EdiMovimento::withoutGlobalScopes()
            ->whereNotNull('estabelecimento_id')
            ->whereBetween('data_inicial_transacao', [$inicioMes, $fimMes])
            ->sum('valor_total_transacao');

        $ediApuracao = (float) EdiMovimento::withoutGlobalScopes()
            ->whereNotNull('estabelecimento_id')
            ->whereDate('data_inicial_transacao', '>=', $desdeApuracao)
            ->sum('valor_total_transacao');

        $motivos = [];

        if (abs($cardMes - $dashboardPlanos) > 1000) {
            if ($cardMes > $dashboardPlanos && abs($cardMes - $ediApuracao) < abs($cardMes - $ediMes)) {
                $motivos[] = 'Card do topo usa mês calendário ('.$mesRef->format('m/Y').'); apuração usa últimos '.$dias.' dias — períodos diferentes.';
            }

            if ($semPlano > 1000) {
                $motivos[] = sprintf(
                    'Estabelecimentos sem plano_id concentram %s no período — não entram nos cards de plano.',
                    $this->moeda($semPlano),
                );
            }

            if (abs($cardMes - $ediMes) > 1000) {
                $motivos[] = sprintf(
                    'Faturamento agregado (%s) difere do EDI do mês (%s) — pode precisar reagregar.',
                    $this->moeda($cardMes),
                    $this->moeda($ediMes),
                );
            }

            if ($motivos === []) {
                $motivos[] = 'Grande parte do faturamento está em planos com pouca visibilidade no dashboard ou em categorias filtradas.';
            }
        } else {
            $motivos[] = 'Valores dentro de tolerância — discrepância visual pode ser só diferença de período.';
        }

        foreach ($motivos as $i => $motivo) {
            $this->line(($i + 1).'. '.$motivo);
        }

        $this->newLine();
        $this->line('Comparativo rápido:');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Card dashboard (mês calendário)', $this->moeda($cardMes)],
                ['Apuração planos ('.$dias.' dias)', $this->moeda($dashboardPlanos)],
                ['EDI vinculado ('.$dias.' dias)', $this->moeda($ediApuracao)],
                ['Sem plano_id ('.$dias.' dias)', $this->moeda($semPlano)],
            ],
        );
    }

    private function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
