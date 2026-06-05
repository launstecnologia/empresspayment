<?php

namespace App\Services;

use App\Models\Estabelecimento;
use App\Models\PagbankLog;
use App\Models\PlanoTaxa;
use App\Support\PlatformSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Aplica a tabela de taxas do plano na conta PagBank do estabelecimento.
 *
 * Endpoint: PUT /accounts/{account_id}/fees
 * Documentação: https://dev.pagbank.com.br/reference/definir-taxas
 *
 * Formato do payload:
 * {
 *   "fees": [
 *     { "type": "MDR", "payment_method": "CREDIT_CARD", "installment": 1, "percent": 3.99 },
 *     { "type": "MDR", "payment_method": "DEBIT_CARD",  "installment": 1, "percent": 1.99 },
 *     { "type": "MDR", "payment_method": "PIX",         "installment": 1, "percent": 0.99 }
 *   ]
 * }
 */
class PagBankFeesService
{
    private int $ultimaDuracaoMs = 0;

    public function ultimaDuracaoMs(): int
    {
        return $this->ultimaDuracaoMs;
    }

    /**
     * Monta o payload de fees a partir das taxas do plano do estabelecimento.
     *
     * @return array<string, mixed>
     */
    public function montarPayload(Estabelecimento $estab): array
    {
        $taxas = PlanoTaxa::where('plano_id', $estab->plano_id)
            ->where('ativo', true)
            ->get();

        $fees = $taxas
            ->map(fn (PlanoTaxa $taxa) => $this->taxaParaFee($taxa))
            ->filter()
            ->values()
            ->toArray();

        return ['fees' => $fees];
    }

    /**
     * Envia as taxas para o PagBank e registra no log.
     */
    public function aplicar(Estabelecimento $estab): bool
    {
        if (! $estab->pagbank_account_id) {
            Log::warning('PagBankFeesService: estabelecimento sem account_id', ['id' => $estab->id]);
            return false;
        }

        if (! $estab->plano_id) {
            Log::info('PagBankFeesService: estabelecimento sem plano, taxas não aplicadas', ['id' => $estab->id]);
            return false;
        }

        $payload = $this->montarPayload($estab);

        if (empty($payload['fees'])) {
            Log::info('PagBankFeesService: plano sem taxas ativas', [
                'id' => $estab->id,
                'plano_id' => $estab->plano_id,
            ]);
            return false;
        }

        $baseUrl   = PlatformSettings::pagbankApiUrl();
        $token     = (string) (PlatformSettings::pagbankToken() ?? '');
        $clientId  = (string) (PlatformSettings::pagbankClientId() ?? '');
        $clientSecret = (string) (PlatformSettings::pagbankClientSecret() ?? '');
        $accountId = $estab->pagbank_account_id;

        $inicio = microtime(true);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization'   => "Bearer {$token}",
                    'x-client-id'     => $clientId,
                    'x-client-secret' => $clientSecret,
                    'Content-Type'    => 'application/json',
                ])
                ->put("{$baseUrl}/accounts/{$accountId}/fees", $payload);

            $this->ultimaDuracaoMs = (int) round((microtime(true) - $inicio) * 1000);
            $sucesso = $response->successful();

            PagbankLog::create([
                'estabelecimento_id' => $estab->id,
                'tipo'               => 'aplicar_fees',
                'endpoint'           => "/accounts/{$accountId}/fees",
                'metodo'             => 'PUT',
                'request_body'       => $payload,
                'response_status'    => $response->status(),
                'response_body'      => $response->json() ?? [],
                'sucesso'            => $sucesso,
                'duracao_ms'         => $this->ultimaDuracaoMs,
                'erro'               => $sucesso ? null : $response->body(),
            ]);

            if (! $sucesso) {
                Log::warning('PagBankFeesService: erro ao aplicar fees', [
                    'id'     => $estab->id,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return $sucesso;

        } catch (\Throwable $e) {
            $this->ultimaDuracaoMs = (int) round((microtime(true) - $inicio) * 1000);

            PagbankLog::create([
                'estabelecimento_id' => $estab->id,
                'tipo'               => 'aplicar_fees',
                'endpoint'           => "/accounts/{$accountId}/fees",
                'metodo'             => 'PUT',
                'request_body'       => $payload,
                'sucesso'            => false,
                'duracao_ms'         => $this->ultimaDuracaoMs,
                'erro'               => $e->getMessage(),
            ]);

            Log::error('PagBankFeesService: exception', [
                'id'  => $estab->id,
                'msg' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Converte uma PlanoTaxa para o formato de fee do PagBank.
     *
     * @return array<string, mixed>|null
     */
    private function taxaParaFee(PlanoTaxa $taxa): ?array
    {
        $paymentMethod = $this->resolverPaymentMethod($taxa->arranjo_ur, $taxa->tipo_transacao);

        if (! $paymentMethod) {
            return null;
        }

        return [
            'type'           => 'MDR',
            'payment_method' => $paymentMethod,
            'installment'    => (int) ($taxa->parcelas ?? 1),
            'percent'        => (float) $taxa->taxa_percentual,
        ];
    }

    /**
     * Mapeia arranjo_ur / tipo_transacao → payment_method do PagBank.
     */
    private function resolverPaymentMethod(?string $arranjoUr, ?string $tipoTransacao): ?string
    {
        if (filled($arranjoUr)) {
            return match (true) {
                str_starts_with($arranjoUr, 'CREDIT_') => 'CREDIT_CARD',
                str_starts_with($arranjoUr, 'DEBIT_')  => 'DEBIT_CARD',
                $arranjoUr === 'PIX'                   => 'PIX',
                default                                => null,
            };
        }

        return match ($tipoTransacao) {
            'credito' => 'CREDIT_CARD',
            'debito'  => 'DEBIT_CARD',
            'pix'     => 'PIX',
            default   => null,
        };
    }
}
