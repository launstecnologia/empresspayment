<?php

namespace App\Jobs;

use App\Models\Estabelecimento;
use App\Services\EstabelecimentoEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CriarEmailEstabelecimentoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Estabelecimento $estabelecimento) {}

    public function handle(EstabelecimentoEmailService $emails): void
    {
        if (! config('directadmin.criar_email_ao_habilitar', true)) {
            return;
        }

        $emails->provisionarAutomatico($this->estabelecimento);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha ao criar e-mail do estabelecimento', [
            'estabelecimento_id' => $this->estabelecimento->id,
            'erro' => $exception->getMessage(),
        ]);
    }
}
