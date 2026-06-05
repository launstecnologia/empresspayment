<?php

namespace App\Jobs;

use App\Models\KycAnalise;
use App\Services\KycHistoricoService;
use App\Services\ReceitaFederalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ConsultarReceitaKycJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public KycAnalise $kycAnalise) {}

    public function handle(ReceitaFederalService $receita, KycHistoricoService $historico): void
    {
        $kyc = $this->kycAnalise->fresh('estabelecimento');
        $estabelecimento = $kyc->estabelecimento;

        try {
            $receita->aplicarConsulta($kyc, $estabelecimento);
            $historico->registrar($kyc, 'receita_consultada', 'Consulta Receita Federal concluída');
        } catch (\Throwable $e) {
            $historico->registrar($kyc, 'receita_erro', $e->getMessage());
        }
    }
}
