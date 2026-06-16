<?php

namespace App\Jobs;

use App\Services\RoyaltyCalculadorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalcularRoyaltiesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public ?string $data = null) {}

    public function handle(RoyaltyCalculadorService $service): void
    {
        $processados = $service->calcularPendentes(500, $this->data);

        if ($processados === 500) {
            self::dispatch($this->data);

            return;
        }

        if ($this->data) {
            AgregarFaturamentoJob::dispatch($this->data);
        }
    }
}
