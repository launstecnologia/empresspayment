<?php

namespace Database\Seeders;

use App\Models\Estabelecimento;
use App\Models\EdiMovimento;
use App\Services\EdiProcessadorService;
use App\Services\FaturamentoAgregadorService;
use App\Services\RoyaltyCalculadorService;
use Illuminate\Database\Seeder;

class EdiTesteSeeder extends Seeder
{
    public function run(): void
    {
        $estabelecimento = Estabelecimento::withoutGlobalScopes()
            ->whereNotNull('marketplace_id')
            ->where('ativo', true)
            ->first();

        if (! $estabelecimento) {
            $this->command?->warn('Nenhum estabelecimento ativo com marketplace_id encontrado.');

            return;
        }

        $estabelecimento->load('marketplace');

        $tokenEdi = $estabelecimento->token_pagseguro ?: 'TESTEDI001';

        if (! $estabelecimento->token_pagseguro || ! $estabelecimento->pagbank_edi_ativo) {
            $estabelecimento->forceFill([
                'token_pagseguro' => $tokenEdi,
                'pagbank_edi_ativo' => true,
            ])->save();
        }

        $dataReferencia = now()->format('Y-m-d');
        $registros = $this->registrosEdi($tokenEdi, $dataReferencia);

        $registros = array_values(array_filter(
            $registros,
            fn (array $registro) => ! EdiMovimento::where('movimento_api_codigo', $registro['movimento_api_codigo'])->exists()
        ));

        if ($registros === []) {
            $this->command?->info('Transacoes EDI de teste ja existem no banco.');

            return;
        }

        $importados = app(EdiProcessadorService::class)->processarPagina(
            $estabelecimento->id,
            $dataReferencia,
            'transactional',
            1,
            ['movimentos' => $registros, 'has_next' => false]
        );

        $estabelecimento = $estabelecimento->fresh();
        $royaltyService = app(RoyaltyCalculadorService::class);
        $royaltyService->fixarCadeia($estabelecimento);
        $processados = $royaltyService->calcularPendentes();
        app(FaturamentoAgregadorService::class)->agregar($dataReferencia);

        $this->command?->info(sprintf(
            'EDI teste: %d movimento(s) importado(s) para estabelecimento #%d (%s), marketplace #%d (%s). Royalties calculados: %d.',
            $importados,
            $estabelecimento->id,
            $estabelecimento->nome_fantasia,
            $estabelecimento->marketplace_id,
            $estabelecimento->marketplace?->email ?? '-',
            $processados
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrosEdi(string $codigoEstabelecimento, string $data): array
    {
        $hora = '14:32:10';

        return [
            [
                'movimento_api_codigo' => 'TEST-EDI-2026-000001',
                'estabelecimento' => $codigoEstabelecimento,
                'tipo_registro' => '01',
                'data_inicial_transacao' => $data,
                'hora_inicial_transacao' => $hora,
                'data_venda_ajuste' => $data,
                'hora_venda_ajuste' => $hora,
                'data_prevista_pagamento' => $data,
                'tipo_evento' => '01',
                'tipo_transacao' => 'debito',
                'codigo_transacao' => 'TX-DEB-001',
                'codigo_venda' => 'VND-DEB-001',
                'valor_total_transacao' => 150.00,
                'valor_parcela' => 150.00,
                'valor_original_transacao' => 150.00,
                'valor_liquido_transacao' => 146.85,
                'taxa_intermediacao' => 2.85,
                'tarifa_intermediacao' => 0.39,
                'pagamento_prazo' => '00',
                'plano' => '01',
                'parcela' => '01',
                'quantidade_parcela' => '1',
                'status_pagamento' => '03',
                'meio_pagamento' => '04',
                'arranjo_ur' => 'DEBIT_VISA',
                'instituicao_financeira' => 'VISA',
                'canal_entrada' => 'ME',
                'leitor' => '01',
                'meio_captura' => '01',
                'num_logico' => 'T0012345',
                'nsu' => 'NSU000001',
                'codigo_autorizacao' => 'A12345',
            ],
            [
                'movimento_api_codigo' => 'TEST-EDI-2026-000002',
                'estabelecimento' => $codigoEstabelecimento,
                'tipo_registro' => '01',
                'data_inicial_transacao' => $data,
                'hora_inicial_transacao' => '15:10:22',
                'data_venda_ajuste' => $data,
                'hora_venda_ajuste' => '15:10:22',
                'data_prevista_pagamento' => $data,
                'tipo_evento' => '01',
                'tipo_transacao' => 'credito',
                'codigo_transacao' => 'TX-CRE-001',
                'codigo_venda' => 'VND-CRE-001',
                'valor_total_transacao' => 320.50,
                'valor_parcela' => 320.50,
                'valor_original_transacao' => 320.50,
                'valor_liquido_transacao' => 310.12,
                'taxa_intermediacao' => 8.21,
                'tarifa_intermediacao' => 2.17,
                'pagamento_prazo' => '00',
                'plano' => '01',
                'parcela' => '01',
                'quantidade_parcela' => '1',
                'status_pagamento' => '03',
                'meio_pagamento' => '03',
                'arranjo_ur' => 'CREDIT_VISA',
                'instituicao_financeira' => 'VISA',
                'canal_entrada' => 'ME',
                'leitor' => '01',
                'meio_captura' => '01',
                'num_logico' => 'T0012345',
                'nsu' => 'NSU000002',
                'codigo_autorizacao' => 'B67890',
            ],
            [
                'movimento_api_codigo' => 'TEST-EDI-2026-000003',
                'estabelecimento' => $codigoEstabelecimento,
                'tipo_registro' => '01',
                'data_inicial_transacao' => $data,
                'hora_inicial_transacao' => '16:45:00',
                'data_venda_ajuste' => $data,
                'hora_venda_ajuste' => '16:45:00',
                'data_prevista_pagamento' => now()->addDays(30)->format('Y-m-d'),
                'tipo_evento' => '01',
                'tipo_transacao' => 'credito',
                'codigo_transacao' => 'TX-CRE-002',
                'codigo_venda' => 'VND-CRE-002',
                'valor_total_transacao' => 900.00,
                'valor_parcela' => 300.00,
                'valor_original_transacao' => 900.00,
                'valor_liquido_transacao' => 870.30,
                'taxa_intermediacao' => 22.50,
                'tarifa_intermediacao' => 7.20,
                'pagamento_prazo' => '30',
                'plano' => '01',
                'parcela' => '01',
                'quantidade_parcela' => '3',
                'status_pagamento' => '03',
                'meio_pagamento' => '03',
                'arranjo_ur' => 'CREDIT_MASTERCARD',
                'instituicao_financeira' => 'MASTERCARD',
                'canal_entrada' => 'ME',
                'leitor' => '01',
                'meio_captura' => '01',
                'num_logico' => 'T0012345',
                'nsu' => 'NSU000003',
                'codigo_autorizacao' => 'C11223',
            ],
            [
                'movimento_api_codigo' => 'TEST-EDI-2026-000004',
                'estabelecimento' => $codigoEstabelecimento,
                'tipo_registro' => '01',
                'data_inicial_transacao' => $data,
                'hora_inicial_transacao' => '17:20:15',
                'data_venda_ajuste' => $data,
                'hora_venda_ajuste' => '17:20:15',
                'data_prevista_pagamento' => $data,
                'tipo_evento' => '01',
                'tipo_transacao' => 'pix',
                'codigo_transacao' => 'TX-PIX-001',
                'codigo_venda' => 'VND-PIX-001',
                'valor_total_transacao' => 89.90,
                'valor_parcela' => 89.90,
                'valor_original_transacao' => 89.90,
                'valor_liquido_transacao' => 88.61,
                'taxa_intermediacao' => 1.09,
                'tarifa_intermediacao' => 0.20,
                'pagamento_prazo' => '00',
                'plano' => '01',
                'parcela' => '01',
                'quantidade_parcela' => '1',
                'status_pagamento' => '03',
                'meio_pagamento' => '11',
                'arranjo_ur' => 'PIX',
                'instituicao_financeira' => 'BACEN',
                'canal_entrada' => 'ME',
                'leitor' => '01',
                'meio_captura' => '02',
                'num_logico' => 'T0012345',
                'nsu' => 'NSU000004',
                'tx_id' => 'E12345678901234567890123456789012',
            ],
            [
                'movimento_api_codigo' => 'TEST-EDI-2026-000005',
                'estabelecimento' => $codigoEstabelecimento,
                'tipo_registro' => '01',
                'data_inicial_transacao' => $data,
                'hora_inicial_transacao' => '18:05:40',
                'data_venda_ajuste' => $data,
                'hora_venda_ajuste' => '18:05:40',
                'data_prevista_pagamento' => now()->addDays(30)->format('Y-m-d'),
                'tipo_evento' => '01',
                'tipo_transacao' => 'credito',
                'codigo_transacao' => 'TX-CRE-003',
                'codigo_venda' => 'VND-CRE-003',
                'valor_total_transacao' => 540.00,
                'valor_parcela' => 270.00,
                'valor_original_transacao' => 540.00,
                'valor_liquido_transacao' => 522.18,
                'taxa_intermediacao' => 13.50,
                'tarifa_intermediacao' => 4.32,
                'pagamento_prazo' => '30',
                'plano' => '01',
                'parcela' => '01',
                'quantidade_parcela' => '2',
                'status_pagamento' => '03',
                'meio_pagamento' => '03',
                'arranjo_ur' => 'CREDIT_MASTERCARD',
                'instituicao_financeira' => 'MASTERCARD',
                'canal_entrada' => 'ME',
                'leitor' => '01',
                'meio_captura' => '01',
                'num_logico' => 'T0012345',
                'nsu' => 'NSU000005',
                'codigo_autorizacao' => 'D44556',
            ],
        ];
    }
}
