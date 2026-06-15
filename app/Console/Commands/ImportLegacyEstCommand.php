<?php

namespace App\Console\Commands;

use App\Services\LegacyEstImportService;
use Illuminate\Console\Command;

class ImportLegacyEstCommand extends Command
{
    protected $signature = 'import:legacy-est
                            {file : Caminho do arquivo estabelecimentos.xlsx}
                            {--dry-run : Simula sem gravar no banco}
                            {--include-test : Inclui registros de teste}
                            {--limit= : Limita quantidade de linhas processadas}
                            {--offset=0 : Pula as primeiras N linhas de estabelecimento}
                            {--detalhado : Exibe todas as linhas processadas}';

    protected $description = 'Importa estabelecimentos do Excel legado, vinculados a marketplace e revenda';

    public function handle(LegacyEstImportService $service): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Arquivo não encontrado ou sem permissão: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipTest = ! $this->option('include-test');
        $limit = filled($this->option('limit')) ? (int) $this->option('limit') : null;
        $offset = (int) $this->option('offset');

        if ($dryRun) {
            $this->warn('Modo dry-run — nenhum registro será gravado.');
        }

        $this->info('Importando estabelecimentos...');
        $this->line('Requer marketplaces (import:legacy-mkt) e revendas (import:legacy-rep) já importados.');

        $resultado = $service->importar($path, $dryRun, $skipTest, $limit, $offset);

        $linhasExibir = $this->option('detalhado')
            ? $resultado['linhas']
            : array_values(array_filter(
                $resultado['linhas'],
                fn (array $linha) => $linha['status'] !== 'criado'
                    || str_contains($linha['mensagem'], 'não encontrad')
                    || str_contains($linha['mensagem'], 'ignorada')
                    || str_contains($linha['mensagem'], 'Plano não mapeado'),
            ));

        if ($linhasExibir !== []) {
            $headers = ['Fantasia', 'Token', 'Marketplace', 'Status', 'Mensagem', 'Estab. ID', 'MKT ID', 'REP ID'];
            $rows = array_map(fn (array $linha) => [
                $linha['fantasia'],
                $linha['token'],
                $linha['marketplace'],
                $linha['status'],
                $linha['mensagem'],
                $linha['estabelecimento_id'] ?? '—',
                $linha['marketplace_id'] ?? '—',
                $linha['revenda_id'] ?? '—',
            ], $linhasExibir);

            $this->table($headers, $rows);
        } elseif (! $this->option('detalhado')) {
            $this->line('Nenhum erro ou registro ignorado. Use --detalhado para ver todas as linhas.');
        }

        $this->newLine();
        $this->info("Criados: {$resultado['criados']}");
        $this->line("Ignorados (já existiam / teste): {$resultado['ignorados']}");
        $this->line("Erros: {$resultado['erros']}");

        return $resultado['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
