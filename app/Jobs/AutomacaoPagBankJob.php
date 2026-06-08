<?php

namespace App\Jobs;

use App\Models\Estabelecimento;
use App\Services\AutomacaoPagBankService;
use App\Services\EmailPlataformaService;
use App\Services\NotificacaoEmailService;
use App\Support\NotificacaoVars;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutomacaoPagBankJob implements ShouldQueue
{
    use Queueable;

    /**
     * Uma única tentativa — o polling interno já trata retries.
     * Se a API Python estiver indisponível, o job falha e pode ser reprocessado manualmente.
     */
    public int $tries = 1;

    /** 15 minutos: cadastro FV + aguardar email + criar senha */
    public int $timeout = 900;

    public function __construct(
        public readonly int $estabelecimentoId,
        public readonly string $senha6,
    ) {}

    public function handle(AutomacaoPagBankService $service, NotificacaoEmailService $notificacao, EmailPlataformaService $emailService): void
    {
        $estab = Estabelecimento::withoutGlobalScopes()->find($this->estabelecimentoId);

        if (! $estab) {
            Log::warning('AutomacaoPagBankJob: estabelecimento não encontrado', [
                'id' => $this->estabelecimentoId,
            ]);

            return;
        }

        // Evita re-execução se já em andamento (a re-execução manual via controller já valida antes)
        if ($estab->fv_status === 'em_andamento') {
            Log::info('AutomacaoPagBankJob: automação já em andamento', ['id' => $estab->id]);

            return;
        }

        $estab->update([
            'fv_status'     => 'em_andamento',
            'fv_iniciado_em' => now(),
            'fv_erro'        => null,
        ]);

        try {
            // 1. Inicia o job na API Python
            $jobId = $service->iniciarCadastro($estab, $this->senha6);

            $estab->update(['fv_job_id' => $jobId]);

            Log::info('AutomacaoPagBankJob: job Python iniciado', [
                'estabelecimento_id' => $estab->id,
                'job_id'             => $jobId,
            ]);

            // 2. Polling até concluir ou atingir timeout
            $intervalo   = (int) config('automacao.polling_intervalo_seg', 20);
            $maxTentativas = (int) config('automacao.polling_max_tentativas', 30);
            $statusFinal = null;
            $resultado   = null;

            for ($i = 0; $i < $maxTentativas; $i++) {
                sleep($intervalo);

                $status = $service->consultarStatus($jobId);
                $statusFinal = $status['status'] ?? 'desconhecido';
                $resultado   = $status['resultado'] ?? null;

                Log::debug("AutomacaoPagBankJob: poll {$i}/{$maxTentativas}", [
                    'job_id' => $jobId,
                    'status' => $statusFinal,
                ]);

                if (in_array($statusFinal, ['concluido', 'erro', 'erro_email'], true)) {
                    break;
                }
            }

        // 3. Processa resultado
        if ($statusFinal === 'concluido') {
            $estab->update([
                'fv_status'      => 'concluido',
                'fv_senha_6'     => $this->senha6,
                'fv_concluido_em' => now(),
                'fv_erro'        => null,
                // Atualiza o status do estabelecimento se ainda for em_cadastro
                'status'         => $estab->status === 'em_cadastro' ? 'habilitado' : $estab->status,
            ]);

            // Ativa o forwarder agora que a automação já leu o e-mail de validação
            try {
                $emailService->ativarForwarder($estab->fresh());
            } catch (\Throwable $e) {
                Log::warning('AutomacaoPagBankJob: falha ao ativar forwarder', [
                    'estabelecimento_id' => $estab->id,
                    'erro' => $e->getMessage(),
                ]);
            }

            Log::info('AutomacaoPagBankJob: automação concluída com sucesso', [
                    'estabelecimento_id' => $estab->id,
                    'job_id'             => $jobId,
                ]);

                // Notifica o estabelecimento
                if (filled($estab->email)) {
                    $notificacao->enfileirar(
                        'pagbank.fv_concluido',
                        $estab->email,
                        NotificacaoVars::estabelecimento($estab),
                        route('estabelecimentos.show', $estab),
                    );
                }

            } elseif (in_array($statusFinal, ['erro', 'erro_email'], true)) {
                $erro = $status['erro'] ?? 'Erro desconhecido na automação';

                $estab->update([
                    'fv_status' => $statusFinal,
                    'fv_erro'   => $erro,
                ]);

                Log::error('AutomacaoPagBankJob: automação falhou', [
                    'estabelecimento_id' => $estab->id,
                    'job_id'             => $jobId,
                    'status'             => $statusFinal,
                    'erro'               => $erro,
                ]);

                // Notifica admins
                $notificacao->enfileirarParaAdmins(
                    'pagbank.fv_erro_admin',
                    array_merge(NotificacaoVars::estabelecimento($estab), [
                        'motivo' => $erro,
                        'fv_status' => $statusFinal,
                    ]),
                    route('estabelecimentos.show', $estab),
                );

            } else {
                // Timeout de polling — job ainda em andamento após o limite
                $estab->update([
                    'fv_status' => 'timeout',
                    'fv_erro'   => 'Timeout: automação não concluiu dentro do prazo esperado.',
                ]);

                Log::warning('AutomacaoPagBankJob: timeout de polling', [
                    'estabelecimento_id' => $estab->id,
                    'job_id'             => $jobId,
                    'status_atual'       => $statusFinal,
                ]);
            }

        } catch (\Throwable $e) {
            $estab->update([
                'fv_status' => 'erro',
                'fv_erro'   => $e->getMessage(),
            ]);

            Log::error('AutomacaoPagBankJob: exceção inesperada', [
                'estabelecimento_id' => $this->estabelecimentoId,
                'erro'               => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('AutomacaoPagBankJob: job falhou definitivamente', [
            'estabelecimento_id' => $this->estabelecimentoId,
            'erro'               => $exception?->getMessage(),
        ]);

        Estabelecimento::withoutGlobalScopes()
            ->where('id', $this->estabelecimentoId)
            ->update([
                'fv_status' => 'erro',
                'fv_erro'   => $exception?->getMessage() ?? 'Erro desconhecido',
            ]);
    }
}
