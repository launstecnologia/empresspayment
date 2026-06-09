<?php

namespace App\Jobs;

use App\Models\Estabelecimento;
use App\Services\AutomacaoLogService;
use App\Services\AutomacaoPagBankService;
use App\Support\AutomacaoSchema;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutomacaoAceitarPropostaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $estabelecimentoId) {}

    public function handle(AutomacaoPagBankService $service, AutomacaoLogService $automacaoLog): void
    {
        $estab = Estabelecimento::withoutGlobalScopes()->find($this->estabelecimentoId);

        if (! $estab) {
            Log::warning('AutomacaoAceitarPropostaJob: estabelecimento não encontrado', [
                'id' => $this->estabelecimentoId,
            ]);

            return;
        }

        if (filled(AutomacaoSchema::atualizacaoProposta('em_andamento'))) {
            $estab->update(array_merge(
                AutomacaoSchema::atualizacaoProposta('em_andamento'),
                ['fv_proposta_erro' => null],
            ));
        }

        try {
            $automacaoLog->registrar($estab->id, 'Job de aceite de proposta enfileirado', 'info', null, 'Fila');

            $jobId = $service->iniciarAceitarProposta($estab);
            $estab->update(['fv_job_id' => $jobId]);

            $automacaoLog->registrar($estab->id, "Automação Python iniciada (job {$jobId})", 'info', $jobId, 'Python');

            $intervalo = (int) config('automacao.polling_intervalo_seg', 20);
            $maxTentativas = 20;
            $statusFinal = null;
            $status = [];

            for ($i = 0; $i < $maxTentativas; $i++) {
                sleep($intervalo);

                $status = $service->consultarStatusESincronizarLogs($estab, $jobId);
                $statusFinal = $status['status'] ?? 'desconhecido';

                if (in_array($statusFinal, ['concluido', 'erro_proposta', 'erro'], true)) {
                    break;
                }
            }

            if ($statusFinal === 'concluido') {
                $update = array_merge(
                    AutomacaoSchema::atualizacaoProposta('concluido'),
                    [
                        'fv_status' => $estab->fv_status === 'erro_proposta' ? 'concluido' : $estab->fv_status,
                        'fv_erro' => $estab->fv_status === 'erro_proposta' ? null : $estab->fv_erro,
                    ],
                );

                if ($update !== []) {
                    $estab->update($update);
                }

                $automacaoLog->registrarConclusao($estab->id, 'Proposta comercial aceita com sucesso', $jobId);

                Log::info('AutomacaoAceitarPropostaJob: proposta aceita', [
                    'estabelecimento_id' => $estab->id,
                ]);

                return;
            }

            $erro = $status['erro'] ?? 'Timeout ou erro ao aceitar proposta';

            $update = array_merge(
                AutomacaoSchema::atualizacaoProposta('erro', $erro),
                [
                    'fv_status' => 'erro_proposta',
                    'fv_erro' => $erro,
                ],
            );

            if ($update !== []) {
                $estab->update($update);
            }

            $automacaoLog->registrarErro($estab->id, $erro, $jobId ?? null, 'erro_proposta');

            Log::error('AutomacaoAceitarPropostaJob: falha', [
                'estabelecimento_id' => $estab->id,
                'status' => $statusFinal,
                'erro' => $erro,
            ]);
        } catch (\Throwable $e) {
            $update = array_merge(
                AutomacaoSchema::atualizacaoProposta('erro', $e->getMessage()),
                [
                    'fv_status' => 'erro_proposta',
                    'fv_erro' => $e->getMessage(),
                ],
            );

            if ($update !== []) {
                $estab->update($update);
            }

            $automacaoLog->registrarErro($estab->id, $e->getMessage(), $estab->fv_job_id ?? null, 'excecao');

            Log::error('AutomacaoAceitarPropostaJob: exceção', [
                'estabelecimento_id' => $estab->id,
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
