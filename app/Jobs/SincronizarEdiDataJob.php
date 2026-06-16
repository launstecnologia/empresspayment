<?php

namespace App\Jobs;

use App\Services\EdiProcessadorService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SincronizarEdiDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public string $data,
        public string $tipoMovimento = 'transactional',
        public ?int $estabelecimentoIdFiltro = null,
    ) {}

    public function handle(EdiProcessadorService $service): void
    {
        $service->buscarEdiPorData(
            Carbon::parse($this->data)->startOfDay(),
            $this->tipoMovimento,
            $this->estabelecimentoIdFiltro,
        );
    }
}
