<?php

namespace App\Services;

use App\Models\Estabelecimento;
use App\Models\KycAnalise;
use App\Support\DocumentoBrasil;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;

class ReceitaFederalService
{
    public function consultarCnpj(string $cnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $baseUrl = rtrim(PlatformSettings::brasilApiUrl(), '/');

        $response = Http::timeout(10)->get("{$baseUrl}/cnpj/v1/{$cnpj}");

        if (! $response->successful()) {
            throw new \RuntimeException('Falha ao consultar CNPJ na Receita Federal.');
        }

        return $response->json();
    }

    public function consultarCpf(string $cpf): array
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        return [
            'valido' => $this->validarDigitosCpf($cpf),
            'cpf' => $cpf,
        ];
    }

    public function validarDigitosCpf(string $cpf): bool
    {
        return DocumentoBrasil::cpfValido($cpf);
    }

    public function aplicarConsulta(KycAnalise $kyc, Estabelecimento $estabelecimento): void
    {
        $dados = null;
        $situacao = null;
        $nome = null;
        $dataAbertura = null;

        if ($estabelecimento->pessoa_tipo === 'juridica' && $estabelecimento->cnpj) {
            $dados = $this->consultarCnpj($estabelecimento->cnpj);
            $situacao = $dados['descricao_situacao_cadastral'] ?? $dados['situacao_cadastral'] ?? null;
            $nome = $dados['razao_social'] ?? null;
            $dataAbertura = $dados['data_inicio_atividade'] ?? null;
        } elseif ($estabelecimento->cpf) {
            $dados = $this->consultarCpf($estabelecimento->cpf);
            $situacao = ($dados['valido'] ?? false) ? 'VALIDO' : 'INVALIDO';
            $nome = $estabelecimento->nome_completo;
        }

        $divergencias = $dados ? $this->cruzarDadosCadastro($dados, $estabelecimento) : [];

        $kyc->update([
            'receita_consultado' => true,
            'receita_situacao' => $situacao,
            'receita_nome' => $nome,
            'receita_data_abertura' => $dataAbertura,
            'receita_json' => $dados,
            'receita_consultado_em' => now(),
            'risco_nivel' => empty($divergencias) ? 'confiavel' : 'atencao',
            'score_risco' => empty($divergencias) ? 20 : 55,
        ]);
    }

    public function cruzarDadosCadastro(array $dadosReceita, Estabelecimento $estab): array
    {
        $divergencias = [];

        if (isset($dadosReceita['razao_social'])) {
            $receitaNome = strtolower(trim($dadosReceita['razao_social']));
            $cadastroNome = strtolower(trim($estab->razao_social ?? ''));
            similar_text($receitaNome, $cadastroNome, $similaridade);
            if ($similaridade < 80) {
                $divergencias['razao_social'] = [
                    'receita' => $dadosReceita['razao_social'],
                    'cadastro' => $estab->razao_social,
                    'similaridade' => round($similaridade, 1),
                ];
            }
        }

        $situacao = $dadosReceita['descricao_situacao_cadastral'] ?? $dadosReceita['situacao_cadastral'] ?? null;
        if ($situacao && strtoupper((string) $situacao) !== 'ATIVA') {
            $divergencias['situacao'] = [
                'receita' => $situacao,
                'esperado' => 'ATIVA',
            ];
        }

        return $divergencias;
    }
}
