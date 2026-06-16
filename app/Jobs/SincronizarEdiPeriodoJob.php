<?php

namespace App\Jobs;

use App\Services\EdiProcessadorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SincronizarEdiPeriodoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(
        public string $de,
        public string $ate,
        public ?int $estabelecimentoIdFiltro = null,
    ) {}

    public function handle(EdiProcessadorService $service): void
    {
        $inicio = \Carbon\Carbon::parse($this->de)->startOfDay();
        $fim = \Carbon\Carbon::parse($this->ate)->startOfDay();

        for ($data = $inicio->copy(); $data->lte($fim); $data->addDay()) {
            $resultado = $service->importarDiaCompleto(
                $data->format('Y-m-d'),
                'transactional',
                $this->estabelecimentoIdFiltro,
            );

            Log::info('EDI PagBank: dia do período processado', array_merge($resultado, [
                'data' => $data->format('Y-m-d'),
            ]));
        }
    }
}
