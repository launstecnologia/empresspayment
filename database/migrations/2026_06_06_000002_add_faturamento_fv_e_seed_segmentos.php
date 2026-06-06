<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes para integração completa com a automação Força de Vendas:
 *
 * 1. Adiciona `faturamento_mensal` em estabelecimentos
 * 2. Adiciona `codigo_fv` em planos  (código de promoção no portal PagBank FV)
 * 3. Seed idempotente dos segmentos (nomes exatos do portal PagBank FV)
 */
return new class extends Migration
{
    // Faixas de faturamento exatamente como aparecem no portal PagBank FV
    public const FATURAMENTOS = [
        'De R$ 1 mil até R$ 5 mil',
        'De R$ 5 mil até R$ 10 mil',
        'Acima de R$ 10 mil',
    ];

    // Segmentos exatamente como aparecem no portal PagBank FV
    public const SEGMENTOS = [
        'Outras atividades empresariais',
        'Comércio de vestuário e acessórios',
        'Loja de utensilios domésticos',
        'Conserto de Eletrônicos / Eletrodomésticos',
        'Serviços médicos e terapias',
        'Jardineiro/Florista/Paisagista',
        'Corretor de imobiliária',
        'Tatuadores',
        'Podólogos/Pedicuros',
        'Alfaiates/Costureiras',
        'Comércio de artigos educativos',
        'Artigos Religiosos',
        'Fotógrafo',
        'Sex shop/Produtos eróticos',
        'Personal Trainer',
        'Dentistas',
        'Advogados e Serviços Legais',
        'Veterinarios',
        'Artesanato, Arte e Antiguidades',
        'Arquiteto e Engenheiro',
        'Despachante',
        'Reparos e Materiais de Construção',
        'Corretor de seguro',
        'Restaurantes e similares',
        'Comércio varejista de bebidas',
        'Padarias e confeitarias',
        'Açougues',
        'Mercados, mercearias',
        'Jóias e Relógios',
        'Prestadores de serviços pessoais',
        'Serviços de recreação e lazer',
        'Feira Livre',
        'Esteticista / Massagista',
        'Celulares e Telefonia',
        'Cabelereiro/Barbeiro/Manicure',
        'Outras atividades profissionais',
        'Comércio varejista de alimentos',
        'Automóveis e Acessórios',
        'Serviços de turismo e hospedagem',
        'Táxi e motoristas de aplicativos',
        'Pet Shop',
        'Marceneiros/Serralheiros/Vidraceiros',
        'Venda a Domicilio',
        'Borracheiro/Mecanico/Funilaria/Pintura',
        'Chaveiro',
    ];

    public function up(): void
    {
        // ── 1. Faturamento mensal em estabelecimentos ──────────────────────
        if (! Schema::hasColumn('estabelecimentos', 'faturamento_mensal')) {
            Schema::table('estabelecimentos', function (Blueprint $table) {
                $table->string('faturamento_mensal', 100)->nullable()->after('segmento');
            });
        }

        // ── 2. Código FV (promoção) em planos ─────────────────────────────
        if (! Schema::hasColumn('planos', 'codigo_fv')) {
            Schema::table('planos', function (Blueprint $table) {
                $table->string('codigo_fv', 100)->nullable()->after('nome')
                    ->comment('Código de promoção usado no portal PagBank Força de Vendas');
            });
        }

        // Atualiza o plano EXPRESS73299p2 com o código FV padrão
        DB::table('planos')
            ->where('nome', 'EXPRESS73299p2')
            ->whereNull('codigo_fv')
            ->update(['codigo_fv' => 'nnexpresspay7399d028retorno', 'updated_at' => now()]);

        // ── 3. Seed de segmentos (idempotente) ────────────────────────────
        $now = now();
        foreach (self::SEGMENTOS as $nome) {
            DB::table('segmentos')->updateOrInsert(
                ['nome' => $nome],
                ['ativo' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->dropColumn('faturamento_mensal');
        });

        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('codigo_fv');
        });

        DB::table('segmentos')->whereIn('nome', self::SEGMENTOS)->delete();
    }
};
