<?php

namespace App\Jobs;

use App\Services\FaturamentoAgregadorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AgregarFaturamentoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $data = null) {}

    public function handle(FaturamentoAgregadorService $service): void
    {
        $service->agregar($this->data);
    }
}
