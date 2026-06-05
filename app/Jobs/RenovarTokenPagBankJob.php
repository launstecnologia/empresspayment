<?php

namespace App\Jobs;

use App\Models\Estabelecimento;
use App\Services\PagBankTokenService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RenovarTokenPagBankJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(PagBankTokenService $tokenService): void
    {
        $limite = now()->addDays((int) config('pagbank.renovacao_dias_antecedencia', 7));

        Estabelecimento::withoutGlobalScopes()
            ->whereNotNull('pagbank_account_id')
            ->whereNotNull('pagbank_refresh_token')
            ->where(function ($query) use ($limite) {
                $query->whereNull('pagbank_token_expira')
                    ->orWhere('pagbank_token_expira', '<=', $limite);
            })
            ->chunkById(50, function ($estabelecimentos) use ($tokenService) {
                foreach ($estabelecimentos as $estabelecimento) {
                    try {
                        $tokenService->renovar($estabelecimento);
                    } catch (\Throwable $e) {
                        Log::error('Falha ao renovar token PagBank', [
                            'estabelecimento_id' => $estabelecimento->id,
                            'account_id' => $estabelecimento->pagbank_account_id,
                            'erro' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
