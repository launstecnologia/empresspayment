<?php

namespace App\Console\Commands;

use App\Models\EdiMovimento;
use App\Services\EdiProcessadorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class EdiStatusCommand extends Command
{
    protected $signature = 'edi:status
                            {--de= : Data inicial (Y-m-d)}
                            {--ate= : Data final (Y-m-d)}';

    protected $description = 'Mostra progresso da importação EDI por dia e tamanho da fila Redis';

    public function handle(): int
    {
        $ate = filled($this->option('ate'))
            ? Carbon::parse($this->option('ate'))->startOfDay()
            : now()->subDay()->startOfDay();

        $de = filled($this->option('de'))
            ? Carbon::parse($this->option('de'))->startOfDay()
            : $ate->copy()->subDays(14)->startOfDay();

        $diasEsperados = $de->copy()->diffInDays($ate) + 1;

        $importados = EdiMovimento::withoutGlobalScopes()
            ->whereBetween('data_inicial_transacao', [$de->format('Y-m-d'), $ate->format('Y-m-d')])
            ->selectRaw('DATE(data_inicial_transacao) as dia, COUNT(*) as qtd')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        $linhas = [];
        $faltam = [];

        for ($data = $de->copy(); $data->lte($ate); $data->addDay()) {
            $chave = $data->format('Y-m-d');
            $qtd = (int) ($importados->get($chave)?->qtd ?? 0);
            $linhas[] = [$chave, $qtd > 0 ? number_format($qtd, 0, ',', '.') : '—'];

            if ($qtd === 0) {
                $faltam[] = $chave;
            }
        }

        $this->info("Período: {$de->format('d/m/Y')} → {$ate->format('d/m/Y')}");
        $this->line('Dias com dados: '.$importados->count()."/{$diasEsperados}");
        $this->newLine();
        $this->table(['Dia', 'Movimentos'], $linhas);

        $filaDefault = Queue::size('default');
        $filaAutomacao = Queue::size('automacao');

        $this->newLine();
        $this->line("Fila Redis (default): {$filaDefault}");
        $this->line("Fila Redis (automacao): {$filaAutomacao}");
        $this->comment('A fila usa Redis — DB::table("jobs") sempre mostra 0.');

        if ($faltam !== []) {
            $this->newLine();
            $this->warn('Faltam: '.implode(', ', $faltam));
        } else {
            $this->newLine();
            $this->info('✓ Período completo no banco.');
        }

        return self::SUCCESS;
    }
}
