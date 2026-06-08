<?php

namespace App\Jobs;

use App\Models\KycDocumento;
use App\Services\KycCruzamentoService;
use App\Services\KycFinalizacaoService;
use App\Services\PpidKycService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalisarDocumentoKycJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $backoff = 30;

    public function __construct(public KycDocumento $documento) {}

    public function handle(
        PpidKycService $ppidKyc,
        KycCruzamentoService $cruzamento,
        KycFinalizacaoService $finalizacao,
    ): void {
        $documento = $this->documento->fresh(['estabelecimento', 'kycAnalise']);
        $documento->update(['openai_status' => 'processando']);

        try {
            $resultado = $ppidKyc->analisarDocumento($documento);

            $documento->update([
                'openai_status' => $resultado['status'],
                'openai_dados_extraidos' => $resultado['dados'],
                'openai_motivo_reprovacao' => $resultado['motivo'] ?? null,
                'openai_confianca' => $resultado['confianca'],
                'openai_tokens_usados' => $resultado['tokens'],
                'openai_modelo' => $resultado['modelo'],
                'openai_analisado_em' => now(),
                'ppid_consulta_id' => $resultado['ppid_consulta_id'] ?? null,
            ]);

            if ($resultado['status'] === 'aprovado') {
                $cruzamento->cruzar($documento);
            }

            $finalizacao->verificar($documento->kyc_analise_id);
        } catch (\Throwable $e) {
            $motivo = str_contains($e->getMessage(), 'Limite mensal')
                ? 'Limite mensal de consultas PPID atingido — análise manual necessária.'
                : 'Erro na análise PPID: '.$e->getMessage();

            $documento->update([
                'openai_status' => 'revisao_manual',
                'openai_motivo_reprovacao' => $motivo,
            ]);

            $finalizacao->verificar($documento->kyc_analise_id);
        }
    }
}
