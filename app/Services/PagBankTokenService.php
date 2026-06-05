<?php

namespace App\Services;

use App\Models\Estabelecimento;
use App\Models\PagbankLog;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;

class PagBankTokenService
{
    private int $ultimaDuracaoMs = 0;

    public function ultimaDuracaoMs(): int
    {
        return $this->ultimaDuracaoMs;
    }

    public function renovar(Estabelecimento $estabelecimento): void
    {
        $tokenParceiro = (string) (PlatformSettings::pagbankToken() ?? '');
        $clientId = (string) (PlatformSettings::pagbankClientId() ?? '');
        $clientSecret = (string) (PlatformSettings::pagbankClientSecret() ?? '');
        $baseUrl = PlatformSettings::pagbankApiUrl();

        if ($tokenParceiro === '' || $clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('Credenciais PagBank incompletas (token, client id ou secret).');
        }

        if (blank($estabelecimento->pagbank_refresh_token)) {
            throw new \RuntimeException('Estabelecimento sem refresh_token PagBank.');
        }

        $inicio = microtime(true);

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$tokenParceiro}",
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/oauth2/token", [
                'grant_type' => 'refresh_token',
                'refresh_token' => $estabelecimento->pagbank_refresh_token,
            ]);

        $this->ultimaDuracaoMs = (int) round((microtime(true) - $inicio) * 1000);

        if (! $response->successful()) {
            PagbankLog::create([
                'estabelecimento_id' => $estabelecimento->id,
                'tipo' => 'renovar_token',
                'endpoint' => '/oauth2/token',
                'metodo' => 'POST',
                'request_body' => ['grant_type' => 'refresh_token'],
                'response_status' => $response->status(),
                'response_body' => ['body' => $response->body()],
                'sucesso' => false,
                'erro' => "PagBank API erro {$response->status()}: ".$response->body(),
                'duracao_ms' => $this->ultimaDuracaoMs,
            ]);

            throw new \RuntimeException(
                "PagBank API erro {$response->status()}: ".$response->body()
            );
        }

        $dados = $response->json();

        $estabelecimento->update([
            'pagbank_access_token' => $dados['access_token'],
            'pagbank_refresh_token' => $dados['refresh_token'] ?? $estabelecimento->pagbank_refresh_token,
            'pagbank_token_expira' => now()->addSeconds((int) ($dados['expires_in'] ?? 0)),
        ]);

        PagbankLog::create([
            'estabelecimento_id' => $estabelecimento->id,
            'tipo' => 'renovar_token',
            'endpoint' => '/oauth2/token',
            'metodo' => 'POST',
            'request_body' => ['grant_type' => 'refresh_token'],
            'response_status' => $response->status(),
            'response_body' => [
                'expires_in' => $dados['expires_in'] ?? null,
                'token_type' => $dados['token_type'] ?? null,
            ],
            'sucesso' => true,
            'duracao_ms' => $this->ultimaDuracaoMs,
        ]);
    }
}
