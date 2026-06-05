<?php

namespace App\Jobs;

use App\Models\EstabelecimentoEmail;
use App\Services\ImapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SincronizarContaEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public EstabelecimentoEmail $conta) {}

    public function handle(ImapService $imap): void
    {
        if (! $this->conta->ativo) {
            return;
        }

        $imap->sincronizar($this->conta);
    }
}
