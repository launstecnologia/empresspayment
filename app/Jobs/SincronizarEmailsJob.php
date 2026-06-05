<?php

namespace App\Jobs;

use App\Models\EstabelecimentoEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SincronizarEmailsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        EstabelecimentoEmail::query()
            ->where('ativo', true)
            ->whereNotNull('senha_criptografada')
            ->orderBy('id')
            ->each(fn (EstabelecimentoEmail $conta) => SincronizarContaEmailJob::dispatch($conta));
    }
}
