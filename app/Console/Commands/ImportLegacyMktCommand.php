<?php

namespace App\Console\Commands;

use App\Services\LegacyMktImportService;
use Illuminate\Console\Command;

class ImportLegacyMktCommand extends Command
{
    protected $signature = 'import:legacy-mkt
                            {file : Caminho do arquivo estabelecimentos.xlsx}
                            {--dry-run : Simula sem gravar no banco}
                            {--include-test : Inclui registros de teste (ex: SUA MARCA)}';

    protected $description = 'Importa marketplaces (tag MKT) do Excel legado, sem master e sem branding';

    public function handle(LegacyMktImportService $service): int
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

        $this->info('Importando marketplaces (MKT)...');

        $resultado = $service->importar($path, $dryRun, $skipTest);

        $headers = ['Fantasia', 'Token PagBank', 'Status', 'Mensagem', 'ID'];
        $rows = array_map(fn (array $linha) => [
            $linha['fantasia'],
            $linha['token'],
            $linha['status'],
            $linha['mensagem'],
            $linha['usuario_id'] ?? '—',
        ], $resultado['linhas']);

        $this->table($headers, $rows);

        $this->newLine();
        $this->info("Criados: {$resultado['criados']}");
        $this->line("Ignorados (já existiam / teste): {$resultado['ignorados']}");
        $this->line("Erros: {$resultado['erros']}");

        return $resultado['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
