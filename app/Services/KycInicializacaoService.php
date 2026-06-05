<?php

namespace App\Services;

use App\Jobs\ConsultarReceitaKycJob;
use App\Models\Estabelecimento;
use App\Models\KycAnalise;
use App\Support\PlatformSettings;

class KycInicializacaoService
{
    public function __construct(
        private KycHistoricoService $historico,
    ) {}

    public function iniciar(Estabelecimento $estabelecimento): KycAnalise
    {
        $kyc = KycAnalise::firstOrCreate(
            ['estabelecimento_id' => $estabelecimento->id],
            ['status' => 'pendente'],
        );

        if (! $kyc->wasRecentlyCreated) {
            return $kyc;
        }

        $this->historico->registrar($kyc, 'kyc_iniciado', 'KYC iniciado para o estabelecimento');

        if (PlatformSettings::kycAtivo()) {
            ConsultarReceitaKycJob::dispatch($kyc);
        }

        return $kyc;
    }
}
