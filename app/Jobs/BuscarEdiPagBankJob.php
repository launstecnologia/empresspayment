<?php

namespace App\Jobs;

use App\Services\EdiProcessadorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuscarEdiPagBankJob implements ShouldQueue
{
    use Queueable;

    public function handle(EdiProcessadorService $service): void
    {
        $service->buscarEdiPorData(now()->subDay());
    }
}
