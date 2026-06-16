<?php

namespace App\Console\Commands;

use App\Jobs\AgregarFaturamentoJob;
use App\Jobs\BuscarEdiPagBankJob;
use App\Jobs\CalcularRoyaltiesJob;
use App\Models\Estabelecimento;
use App\Services\EdiProcessadorService;
use App\Support\PlatformSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EdiSincronizarCommand extends Command
{
    protected $signature = 'edi:sincronizar
                            {--de= : Data inicial (Y-m-d)}
                            {--ate= : Data final (Y-m-d), padrão: ontem}
                            {--dias=90 : Usado quando --de não informado}
                            {--estabelecimento= : ID específico}
                            {--limit= : Limita quantidade de estabelecimentos}
                            {--ontem : Busca só o dia anterior (job diário)}
                            {--force : Enfileira sem confirmação}
                            {--sync : Importa direto (sem fila), dia a dia}
                            {--calcular : Dispara cálculo de royalties e agregação após enfileirar}';

    protected $description = 'Enfileira busca de EDI PagBank para estabelecimentos com token ativo';

    public function handle(EdiProcessadorService $service): int
    {
        if (! PlatformSettings::ediConfigurado()) {
            $this->error('Credenciais EDI não configuradas (USER + TOKEN). Configure em Admin → Configurações → PagBank.');

            return self::FAILURE;
        }

        if ($this->option('ontem')) {
            $this->info('Enfileirando busca EDI de ontem (credencial parceiro 1xN)...');
            BuscarEdiPagBankJob::dispatch();
            $this->line('Job BuscarEdiPagBankJob enfileirado.');

            return $this->finalizar($this->option('calcular'));
        }

        $ate = filled($this->option('ate'))
            ? Carbon::parse($this->option('ate'))->startOfDay()
            : now()->subDay()->startOfDay();

        $de = filled($this->option('de'))
            ? Carbon::parse($this->option('de'))->startOfDay()
            : $ate->copy()->subDays(max(1, (int) $this->option('dias')) - 1)->startOfDay();

        $elegiveis = Estabelecimento::withoutGlobalScopes()
            ->where('ativo', true)
            ->where('pagbank_edi_ativo', true)
            ->whereNotNull('token_pagseguro')
            ->where('token_pagseguro', '!=', '')
            ->when($this->option('estabelecimento'), fn ($q) => $q->whereKey((int) $this->option('estabelecimento')))
            ->count();

        $this->info("Período: {$de->format('d/m/Y')} → {$ate->format('d/m/Y')}");
        $this->line("Estabelecimentos elegíveis (vínculo por token_pagseguro): {$elegiveis}");

        if ($elegiveis === 0 && ! $this->option('estabelecimento')) {
            $this->warn('Nenhum estabelecimento com pagbank_edi_ativo e token_pagseguro.');
        }

        if (! $this->option('force') && ! $this->option('sync') && ! $this->confirm('Enfileirar sincronização EDI?', true)) {
            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            return $this->sincronizarDireto($service, $de, $ate);
        }

        $resultado = $service->enfileirarSincronizacao(
            $de,
            $ate,
            filled($this->option('estabelecimento')) ? (int) $this->option('estabelecimento') : null,
            filled($this->option('limit')) ? (int) $this->option('limit') : null,
        );

        $this->newLine();
        $this->info("Job enfileirado: 1 (período {$resultado['dias']} dia(s), importação sequencial)");
        $this->line('Acompanhe: php artisan edi:status --de='.$de->format('Y-m-d').' --ate='.$ate->format('Y-m-d'));
        $this->line('Worker: docker compose logs queue -f --tail=20');

        return $this->finalizar($this->option('calcular'));
    }

    private function sincronizarDireto(EdiProcessadorService $service, Carbon $de, Carbon $ate): int
    {
        $this->info('Importação direta (sem fila)...');

        for ($data = $de->copy(); $data->lte($ate); $data->addDay()) {
            $dia = $data->format('Y-m-d');
            $resultado = $service->importarDiaCompleto($dia);

            if ($resultado['validado']) {
                $this->line("  {$dia} → ".number_format($resultado['importados'], 0, ',', '.')." movimentos ({$resultado['paginas']} pág.)");
            } else {
                $this->warn("  {$dia} → pulado (".($resultado['motivo'] ?? 'erro').')');
            }
        }

        return self::SUCCESS;
    }

    private function finalizar(bool $calcular): int
    {
        if ($calcular) {
            CalcularRoyaltiesJob::dispatch();
            $this->line('Job de royalties enfileirado (pendências globais). Faturamento roda ao concluir cada dia importado.');
        }

        return self::SUCCESS;
    }
}
