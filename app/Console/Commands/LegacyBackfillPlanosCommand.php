<?php

namespace App\Console\Commands;

use App\Models\Estabelecimento;
use App\Models\Plano;
use App\Services\RoyaltyCalculadorService;
use App\Support\LegacyPlanoAlias;
use App\Support\SimpleXlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyBackfillPlanosCommand extends Command
{
    protected $signature = 'legacy:backfill-planos
                            {file=database/legacy/estabelecimentos.xlsx : Caminho do Excel legado}
                            {--dry-run : Simula sem gravar}
                            {--force : Atualiza mesmo estabelecimentos que já têm plano_id}
                            {--recalcular-royalties : Recalcula royalties após vincular planos}';

    protected $description = 'Vincula plano_id nos estabelecimentos a partir do Excel legado';

    public function handle(RoyaltyCalculadorService $royaltyCalculador): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $cache = $this->montarCachePlanos();

        $atualizados = 0;
        $ignorados = 0;
        $semMatch = 0;
        $naoEncontrados = 0;
        $estabelecimentosAfetados = [];

        foreach (SimpleXlsxReader::rowsAssociativos($path) as $row) {
            if (in_array(strtoupper(trim((string) ($row['tag'] ?? ''))), ['MKT', 'REP'], true)) {
                continue;
            }

            $token = trim((string) ($row['token'] ?? $row['id_estabelecimento'] ?? ''));
            $planCode = trim((string) ($row['plan_code'] ?? $row['plano_listagem'] ?? ''));

            if ($token === '') {
                continue;
            }

            $plano = LegacyPlanoAlias::resolverPlano($planCode, $cache);

            if (! $plano) {
                if ($planCode !== '' && strcasecmp($planCode, 'Sem Plano') !== 0) {
                    $semMatch++;
                }

                continue;
            }

            $estabelecimento = Estabelecimento::withoutGlobalScopes()
                ->where('token_pagseguro', $token)
                ->first();

            if (! $estabelecimento) {
                $naoEncontrados++;

                continue;
            }

            if ($estabelecimento->plano_id && ! $force) {
                $ignorados++;

                continue;
            }

            if ((int) $estabelecimento->plano_id === (int) $plano->id) {
                $ignorados++;

                continue;
            }

            if ($dryRun) {
                $this->line("DRY  #{$estabelecimento->id} {$estabelecimento->nome_fantasia} → {$plano->nome}");
                $atualizados++;

                continue;
            }

            DB::transaction(function () use ($estabelecimento, $plano, $royaltyCalculador, &$estabelecimentosAfetados) {
                $estabelecimento->plano_id = $plano->id;
                $estabelecimento->save();

                $estabelecimento->load('plano.taxas.royalties');
                $royaltyCalculador->fixarCadeia($estabelecimento);

                $estabelecimentosAfetados[] = $estabelecimento->id;
            });

            $atualizados++;
        }

        $this->newLine();
        $this->info($dryRun ? 'Simulação concluída.' : 'Backfill concluído.');
        $this->line("Atualizados: {$atualizados}");
        $this->line("Ignorados (já tinham plano): {$ignorados}");
        $this->line("Token não encontrado no banco: {$naoEncontrados}");
        $this->line("Plan code sem match: {$semMatch}");

        if ($this->option('recalcular-royalties') && ! $dryRun && $estabelecimentosAfetados !== []) {
            $this->newLine();
            $this->comment('Recalcular royalties por movimento exige reprocessar EDI — rode edi:sincronizar --force no período desejado.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, Plano>
     */
    private function montarCachePlanos(): array
    {
        $cache = [];

        Plano::query()->get(['id', 'nome', 'codigo_fv'])->each(function (Plano $plano) use (&$cache) {
            foreach (LegacyPlanoAlias::chaves($plano->nome) as $chave) {
                $cache[$chave] = $plano;
            }

            if (filled($plano->codigo_fv)) {
                foreach (LegacyPlanoAlias::chaves($plano->codigo_fv) as $chave) {
                    $cache[$chave] = $plano;
                }
            }
        });

        return $cache;
    }
}
