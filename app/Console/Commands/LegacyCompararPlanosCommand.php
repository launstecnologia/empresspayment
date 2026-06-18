<?php

namespace App\Console\Commands;

use App\Models\Estabelecimento;
use App\Models\Plano;
use App\Support\LegacyPlanoAlias;
use App\Support\SimpleXlsxReader;
use Illuminate\Console\Command;

class LegacyCompararPlanosCommand extends Command
{
    protected $signature = 'legacy:comparar-planos
                            {file=database/legacy/estabelecimentos.xlsx : Caminho do Excel legado}';

    protected $description = 'Cruza planos do Excel legado com os planos cadastrados na plataforma';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $planos = Plano::query()->orderBy('nome')->get(['id', 'nome', 'codigo_fv', 'ativo']);
        $cache = $this->montarCachePlanos($planos);
        $planoPorId = $planos->keyBy('id');
        $estabPorToken = $this->montarEstabelecimentosPorToken();

        $contagemExcel = [];
        $contagemResolvida = [];
        $semMatch = [];
        $divergentes = [];

        foreach (SimpleXlsxReader::rowsAssociativos($path) as $row) {
            if (in_array(strtoupper(trim((string) ($row['tag'] ?? ''))), ['MKT', 'REP'], true)) {
                continue;
            }

            $planCode = trim((string) ($row['plan_code'] ?? $row['plano_listagem'] ?? ''));
            if ($planCode === '') {
                continue;
            }

            $contagemExcel[$planCode] = ($contagemExcel[$planCode] ?? 0) + 1;

            $plano = LegacyPlanoAlias::resolverPlano($planCode, $cache);

            if ($plano) {
                $contagemResolvida[$plano->nome] = ($contagemResolvida[$plano->nome] ?? 0) + 1;

                $token = trim((string) ($row['token'] ?? $row['id_estabelecimento'] ?? ''));
                $estabelecimento = $token !== '' ? ($estabPorToken[$token] ?? null) : null;

                if (
                    $estabelecimento
                    && $estabelecimento->plano_id
                    && (int) $estabelecimento->plano_id !== (int) $plano->id
                ) {
                    $divergentes[] = [
                        $estabelecimento->id,
                        $estabelecimento->nome_fantasia
                            ?: $estabelecimento->razao_social
                            ?: $estabelecimento->nome_completo
                            ?: '—',
                        $token,
                        $planoPorId[$estabelecimento->plano_id]?->nome ?? "#{$estabelecimento->plano_id}",
                        $plano->nome,
                    ];
                }
            } else {
                $semMatch[$planCode] = ($semMatch[$planCode] ?? 0) + 1;
            }
        }

        $this->info('Planos cadastrados na plataforma');
        $this->table(
            ['ID', 'Nome', 'Código FV', 'Ativo'],
            $planos->map(fn (Plano $p) => [
                $p->id,
                $p->nome,
                $p->codigo_fv ?: '—',
                $p->ativo ? 'sim' : 'não',
            ])->all(),
        );

        $this->newLine();
        $this->comment('Excel → Plataforma (plan_code / plano_listagem)');

        arsort($contagemExcel);
        $linhas = [];

        foreach ($contagemExcel as $excel => $qtd) {
            $plano = LegacyPlanoAlias::resolverPlano($excel, $cache);
            $via = $this->origemMatch($excel, $plano, $cache);

            $linhas[] = [
                $excel,
                $qtd,
                $plano?->nome ?? '—',
                $plano ? '✓' : '✗',
                $via,
            ];
        }

        $this->table(['Excel', 'Qtd', 'Plano plataforma', 'Match', 'Via'], $linhas);

        $totalExcel = array_sum($contagemExcel);
        $totalMatch = array_sum($contagemResolvida);
        $totalSem = array_sum($semMatch);

        $this->newLine();
        $this->line("Estabelecimentos no Excel: {$totalExcel}");
        $this->line("Com plano resolvido: {$totalMatch}");
        $this->line("Sem match: {$totalSem}");

        if ($semMatch !== []) {
            $this->newLine();
            $this->warn('Valores do Excel sem plano na plataforma:');
            foreach ($semMatch as $valor => $qtd) {
                $this->line("  {$qtd}× {$valor}");
            }
        }

        $this->newLine();
        $this->comment('Distribuição final por plano da plataforma');
        arsort($contagemResolvida);

        $linhasPlataforma = [];
        foreach ($planos as $plano) {
            $qtd = $contagemResolvida[$plano->nome] ?? 0;
            $linhasPlataforma[] = [$plano->id, $plano->nome, $qtd];
        }

        usort($linhasPlataforma, fn ($a, $b) => $b[2] <=> $a[2]);
        $this->table(['ID', 'Plano plataforma', 'Estabelecimentos Excel'], $linhasPlataforma);

        $ociosos = $planos->filter(fn (Plano $p) => ($contagemResolvida[$p->nome] ?? 0) === 0);
        if ($ociosos->isNotEmpty()) {
            $this->newLine();
            $this->line('Planos na plataforma sem nenhum estabelecimento no Excel:');
            foreach ($ociosos as $plano) {
                $this->line("  • {$plano->nome}");
            }
        }

        $this->newLine();
        $this->comment('Estabelecimentos com plano divergente (banco × Excel)');

        if ($divergentes === []) {
            $this->info('Nenhum estabelecimento com plano divergente — banco e Excel batem.');
        } else {
            usort($divergentes, fn ($a, $b) => strcmp((string) $a[3], (string) $b[3]));
            $this->table(
                ['ID', 'Estabelecimento', 'Token', 'Plano atual (banco)', 'Plano Excel'],
                $divergentes,
            );
            $this->warn(count($divergentes).' estabelecimento(s) com plano divergente.');
            $this->line('Para alinhar com o Excel: php artisan legacy:backfill-planos --force');
        }

        return $semMatch === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, Estabelecimento>
     */
    private function montarEstabelecimentosPorToken(): array
    {
        return Estabelecimento::withoutGlobalScopes()
            ->whereNotNull('token_pagseguro')
            ->get(['id', 'nome_fantasia', 'razao_social', 'nome_completo', 'token_pagseguro', 'plano_id'])
            ->keyBy(fn (Estabelecimento $e) => trim((string) $e->token_pagseguro))
            ->all();
    }

    /**
     * @param  array<string, Plano>  $cache
     * @return array<string, Plano>
     */
    private function montarCachePlanos($planos): array
    {
        $cache = [];

        foreach ($planos as $plano) {
            foreach (LegacyPlanoAlias::chaves($plano->nome) as $chave) {
                $cache[$chave] = $plano;
            }

            if (filled($plano->codigo_fv)) {
                foreach (LegacyPlanoAlias::chaves($plano->codigo_fv) as $chave) {
                    $cache[$chave] = $plano;
                }
            }
        }

        return $cache;
    }

    /**
     * @param  array<string, Plano>  $cache
     */
    private function origemMatch(string $excel, ?Plano $plano, array $cache): string
    {
        if (! $plano) {
            return '—';
        }

        if (LegacyPlanoAlias::nomePlataforma($excel) === $plano->nome) {
            return 'alias legado';
        }

        foreach (LegacyPlanoAlias::chaves($excel) as $chave) {
            if (isset($cache[$chave]) && $cache[$chave]->id === $plano->id) {
                if (filled($plano->codigo_fv) && in_array($chave, LegacyPlanoAlias::chaves($plano->codigo_fv), true)) {
                    return 'codigo_fv';
                }

                return 'nome exato';
            }
        }

        return 'alias legado';
    }
}
