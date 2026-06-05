<?php

namespace App\Services;

use App\Models\KycDocumento;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OpenAiKycService
{
    public function analisarDocumento(KycDocumento $documento): array
    {
        $apiKey = PlatformSettings::openaiApiKey();
        if (! $apiKey) {
            throw new \RuntimeException('Token da OpenAI não configurado. Acesse Configurações → KYC.');
        }

        $disk = $documento->usaDiscoPublico() ? 'public' : 'local';
        $conteudo = Storage::disk($disk)->get($documento->caminho);
        $imagemBase64 = base64_encode($conteudo);
        $mimeType = $documento->mime_type;
        $modelo = PlatformSettings::openaiModelo();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $modelo,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->montarPrompt($documento->tipo)],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$imagemBase64}",
                                'detail' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI API error: '.$response->body());
        }

        $conteudoResposta = $response->json('choices.0.message.content');
        $dados = json_decode((string) $conteudoResposta, true);

        if (! is_array($dados)) {
            throw new \RuntimeException('OpenAI retornou resposta inválida.');
        }

        $legivel = (bool) ($dados['documento_legivel'] ?? true);
        $valido = (bool) ($dados['documento_valido'] ?? false);
        $adulteracao = (bool) ($dados['sinais_adulteracao'] ?? false);

        $status = 'aprovado';
        if (! $legivel || $adulteracao) {
            $status = 'revisao_manual';
        } elseif (! $valido) {
            $status = 'reprovado';
        }

        return [
            'status' => $status,
            'dados' => $dados,
            'motivo' => $dados['motivo_reprovacao'] ?? null,
            'confianca' => $dados['confianca'] ?? 0,
            'tokens' => $response->json('usage.total_tokens'),
            'modelo' => $modelo,
        ];
    }

    private function montarPrompt(string $tipo): string
    {
        $base = <<<'TXT'
Você é um sistema especializado em verificação de documentos brasileiros para KYC.
Analise a imagem do documento e retorne APENAS um JSON válido, sem markdown.

CAMPOS OBRIGATÓRIOS NO JSON:
- documento_valido (boolean): o documento é válido e legível?
- documento_legivel (boolean): é possível ler claramente os dados?
- sinais_adulteracao (boolean): há sinais de adulteração ou falsificação?
- confianca (float 0-1): nível de confiança da análise
- motivo_reprovacao (string|null): se reprovado, explique o motivo em português

TXT;

        return match ($tipo) {
            'rg_frente', 'cnh_frente' => $base.'EXTRAIR TAMBÉM: tipo_documento, nome, cpf, data_nascimento, numero_documento, orgao_emissor, data_expiracao, foto_presente.',
            'rg_verso', 'cnh_verso' => $base.'EXTRAIR TAMBÉM: nome_mae, nome_pai, naturalidade, data_expiracao.',
            'comprovante_endereco' => $base.'EXTRAIR TAMBÉM: tipo_comprovante, nome_titular, cpf_cnpj, logradouro, bairro, cidade, uf, cep, data_documento, dentro_prazo.',
            'contrato_social' => $base.'EXTRAIR TAMBÉM: razao_social, cnpj, data_constituicao, socios, objeto_social.',
            'cartao_cnpj' => $base.'EXTRAIR TAMBÉM: razao_social, nome_fantasia, cnpj, situacao_cadastral, data_abertura.',
            default => $base,
        };
    }
}
