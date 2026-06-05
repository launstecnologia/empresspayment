<?php

namespace Database\Seeders;

use App\Jobs\CadastrarContaPagBankJob;
use App\Models\Estabelecimento;
use App\Models\KycAnalise;
use App\Models\KycDocumento;
use App\Services\KycFinalizacaoService;
use App\Services\PagBankCadastroService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Estabelecimento PJ sandbox com KYC aprovado e cadastro PagBank enfileirado.
 * Reutiliza arquivos de documentos do estabelecimento #3 (se existirem).
 */
class EstabelecimentoSandboxCompletoSeeder extends Seeder
{
    public function run(): void
    {
        $referenciaDocs = KycDocumento::query()
            ->where('estabelecimento_id', 3)
            ->whereIn('tipo', ['cnh_frente', 'cnh_verso', 'comprovante_endereco', 'contrato_social'])
            ->get()
            ->keyBy('tipo');

        $estab = Estabelecimento::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'padaria.teste@sandbox.pagseguro.com.br'],
            [
                'pessoa_tipo' => 'juridica',
                'cnpj' => '11.222.333/0001-81',
                'razao_social' => 'PADARIA TESTE SANDBOX LTDA',
                'nome_fantasia' => 'Padaria Teste Sandbox',
                'segmento' => 'Padaria',
                'data_abertura' => '2018-03-15',
                'rep_nome' => 'Joao da Silva',
                'rep_cpf' => '529.982.247-25',
                'rep_data_nascimento' => '1990-05-15',
                'cep' => '01310-100',
                'endereco' => 'Avenida Paulista',
                'numero' => '1000',
                'complemento' => 'Loja 1',
                'bairro' => 'Bela Vista',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'celular' => '(11) 98111-1111',
                'telefone' => '(11) 3333-4444',
                'ip_cadastro' => '177.0.0.1',
                'status' => 'pendente',
                'risco' => 'confiavel',
                'ativo' => true,
                'cadastrado_por_id' => 2,
                'cadastrado_por_nivel' => 'admin',
                'pagbank_account_id' => null,
                'pagbank_access_token' => null,
                'pagbank_refresh_token' => null,
                'pagbank_token_expira' => null,
                'pagbank_cadastrado_em' => null,
                'subdominio' => 'padaria-teste-'.Str::lower(Str::random(6)),
                'documento_token_publico' => Str::random(40),
            ]
        );

        $kyc = KycAnalise::firstOrCreate(
            ['estabelecimento_id' => $estab->id],
            ['status' => 'pendente']
        );

        $dadosExtraidos = [
            'cnh_frente' => [
                'nome' => 'Joao da Silva',
                'cpf' => '52998224725',
                'data_nascimento' => '1990-05-15',
            ],
            'cnh_verso' => [
                'nome_mae' => 'Maria da Silva',
            ],
            'comprovante_endereco' => [
                'cep' => '01310100',
                'endereco' => 'Avenida Paulista',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
            ],
            'contrato_social' => [
                'razao_social' => 'PADARIA TESTE SANDBOX LTDA',
                'cnpj' => '11222333000181',
            ],
        ];

        foreach (['cnh_frente', 'cnh_verso', 'comprovante_endereco', 'contrato_social'] as $tipo) {
            $ref = $referenciaDocs->get($tipo);
            $caminho = $ref?->caminho ?? "estabelecimentos/{$estab->id}/documentos/placeholder-{$tipo}.pdf";

            KycDocumento::updateOrCreate(
                [
                    'kyc_analise_id' => $kyc->id,
                    'tipo' => $tipo,
                ],
                [
                    'estabelecimento_id' => $estab->id,
                    'nome_original' => $ref?->nome_original ?? "{$tipo}.pdf",
                    'caminho' => $caminho,
                    'mime_type' => $ref?->mime_type ?? 'application/pdf',
                    'tamanho_bytes' => $ref?->tamanho_bytes ?? 1024,
                    'openai_status' => 'aprovado',
                    'openai_dados_extraidos' => $dadosExtraidos[$tipo],
                    'openai_confianca' => 0.95,
                    'cruzamento_status' => 'ok',
                    'cruzamento_divergencias' => null,
                    'admin_override' => null,
                ]
            );
        }

        $kyc->update(['status' => 'em_analise']);

        $adminId = 1;
        app(KycFinalizacaoService::class)->aprovar($kyc->fresh(), $adminId, 'Aprovado via seeder sandbox — dados válidos para PagBank.');

        $estab = $estab->fresh();

        if (! $estab->pagbank_account_id) {
            try {
                (new CadastrarContaPagBankJob($estab))->handle(app(PagBankCadastroService::class));
            } catch (\Throwable $e) {
                $this->command?->warn("Cadastro PagBank pendente na fila: {$e->getMessage()}");
                CadastrarContaPagBankJob::dispatch($estab)->delay(now()->addSeconds(5));
            }
        }

        $estab->refresh();

        $this->command?->info("Estabelecimento #{$estab->id} — KYC: aprovado — PagBank: ".($estab->pagbank_account_id ?: 'enfileirado'));
        $this->command?->info("E-mail: {$estab->email}");
    }
}
