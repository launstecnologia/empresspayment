<?php

namespace App\Console\Commands;

use App\Services\LegacyImportBackfillDatesService;
use Illuminate\Console\Command;

class ImportLegacyFixDatesCommand extends Command
{
    protected $signature = 'import:legacy-fix-datas
                            {file : Caminho do arquivo estabelecimentos.xlsx}
                            {--tipo=all : all, est, mkt ou rep}
                            {--dry-run : Simula sem gravar no banco}';

    protected $description = 'Corrige created_at dos registros legados já importados usando data_cadastro do Excel';

    public function handle(LegacyImportBackfillDatesService $service): int
    {
        $path = $this->argument('file');
        $tipo = strtolower((string) $this->option('tipo'));

        if (! is_readable($path)) {
            $this->error("Arquivo não encontrado ou sem permissão: {$path}");

            return self::FAILURE;
        }

        if (! in_array($tipo, ['all', 'est', 'mkt', 'rep'], true)) {
            $this->error('Tipo inválido. Use: all, est, mkt ou rep.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run — nenhuma data será alterada.');
        }

        $this->info("Corrigindo datas de cadastro ({$tipo})...");

        $resultado = $service->corrigir($path, $tipo, $dryRun);

        $this->newLine();
        $this->info("Atualizados: {$resultado['atualizados']}");
        $this->line("Ignorados (já corretos): {$resultado['ignorados']}");
        $this->line("Não encontrados no banco: {$resultado['nao_encontrados']}");
        $this->line("Sem data_cadastro no Excel: {$resultado['sem_data']}");

        return self::SUCCESS;
    }
}
