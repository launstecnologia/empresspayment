<?php

namespace App\Services;

use App\Models\KycDocumento;
use App\Services\Ppid\PpidOcrService;

class PpidKycService
{
    public function __construct(
        private PpidOcrService $ocr,
    ) {}

    public function analisarDocumento(KycDocumento $documento): array
    {
        $caminho = PpidOcrService::caminhoAbsoluto($documento->caminho, $documento->usaDiscoPublico());
        $mimeType = PpidOcrService::mimeFromPath($caminho, $documento->mime_type);

        $resposta = $this->ocr->consultarArquivo($caminho, $mimeType);

        return $this->mapearResposta($documento, $resposta);
    }

    /**
     * @param  array<string, mixed>  $resposta
     * @return array{status: string, dados: array, motivo: ?string, confianca: float, tokens: null, modelo: string, ppid_consulta_id: ?string}
     */
    private function mapearResposta(KycDocumento $documento, array $resposta): array
    {
        $sucesso = (bool) ($resposta['sucesso'] ?? false);
        $resultado = is_array($resposta['resultado'] ?? null) ? $resposta['resultado'] : [];
        $fields = is_array($resultado['fields'] ?? null) ? $resultado['fields'] : [];
        $confidence = (int) ($resultado['confidence'] ?? 0);
        $documentType = (string) ($resultado['documentType'] ?? '');

        $dadosNormalizados = $this->normalizarCampos($fields, $documentType, $confidence, $resposta);

        $status = 'aprovado';
        $motivo = null;

        if (! $sucesso || empty($fields)) {
            $status = 'revisao_manual';
            $motivo = 'PPID não conseguiu extrair dados do documento com confiança suficiente.';
        } elseif ($confidence < 50) {
            $status = 'revisao_manual';
            $motivo = "Confiança baixa na leitura OCR ({$confidence}%).";
        }

        if (in_array($documento->tipo, ['rg_verso', 'cnh_verso'], true) && $sucesso) {
            $status = 'aprovado';
            $motivo = null;
        }

        return [
            'status' => $status,
            'dados' => $dadosNormalizados,
            'motivo' => $motivo,
            'confianca' => round($confidence / 100, 2),
            'tokens' => null,
            'modelo' => 'ppid-ocr',
            'ppid_consulta_id' => $resposta['consultaId'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $respostaCompleta
     * @return array<string, mixed>
     */
    private function normalizarCampos(array $fields, string $documentType, int $confidence, array $respostaCompleta): array
    {
        $birthDate = $this->normalizarData((string) ($fields['birthDate'] ?? ''));
        $expirationDate = (string) ($fields['expirationDate'] ?? '');

        return [
            'documento_valido' => ! empty($fields),
            'documento_legivel' => $confidence >= 50,
            'sinais_adulteracao' => false,
            'confianca' => round($confidence / 100, 2),
            'tipo_documento' => $documentType ?: null,
            'nome' => $fields['fullName'] ?? null,
            'cpf' => $fields['cpf'] ?? null,
            'data_nascimento' => $birthDate,
            'data_expiracao' => $expirationDate ?: null,
            'uf' => $fields['state'] ?? null,
            'cidade' => $fields['city'] ?? null,
            'ppid_consulta_id' => $respostaCompleta['consultaId'] ?? null,
            'ppid_saldo_restante' => $respostaCompleta['saldoRestante'] ?? null,
            'ppid_campos' => $fields,
        ];
    }

    private function normalizarData(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            return substr($date, 0, 10);
        }

        $ts = strtotime($date);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
