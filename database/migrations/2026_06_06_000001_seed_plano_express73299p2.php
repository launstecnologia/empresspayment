<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed do PLANO EXPRESS73299p2
 *
 * Recebimento D+1  |  CPF e CNPJ
 *
 * Adiciona também a coluna `comissao_percentual` em plano_taxas,
 * caso ainda não exista.
 *
 * A migration é IDEMPOTENTE: pode ser executada em produção sem risco
 * de duplicar dados — usa updateOrInsert por (plano_id, arranjo_ur, parcelas).
 */
return new class extends Migration
{
    private const NOME_PLANO = 'EXPRESS73299p2';

    public function up(): void
    {
        // ── 1. Adicionar comissao_percentual se não existir ────────────────
        if (! Schema::hasColumn('plano_taxas', 'comissao_percentual')) {
            Schema::table('plano_taxas', function (Blueprint $table) {
                $table->decimal('comissao_percentual', 5, 2)->nullable()->after('taxa_percentual');
            });
        }

        // ── 2. Criar (ou localizar) o plano ───────────────────────────────
        $planoExistente = DB::table('planos')
            ->where('nome', self::NOME_PLANO)
            ->first();

        if ($planoExistente) {
            $planoId = $planoExistente->id;
            DB::table('planos')->where('id', $planoId)->update([
                'descricao' => 'Recebimento D+1 — CPF/CNPJ',
                'ativo'     => true,
                'updated_at' => now(),
            ]);
        } else {
            $planoId = DB::table('planos')->insertGetId([
                'nome'       => self::NOME_PLANO,
                'descricao'  => 'Recebimento D+1 — CPF/CNPJ',
                'ativo'      => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── 3. Montar todas as taxas ──────────────────────────────────────
        //
        // Formato: [arranjo_ur, instituicao, tipo_transacao, meio_pagamento_cod, parcelas, taxa_percentual, comissao_percentual]
        //
        // "VISA, MASTER" = VISA + MASTERCARD (mesma taxa)
        // "ELO/DEMAIS"   = ELO
        //
        $taxas = [];

        // PIX ──────────────────────────────────────────────────────────────
        $taxas[] = ['PIX',            'BACEN',       'pix',    11, 1, 0.79, null];

        // DÉBITO ───────────────────────────────────────────────────────────
        $taxas[] = ['DEBIT_VISA',        'VISA',       'debito', 4, 1, 1.19, 0.20];
        $taxas[] = ['DEBIT_MASTERCARD',  'MASTERCARD', 'debito', 4, 1, 1.19, 0.20];
        $taxas[] = ['DEBIT_ELO',         'ELO',        'debito', 4, 1, 1.49, 0.20];

        // CRÉDITO (1× a 18×) ───────────────────────────────────────────────
        // [parcelas, taxa_vm, taxa_elo, comissao]
        $credito = [
            [1,   3.29,  3.99, 0.30],
            [2,   6.23,  6.53, 0.70],
            [3,   7.02,  7.32, 0.70],
            [4,   7.80,  8.10, 0.70],
            [5,   8.58,  8.88, 0.70],
            [6,   9.35,  9.65, 0.70],
            [7,  10.51, 11.01, 1.30],
            [8,  11.26, 11.76, 1.30],
            [9,  12.01, 12.51, 1.30],
            [10, 12.75, 13.25, 1.30],
            [11, 13.47, 13.97, 1.30],
            [12, 14.20, 14.70, 1.30],
            [13, 15.10, 15.70, 1.30],
            [14, 15.81, 16.41, 1.30],
            [15, 16.51, 17.11, 1.30],
            [16, 17.20, 17.80, 1.30],
            [17, 17.88, 18.48, 1.30],
            [18, 18.55, 19.15, 1.30],
        ];

        foreach ($credito as [$parcelas, $taxaVm, $taxaElo, $comissao]) {
            $taxas[] = ["CREDIT_VISA",        'VISA',       'credito', 3, $parcelas, $taxaVm,  $comissao];
            $taxas[] = ["CREDIT_MASTERCARD",  'MASTERCARD', 'credito', 3, $parcelas, $taxaVm,  $comissao];
            $taxas[] = ["CREDIT_ELO",         'ELO',        'credito', 3, $parcelas, $taxaElo, $comissao];
        }

        // ── 4. Inserir / atualizar com upsert idempotente ─────────────────
        $now = now();
        foreach ($taxas as [$arranjoUr, $instituicao, $tipo, $codMeio, $parcelas, $taxa, $comissao]) {
            DB::table('plano_taxas')->updateOrInsert(
                [
                    'plano_id'   => $planoId,
                    'arranjo_ur' => $arranjoUr,
                    'parcelas'   => $parcelas,
                ],
                [
                    'instituicao'         => $instituicao,
                    'tipo_transacao'      => $tipo,
                    'meio_pagamento_cod'  => $codMeio,
                    'taxa_percentual'     => $taxa,
                    'comissao_percentual' => $comissao,
                    'ativo'               => true,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Remove o plano e suas taxas (cascadeOnDelete já cuida das taxas)
        $plano = DB::table('planos')->where('nome', self::NOME_PLANO)->first();
        if ($plano) {
            DB::table('planos')->where('id', $plano->id)->delete();
        }

        // Remove coluna apenas se existir (pode ter sido adicionada aqui)
        if (Schema::hasColumn('plano_taxas', 'comissao_percentual')) {
            Schema::table('plano_taxas', function (Blueprint $table) {
                $table->dropColumn('comissao_percentual');
            });
        }
    }
};
