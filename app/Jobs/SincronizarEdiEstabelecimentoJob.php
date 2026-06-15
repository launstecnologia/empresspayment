<?php

namespace App\Jobs;

use App\Models\Estabelecimento;
use App\Services\EdiProcessadorService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SincronizarEdiEstabelecimentoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public int $estabelecimentoId,
        public string $de,
        public string $ate,
    ) {}

    public function handle(EdiProcessadorService $service): void
    {
        $estabelecimento = Estabelecimento::withoutGlobalScopes()->find($this->estabelecimentoId);

        if (! $estabelecimento) {
            return;
        }

        $inicio = Carbon::parse($this->de)->startOfDay();
        $fim = Carbon::parse($this->ate)->startOfDay();

        for ($data = $inicio->copy(); $data->lte($fim); $data->addDay()) {
            $service->buscarEdiDisponivel($estabelecimento, $data);
        }
    }
}
