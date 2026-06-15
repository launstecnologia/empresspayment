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
                            {--calcular : Dispara cálculo de royalties e agregação após enfileirar}';

    protected $description = 'Enfileira busca de EDI PagBank para estabelecimentos com token ativo';

    public function handle(EdiProcessadorService $service): int
    {
        if (! PlatformSettings::ediConfigurado()) {
            $this->error('Token EDI não configurado. Configure em Admin → Configurações → PagBank.');

            return self::FAILURE;
        }

        if ($this->option('ontem')) {
            $this->info('Enfileirando busca EDI de ontem (todos os estabelecimentos)...');
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
        $this->line("Estabelecimentos elegíveis: {$elegiveis}");

        if ($elegiveis === 0) {
            $this->warn('Nenhum estabelecimento com pagbank_edi_ativo e token_pagseguro.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Enfileirar sincronização EDI?', true)) {
            return self::SUCCESS;
        }

        $resultado = $service->enfileirarSincronizacao(
            $de,
            $ate,
            filled($this->option('estabelecimento')) ? (int) $this->option('estabelecimento') : null,
            filled($this->option('limit')) ? (int) $this->option('limit') : null,
        );

        $this->newLine();
        $this->info("Jobs enfileirados: {$resultado['enfileirados']} estabelecimentos (~{$resultado['dias']} dias no total)");
        $this->line('Certifique-se de que o worker está rodando: docker compose exec -T queue php artisan queue:work');

        return $this->finalizar($this->option('calcular'));
    }

    private function finalizar(bool $calcular): int
    {
        if ($calcular) {
            CalcularRoyaltiesJob::dispatch();
            AgregarFaturamentoJob::dispatch();
            $this->line('Jobs de royalties e faturamento enfileirados (executam após processar EDI).');
        }

        return self::SUCCESS;
    }
}
