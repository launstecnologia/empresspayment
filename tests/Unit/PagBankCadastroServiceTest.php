<?php

namespace Tests\Unit;

use App\Models\Estabelecimento;
use App\Models\KycAnalise;
use App\Models\KycDocumento;
use App\Services\PagBankCadastroService;
use App\Support\PagBankBusinessCategory;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PagBankCadastroServiceTest extends TestCase
{
    private PagBankCadastroService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PagBankCadastroService;
    }

    public function test_monta_payload_pessoa_fisica(): void
    {
        $estab = $this->estabelecimentoBase([
            'pessoa_tipo' => 'fisica',
            'nome_completo' => 'Maria Silva',
            'cpf' => '987.654.321-00',
            'data_nascimento' => '1985-03-20',
        ]);

        $payload = $this->service->montarPayload($estab);

        $this->assertSame('SELLER', $payload['type']);
        $this->assertSame('maria@email.com.br', $payload['email']);
        $this->assertSame('OTHER_SERVICES', $payload['business_category']);
        $this->assertSame('MARIA SILVA', $payload['person']['name']);
        $this->assertSame('98765432100', $payload['person']['tax_id']);
        $this->assertSame('1985-03-20', $payload['person']['birth_date']);
        $this->assertSame('01310100', $payload['person']['address']['postal_code']);
        $this->assertSame('21', $payload['person']['phones'][0]['area']);
        $this->assertSame('988887777', $payload['person']['phones'][0]['number']);
        $this->assertSame('177.0.0.1', $payload['tos_acceptance']['user_ip']);
        $this->assertNotEmpty($payload['tos_acceptance']['date']);
        $this->assertArrayNotHasKey('company', $payload);
    }

    public function test_monta_payload_pessoa_juridica(): void
    {
        $estab = $this->estabelecimentoBase([
            'pessoa_tipo' => 'juridica',
            'rep_nome' => 'Joao da Silva',
            'rep_cpf' => '123.456.789-00',
            'rep_data_nascimento' => '1990-05-15',
            'razao_social' => 'Padaria do Joao Ltda',
            'cnpj' => '12.345.678/0001-90',
            'segmento' => 'Padaria',
        ]);

        $payload = $this->service->montarPayload($estab);

        $this->assertSame('JOAO DA SILVA', $payload['person']['name']);
        $this->assertSame('12345678900', $payload['person']['tax_id']);
        $this->assertSame('1990-05-15', $payload['person']['birth_date']);
        $this->assertSame('BAKERY', $payload['business_category']);
        $this->assertSame('PADARIA DO JOAO LTDA', $payload['company']['name']);
        $this->assertSame('12345678000190', $payload['company']['tax_id']);
        $this->assertSame('Bela Vista', $payload['company']['address']['locality']);
        $this->assertSame('11', $payload['company']['phones'][0]['area']);
    }

    public function test_validacao_falha_sem_kyc_aprovado(): void
    {
        $estab = $this->estabelecimentoBase([
            'pessoa_tipo' => 'fisica',
            'nome_completo' => 'Maria Silva',
            'cpf' => '98765432100',
            'data_nascimento' => '1985-03-20',
        ], 'pendente');

        $erros = $this->service->validar($estab);

        $this->assertContains('KYC deve estar aprovado antes do cadastro PagBank.', $erros);
    }

    public function test_mapeamento_segmento_padaria(): void
    {
        $this->assertSame('BAKERY', PagBankBusinessCategory::resolver('Padaria'));
    }

    public function test_mapeamento_segmento_desconhecido_usa_fallback(): void
    {
        $this->assertSame('OTHER_SERVICES', PagBankBusinessCategory::resolver('Segmento Inexistente XYZ'));
    }

    public function test_sanitiza_documentos_no_payload(): void
    {
        $payload = [
            'person' => ['tax_id' => '12345678900'],
            'company' => ['tax_id' => '12345678000190'],
        ];

        $sanitizado = $this->service->sanitizarPayload($payload);

        $this->assertSame('***', $sanitizado['person']['tax_id']);
        $this->assertSame('***', $sanitizado['company']['tax_id']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function estabelecimentoBase(array $overrides = [], string $kycStatus = 'aprovado'): Estabelecimento
    {
        $estab = new Estabelecimento(array_merge([
            'email' => 'maria@email.com.br',
            'celular' => '(21) 98888-7777',
            'ip_cadastro' => '177.0.0.1',
            'cep' => '01310-100',
            'endereco' => 'Av Paulista',
            'numero' => '1000',
            'bairro' => 'Bela Vista',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
        ], $overrides));

        $estab->created_at = Carbon::parse('2025-06-04 14:30:00', 'America/Sao_Paulo');

        $kyc = new KycAnalise(['status' => $kycStatus]);
        $kyc->setRelation('documentos', collect([
            new KycDocumento([
                'tipo' => 'cnh_verso',
                'openai_dados_extraidos' => ['nome_mae' => 'Maria da Silva'],
            ]),
        ]));
        $estab->setRelation('kycAnalise', $kyc);

        return $estab;
    }
}
