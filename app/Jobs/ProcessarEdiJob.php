<?php

namespace App\Jobs;

use App\Services\EdiProcessadorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessarEdiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $estabelecimentoId,
        public string $data,
        public string $tipoMovimento = 'transactional',
        public int $pagina = 1,
        public ?array $payload = null,
    ) {}

    public function handle(EdiProcessadorService $service): void
    {
        $service->processarPagina(
            $this->estabelecimentoId,
            $this->data,
            $this->tipoMovimento,
            $this->pagina,
            $this->payload
        );
    }
}
