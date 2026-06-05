<?php

namespace App\Jobs;

use App\Services\RoyaltyCalculadorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalcularRoyaltiesJob implements ShouldQueue
{
    use Queueable;

    public function handle(RoyaltyCalculadorService $service): void
    {
        $service->calcularPendentes();
    }
}
