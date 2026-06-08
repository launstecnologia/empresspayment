<?php

namespace App\Services\Ppid;

use App\Models\PpidUsoMensal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PpidOcrService
{
    public function __construct(
        private PpidAuthService $auth,
    ) {}

    public function verificarLimite(): bool
    {
        return ! PpidUsoMensal::limiteAtingido();
    }

    /**
     * @return array Resposta JSON da PPID
     */
    public function consultarArquivo(string $caminhoCompleto, string $mimeType): array
    {
        if (! $this->verificarLimite()) {
            throw new \RuntimeException('Limite mensal de consultas PPID atingido.');
        }

        if (! is_readable($caminhoCompleto)) {
            throw new \RuntimeException('Arquivo do documento não encontrado ou ilegível.');
        }

        $conteudo = file_get_contents($caminhoCompleto);
        if ($conteudo === false) {
            throw new \RuntimeException('Não foi possível ler o arquivo do documento.');
        }

        return $this->consultarBase64(base64_encode($conteudo), $mimeType);
    }

    /**
     * @return array Resposta JSON da PPID
     */
    public function consultarBase64(string $base64, string $mimeType): array
    {
        if (! $this->verificarLimite()) {
            throw new \RuntimeException('Limite mensal de consultas PPID atingido.');
        }

        $apiUrl = rtrim(\App\Support\PlatformSettings::ppidApiUrl(), '/');
        $token = $this->auth->token();

        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken($token)
            ->post("{$apiUrl}/api/ocr/consultar", [
                'imagemBase64' => $base64,
                'mimeType' => $mimeType,
            ]);

        if ($response->status() === 401) {
            $this->auth->invalidarCache();
            $token = $this->auth->token();

            $response = Http::timeout(60)
                ->acceptJson()
                ->withToken($token)
                ->post("{$apiUrl}/api/ocr/consultar", [
                    'imagemBase64' => $base64,
                    'mimeType' => $mimeType,
                ]);
        }

        if ($response->status() === 402) {
            throw new \RuntimeException('Saldo insuficiente na conta PPID.');
        }

        if (! $response->successful()) {
            $msg = $response->json('message')
                ?? $response->json('erro')
                ?? $response->body();

            throw new \RuntimeException('Falha na API PPID OCR: '.$msg);
        }

        PpidUsoMensal::incrementarUso();

        return $response->json();
    }

    public static function mimeFromPath(string $path, ?string $fallback = null): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'webp' => 'image/webp',
        ];

        if (isset($map[$ext])) {
            return $map[$ext];
        }

        if ($fallback && isset($map[strtolower(pathinfo($fallback, PATHINFO_EXTENSION))])) {
            return $map[strtolower(pathinfo($fallback, PATHINFO_EXTENSION))];
        }

        return $fallback ?: 'application/octet-stream';
    }

    public static function caminhoAbsoluto(string $caminho, bool $usaDiscoPublico): string
    {
        $disk = $usaDiscoPublico ? 'public' : 'local';

        return Storage::disk($disk)->path($caminho);
    }
}
