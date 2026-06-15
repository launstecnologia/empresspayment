<?php

namespace App\Console\Commands;

use App\Services\LegacyRepImportService;
use Illuminate\Console\Command;

class ImportLegacyRepCommand extends Command
{
    protected $signature = 'import:legacy-rep
                            {file : Caminho do arquivo estabelecimentos.xlsx}
                            {--dry-run : Simula sem gravar no banco}
                            {--include-test : Inclui registros de teste}';

    protected $description = 'Importa revendas (tag REP) do Excel legado, vinculadas ao marketplace';

    public function handle(LegacyRepImportService $service): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Arquivo não encontrado ou sem permissão: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipTest = ! $this->option('include-test');

        if ($dryRun) {
            $this->warn('Modo dry-run — nenhum registro será gravado.');
        }

        $this->info('Importando revendas (REP)...');
        $this->line('Requer marketplaces já importados (import:legacy-mkt).');

        $resultado = $service->importar($path, $dryRun, $skipTest);

        $headers = ['Fantasia', 'Token PagBank', 'Marketplace', 'Status', 'Mensagem', 'Revenda ID', 'MKT ID'];
        $rows = array_map(fn (array $linha) => [
            $linha['fantasia'],
            $linha['token'],
            $linha['marketplace'],
            $linha['status'],
            $linha['mensagem'],
            $linha['usuario_id'] ?? '—',
            $linha['marketplace_id'] ?? '—',
        ], $resultado['linhas']);

        $this->table($headers, $rows);

        $this->newLine();
        $this->info("Criados: {$resultado['criados']}");
        $this->line("Ignorados (já existiam / teste): {$resultado['ignorados']}");
        $this->line("Erros: {$resultado['erros']}");

        return $resultado['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
