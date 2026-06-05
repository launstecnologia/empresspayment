<?php

namespace App\Services;

use App\Jobs\CadastrarContaPagBankJob;
use App\Models\Estabelecimento;

class PagBankCadastroDispatcher
{
    public function enfileirar(Estabelecimento $estabelecimento, int $delaySegundos = 5): bool
    {
        if ($estabelecimento->pagbank_account_id) {
            return false;
        }

        CadastrarContaPagBankJob::dispatch($estabelecimento)
            ->delay(now()->addSeconds($delaySegundos));

        return true;
    }
}
