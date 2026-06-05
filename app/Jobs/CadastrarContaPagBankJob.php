<?php

namespace App\Jobs;

use App\Exceptions\PagBankValidacaoException;
use App\Models\Estabelecimento;
use App\Models\PagbankLog;
use App\Services\NotificacaoEmailService;
use App\Services\PagBankCadastroService;
use App\Services\PagBankFeesService;
use App\Support\NotificacaoVars;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CadastrarContaPagBankJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $backoff = 60;

    public function __construct(
        public Estabelecimento $estabelecimento
    ) {}

    public function handle(PagBankCadastroService $service, PagBankFeesService $feesService): void
    {
        $estab = Estabelecimento::withoutGlobalScopes()
            ->with('kycAnalise')
            ->find($this->estabelecimento->id);

        if (! $estab) {
            Log::warning('Estabelecimento não encontrado para cadastro PagBank', [
                'id' => $this->estabelecimento->id,
            ]);

            return;
        }

        if ($estab->pagbank_account_id) {
            Log::info('Estabelecimento já cadastrado no PagBank', [
                'id' => $estab->id,
                'account_id' => $estab->pagbank_account_id,
            ]);

            return;
        }

        try {
            $payload = $service->montarPayload($estab);
            $resposta = $service->criarConta($payload);

            $estab->update([
                'pagbank_account_id' => $resposta['id'],
                'pagbank_access_token' => $resposta['token']['access_token'],
                'pagbank_refresh_token' => $resposta['token']['refresh_token'],
                'pagbank_token_expira' => now()->addSeconds((int) ($resposta['token']['expires_in'] ?? 0)),
                'pagbank_cadastrado_em' => now(),
            ]);

            PagbankLog::create([
                'estabelecimento_id' => $estab->id,
                'tipo' => 'cadastro_conta',
                'endpoint' => '/accounts',
                'metodo' => 'POST',
                'request_body' => $service->sanitizarPayload($payload),
                'response_status' => 201,
                'response_body' => ['id' => $resposta['id']],
                'sucesso' => true,
                'duracao_ms' => $service->ultimaDuracaoMs(),
            ]);

            Log::info('Conta PagBank criada com sucesso', [
                'estabelecimento_id' => $estab->id,
                'account_id' => $resposta['id'],
            ]);

            // Aplica tabela de taxas do plano na conta recém-criada
            if ($estab->plano_id) {
                $feesAplicado = $feesService->aplicar($estab);
                Log::info('PagBank fees: ' . ($feesAplicado ? 'aplicadas' : 'não aplicadas ou sem taxas'), [
                    'estabelecimento_id' => $estab->id,
                    'plano_id'           => $estab->plano_id,
                ]);
            }

            if (filled($estab->email)) {
                $vars = array_merge(NotificacaoVars::estabelecimento($estab), [
                    'account_id' => $resposta['id'],
                ]);
                app(NotificacaoEmailService::class)->enfileirar(
                    'pagbank.cadastro_sucesso',
                    $estab->email,
                    $vars,
                    route('estabelecimentos.show', $estab)
                );
            }
        } catch (PagBankValidacaoException $e) {
            PagbankLog::create([
                'estabelecimento_id' => $estab->id,
                'tipo' => 'cadastro_conta',
                'endpoint' => '/accounts',
                'metodo' => 'POST',
                'sucesso' => false,
                'erro' => implode(' ', $e->erros),
            ]);

            $this->fail($e);
        } catch (\Throwable $e) {
            PagbankLog::create([
                'estabelecimento_id' => $estab->id,
                'tipo' => 'cadastro_conta',
                'endpoint' => '/accounts',
                'metodo' => 'POST',
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'duracao_ms' => $service->ultimaDuracaoMs(),
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Cadastro PagBank esgotou tentativas', [
            'estabelecimento_id' => $this->estabelecimento->id,
            'erro' => $exception?->getMessage(),
        ]);

        $estab = Estabelecimento::withoutGlobalScopes()->find($this->estabelecimento->id);

        if (! $estab) {
            return;
        }

        $motivo = $exception?->getMessage() ?? 'Erro desconhecido';
        $vars = array_merge(NotificacaoVars::estabelecimento($estab), ['motivo' => $motivo]);
        $notificacao = app(NotificacaoEmailService::class);

        if (filled($estab->email)) {
            $notificacao->enfileirar('pagbank.cadastro_erro', $estab->email, $vars, route('estabelecimentos.show', $estab));
        }

        $notificacao->enfileirarParaAdmins('pagbank.cadastro_erro_admin', $vars, route('estabelecimentos.show', $estab));
    }
}
