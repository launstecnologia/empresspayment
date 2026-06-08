<?php

namespace App\Services\Ppid;

use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PpidAuthService
{
    private const CACHE_PATH = 'ppid/token_cache.json';

    public function isConfigured(): bool
    {
        return PlatformSettings::ppidConfigurado();
    }

    public function token(): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'PPID não configurada. Defina PPID_API_URL, PPID_EMAIL e PPID_SENHA nas Configurações → KYC.'
            );
        }

        $cached = $this->readCache();
        if ($cached && ! empty($cached['token']) && ! empty($cached['expiration'])) {
            $exp = strtotime((string) $cached['expiration']);
            if ($exp && $exp > time() + 600) {
                return (string) $cached['token'];
            }
        }

        return $this->renovar();
    }

    public function invalidarCache(): void
    {
        Storage::disk('local')->delete(self::CACHE_PATH);
    }

    private function renovar(): string
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->post($this->apiUrl().'/api/auth/login', [
                'email' => PlatformSettings::ppidEmail(),
                'senha' => PlatformSettings::ppidSenha(),
            ]);

        if (! $response->successful() || blank($response->json('token'))) {
            $msg = $response->json('message')
                ?? $response->json('erro')
                ?? $response->body();

            throw new \RuntimeException('Falha ao autenticar na PPID: '.$msg);
        }

        $this->writeCache([
            'token' => $response->json('token'),
            'expiration' => $response->json('expiration') ?? now()->addHour()->toIso8601String(),
        ]);

        return (string) $response->json('token');
    }

    private function apiUrl(): string
    {
        return rtrim(PlatformSettings::ppidApiUrl(), '/');
    }

    private function readCache(): ?array
    {
        if (! Storage::disk('local')->exists(self::CACHE_PATH)) {
            return null;
        }

        $data = json_decode((string) Storage::disk('local')->get(self::CACHE_PATH), true);

        return is_array($data) ? $data : null;
    }

    private function writeCache(array $data): void
    {
        Storage::disk('local')->put(self::CACHE_PATH, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
