<?php

namespace App\Services;

use App\Exceptions\PagBankValidacaoException;
use App\Models\Estabelecimento;
use App\Support\PagBankBusinessCategory;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagBankCadastroService
{
    private int $ultimaDuracaoMs = 0;

    public function ultimaDuracaoMs(): int
    {
        return $this->ultimaDuracaoMs;
    }

    /**
     * @return array<int, string>
     */
    public function validar(Estabelecimento $estab): array
    {
        $erros = [];
        $ehPj = $estab->pessoa_tipo === 'juridica';

        if (blank($estab->email)) {
            $erros[] = 'E-mail é obrigatório.';
        }

        if (blank($estab->celular)) {
            $erros[] = 'Celular é obrigatório.';
        } elseif (! $this->celularValido($estab->celular)) {
            $erros[] = 'Celular deve conter DDD + número (10 ou 11 dígitos).';
        }

        if (blank($estab->ip_cadastro)) {
            $erros[] = 'IP de cadastro não registrado.';
        }

        if (blank($this->resolverNomeMae($estab))) {
            $erros[] = 'Nome da mãe do titular é obrigatório (extraído do KYC ou documento).';
        }

        foreach ([
            'cep' => 'CEP',
            'endereco' => 'Endereço',
            'numero' => 'Número do endereço',
            'bairro' => 'Bairro',
            'cidade' => 'Cidade',
            'uf' => 'UF',
        ] as $campo => $rotulo) {
            if (blank($estab->{$campo})) {
                $erros[] = "{$rotulo} é obrigatório.";
            }
        }

        if ($ehPj) {
            if (blank($estab->rep_nome)) {
                $erros[] = 'Nome do representante é obrigatório (PJ).';
            }
            if (blank($estab->rep_cpf)) {
                $erros[] = 'CPF do representante é obrigatório (PJ).';
            }
            if (blank($estab->rep_data_nascimento)) {
                $erros[] = 'Data de nascimento do representante é obrigatória (PJ).';
            }
            if (blank($estab->razao_social)) {
                $erros[] = 'Razão social é obrigatória (PJ).';
            }
            if (blank($estab->cnpj)) {
                $erros[] = 'CNPJ é obrigatório (PJ).';
            }
            if (blank($estab->segmento)) {
                $erros[] = 'Segmento é obrigatório (PJ).';
            }
        } else {
            if (blank($estab->nome_completo)) {
                $erros[] = 'Nome completo é obrigatório (PF).';
            }
            if (blank($estab->cpf)) {
                $erros[] = 'CPF é obrigatório (PF).';
            }
            if (blank($estab->data_nascimento)) {
                $erros[] = 'Data de nascimento é obrigatória (PF).';
            }
        }

        $kyc = $estab->kycAnalise;
        if (! $kyc || $kyc->status !== 'aprovado') {
            $erros[] = 'KYC deve estar aprovado antes do cadastro PagBank.';
        }

        return $erros;
    }

    public function garantirValido(Estabelecimento $estab): void
    {
        $erros = $this->validar($estab);

        if ($erros !== []) {
            throw new PagBankValidacaoException($erros);
        }
    }

    public function montarPayload(Estabelecimento $estab): array
    {
        $this->garantirValido($estab);

        $ehPj = $estab->pessoa_tipo === 'juridica';
        [$ddd, $numero] = $this->separarTelefone($estab->celular);

        $nomePerson = strtoupper($ehPj ? (string) $estab->rep_nome : (string) $estab->nome_completo);
        $cpf = preg_replace('/\D/', '', $ehPj ? (string) $estab->rep_cpf : (string) $estab->cpf);
        $nascimento = ($ehPj ? $estab->rep_data_nascimento : $estab->data_nascimento)?->format('Y-m-d');
        $nomeMae = strtoupper((string) $this->resolverNomeMae($estab));
        $endereco = $this->montarEndereco($estab);
        $telefone = [
            'country' => '55',
            'area' => $ddd,
            'number' => $numero,
        ];

        $payload = [
            'type' => 'SELLER',
            'email' => $estab->email,
            'business_category' => $this->mapearCategoria($estab->segmento),
            'person' => [
                'name' => $nomePerson,
                'tax_id' => $cpf,
                'birth_date' => $nascimento,
                'mother_name' => $nomeMae,
                'address' => $endereco,
                'phones' => [$telefone],
            ],
            'tos_acceptance' => [
                'user_ip' => $estab->ip_cadastro,
                'date' => $estab->created_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i:sP'),
            ],
        ];

        if ($ehPj) {
            $payload['company'] = [
                'name' => strtoupper((string) $estab->razao_social),
                'tax_id' => preg_replace('/\D/', '', (string) $estab->cnpj),
                'address' => $endereco,
                'phones' => [$telefone],
            ];
        }

        return $payload;
    }

    public function criarConta(array $payload): array
    {
        $baseUrl = PlatformSettings::pagbankApiUrl();
        $token = (string) (PlatformSettings::pagbankToken() ?? '');
        $clientId = (string) (PlatformSettings::pagbankClientId() ?? '');
        $clientSecret = (string) (PlatformSettings::pagbankClientSecret() ?? '');

        if ($token === '' || $clientId === '' || $clientSecret === '') {
            throw new \RuntimeException(
                'Credenciais PagBank incompletas. Configure Token, Client ID e Client Secret em Admin → Configurações → PagBank.'
            );
        }

        $inicio = microtime(true);

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$token}",
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/accounts", $payload);

        $this->ultimaDuracaoMs = (int) round((microtime(true) - $inicio) * 1000);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "PagBank API erro {$response->status()}: ".$response->body()
            );
        }

        return $response->json();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public function separarTelefone(?string $celular): array
    {
        $digitos = preg_replace('/\D/', '', (string) $celular);

        if (! $this->celularValido($celular)) {
            throw new PagBankValidacaoException(['Celular inválido para envio ao PagBank.']);
        }

        $ddd = substr($digitos, 0, 2);
        $numero = substr($digitos, 2);
        $tipo = strlen($numero) === 9 ? 'MOBILE' : 'FIXED';

        return [$ddd, $numero, $tipo];
    }

    public function celularValido(?string $celular): bool
    {
        $digitos = preg_replace('/\D/', '', (string) $celular);

        return strlen($digitos) === 10 || strlen($digitos) === 11;
    }

    public function resolverNomeMae(Estabelecimento $estab): ?string
    {
        $estab->loadMissing('kycAnalise.documentos');

        $documentos = $estab->kycAnalise?->documentos ?? collect();

        foreach ($documentos as $documento) {
            $dados = $documento->openai_dados_extraidos ?? [];
            $nome = $dados['nome_mae'] ?? $dados['mother_name'] ?? null;

            if (filled($nome)) {
                return trim((string) $nome);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function montarEndereco(Estabelecimento $estab): array
    {
        $endereco = [
            'street' => (string) $estab->endereco,
            'number' => (string) $estab->numero,
            'locality' => (string) $estab->bairro,
            'city' => (string) $estab->cidade,
            'region_code' => strtoupper((string) $estab->uf),
            'postal_code' => preg_replace('/\D/', '', (string) $estab->cep),
            'country' => 'BRA',
        ];

        if (filled($estab->complemento)) {
            $endereco['complement'] = (string) $estab->complemento;
        }

        return $endereco;
    }

    public function mapearCategoria(?string $segmento): string
    {
        $categoria = PagBankBusinessCategory::resolver($segmento);

        if ($categoria === PagBankBusinessCategory::FALLBACK && filled($segmento) && ! PagBankBusinessCategory::mapeado($segmento)) {
            Log::warning('Segmento não mapeado para PagBank', [
                'segmento' => $segmento,
                'categoria_fallback' => $categoria,
            ]);
        }

        return $categoria;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitizarPayload(array $payload): array
    {
        if (isset($payload['person']['tax_id'])) {
            $payload['person']['tax_id'] = '***';
        }

        if (isset($payload['company']['tax_id'])) {
            $payload['company']['tax_id'] = '***';
        }

        return $payload;
    }
}
