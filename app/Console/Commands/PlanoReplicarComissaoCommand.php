<?php

namespace App\Console\Commands;

use App\Models\Plano;
use App\Models\PlanoTaxa;
use Illuminate\Console\Command;

class PlanoReplicarComissaoCommand extends Command
{
    protected $signature = 'planos:replicar-comissao
                            {--de=EXPRESS73299p2 : Nome do plano de referência (origem da grade de comissão)}
                            {--dry-run : Simula sem gravar}
                            {--force : Sobrescreve comissao_percentual mesmo onde já existe}';

    protected $description = 'Replica a comissao_percentual (por tipo + parcelas) de um plano de referência para os demais planos';

    public function handle(): int
    {
        $refNome = (string) $this->option('de');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $referencia = Plano::query()->where('nome', $refNome)->first();

        if (! $referencia) {
            $this->error("Plano de referência não encontrado: {$refNome}");

            return self::FAILURE;
        }

        $mapa = [];
        PlanoTaxa::query()
            ->where('plano_id', $referencia->id)
            ->whereNotNull('comissao_percentual')
            ->get(['tipo_transacao', 'parcelas', 'comissao_percentual'])
            ->each(function (PlanoTaxa $taxa) use (&$mapa) {
                $mapa[$taxa->tipo_transacao.'|'.(int) $taxa->parcelas] = (float) $taxa->comissao_percentual;
            });

        if ($mapa === []) {
            $this->error("Plano de referência #{$referencia->id} ({$refNome}) não tem comissao_percentual cadastrada.");

            return self::FAILURE;
        }

        $this->info("Referência: #{$referencia->id} {$referencia->nome} — ".count($mapa).' combinações tipo/parcelas');
        $this->table(
            ['Tipo | Parcelas', 'Comissão %'],
            collect($mapa)->map(fn ($v, $k) => [$k, $v.'%'])->values()->all(),
        );

        $alvo = PlanoTaxa::query()
            ->where('plano_id', '!=', $referencia->id)
            ->when(! $force, fn ($q) => $q->whereNull('comissao_percentual'))
            ->get();

        $atualizados = 0;
        $semMapa = 0;
        $inalterados = 0;

        foreach ($alvo as $taxa) {
            $chave = $taxa->tipo_transacao.'|'.(int) $taxa->parcelas;

            if (! isset($mapa[$chave])) {
                $semMapa++;

                continue;
            }

            $novo = $mapa[$chave];

            if ((float) $taxa->comissao_percentual === $novo) {
                $inalterados++;

                continue;
            }

            if (! $dryRun) {
                $taxa->comissao_percentual = $novo;
                $taxa->save();
            }

            $atualizados++;
        }

        $this->newLine();
        $this->info($dryRun ? 'Simulação concluída.' : 'Replicação concluída.');
        $this->line("Taxas atualizadas: {$atualizados}");
        $this->line("Sem combinação na referência (ex.: PIX): {$semMapa}");
        $this->line("Já estavam corretas: {$inalterados}");

        if ($semMapa > 0) {
            $this->warn('Taxas sem combinação ficam sem comissão. Se quiser comissão de PIX/outros, adicione na referência antes.');
        }

        return self::SUCCESS;
    }
}
