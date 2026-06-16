<?php

namespace App\Console\Commands;

use App\Models\EdiMovimento;
use App\Services\FaturamentoAgregadorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EdiReagregarCommand extends Command
{
    protected $signature = 'edi:reagregar
                            {--de= : Data inicial (Y-m-d)}
                            {--ate= : Data final (Y-m-d), padrão: ontem}
                            {--dry-run : Apenas exibe o que seria feito}
                            {--force : Executa sem confirmação}';

    protected $description = 'Remove agregação stale e reconstrói aggregated_revenue a partir do EDI';

    public function handle(FaturamentoAgregadorService $agregador): int
    {
        $ate = filled($this->option('ate'))
            ? Carbon::parse($this->option('ate'))->startOfDay()
            : now()->subDay()->startOfDay();

        $de = filled($this->option('de'))
            ? Carbon::parse($this->option('de'))->startOfDay()
            : $ate->copy()->startOfMonth();

        if ($de->gt($ate)) {
            [$de, $ate] = [$ate, $de];
        }

        $inicio = $de->format('Y-m-d');
        $fim = $ate->format('Y-m-d');

        $this->info('Reagregação de faturamento');
        $this->line("Período: {$inicio} → {$fim}");
        $this->newLine();

        $resumo = $agregador->resumoAgregadoPeriodo($inicio, $fim);
        $edi = (float) EdiMovimento::withoutGlobalScopes()
            ->whereNotNull('estabelecimento_id')
            ->whereBetween('data_inicial_transacao', [$inicio, $fim])
            ->sum('valor_total_transacao');

        $this->comment('── Antes ──');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Linhas aggregated_revenue', number_format($resumo['linhas'], 0, ',', '.')],
                ['Soma aggregated_revenue', $this->moeda($resumo['total'])],
                ['Soma edi_movimentos', $this->moeda($edi)],
                ['Diferença (agg − EDI)', $this->moeda($resumo['total'] - $edi)],
            ],
        );

        if ($resumo['por_tipo'] !== []) {
            $this->line('Por tipo_transacao no agregado:');
            $this->table(
                ['tipo_transacao', 'Total'],
                collect($resumo['por_tipo'])->map(fn ($row) => [
                    $row['tipo'],
                    $this->moeda($row['total']),
                ])->all(),
            );
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('Dry-run — nenhuma alteração feita.');
            $this->line("Seriam removidas {$resumo['linhas']} linhas e reagregados ".($de->diffInDays($ate) + 1).' dia(s).');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Remover agregação do período e reconstruir a partir do EDI?', false)) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->comment('Reagregando…');

        $resultado = $agregador->reagregarPeriodo($de, $ate);

        $this->newLine();
        $this->comment('── Depois ──');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Linhas removidas', number_format($resultado['removidos'], 0, ',', '.')],
                ['Dias reagregados', number_format($resultado['dias_com_dados'], 0, ',', '.')],
                ['Grupos gravados', number_format($resultado['grupos'], 0, ',', '.')],
                ['Agg antes', $this->moeda($resultado['agg_antes'])],
                ['Agg depois', $this->moeda($resultado['agg_depois'])],
                ['EDI referência', $this->moeda($resultado['edi'])],
                ['Diferença final (agg − EDI)', $this->moeda($resultado['agg_depois'] - $resultado['edi'])],
            ],
        );

        $diff = abs($resultado['agg_depois'] - $resultado['edi']);

        if ($diff < 0.01) {
            $this->info('✓ Agregado alinhado com o EDI.');
        } elseif ($diff < 100) {
            $this->line('Diferença residual mínima — arredondamentos ou royalties parciais.');
        } else {
            $this->warn('Ainda há diferença — verifique edi:validar-faturamento.');
        }

        return self::SUCCESS;
    }

    private function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
