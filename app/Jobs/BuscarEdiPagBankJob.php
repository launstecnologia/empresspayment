<?php

namespace App\Jobs;

use App\Models\Estabelecimento;
use App\Services\EdiProcessadorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuscarEdiPagBankJob implements ShouldQueue
{
    use Queueable;

    public function handle(EdiProcessadorService $service): void
    {
        $data = now()->subDay();

        Estabelecimento::withoutGlobalScopes()
            ->where('ativo', true)
            ->where('pagbank_edi_ativo', true)
            ->whereNotNull('token_pagseguro')
            ->where('token_pagseguro', '!=', '')
            ->chunkById(100, function ($estabelecimentos) use ($service, $data) {
                foreach ($estabelecimentos as $estabelecimento) {
                    $service->buscarEdiDisponivel($estabelecimento, $data);
                }
            });
    }
}
