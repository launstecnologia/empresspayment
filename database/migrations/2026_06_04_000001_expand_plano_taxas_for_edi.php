<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plano_taxas', function (Blueprint $table) {
            $table->unsignedTinyInteger('meio_pagamento_cod')->nullable()->after('tipo_transacao');
            $table->string('arranjo_ur', 50)->nullable()->after('meio_pagamento_cod');
            $table->boolean('ativo')->default(true)->after('taxa_percentual');
        });

        DB::table('plano_taxas')->where('instituicao', 'VISA')->where('tipo_transacao', 'credito')->update(['meio_pagamento_cod' => 3, 'arranjo_ur' => 'CREDIT_VISA']);
        DB::table('plano_taxas')->where('instituicao', 'VISA')->where('tipo_transacao', 'debito')->update(['meio_pagamento_cod' => 4, 'arranjo_ur' => 'DEBIT_VISA']);
        DB::table('plano_taxas')->where('instituicao', 'MASTERCARD')->where('tipo_transacao', 'credito')->update(['meio_pagamento_cod' => 3, 'arranjo_ur' => 'CREDIT_MASTERCARD']);
        DB::table('plano_taxas')->where('instituicao', 'MASTERCARD')->where('tipo_transacao', 'debito')->update(['meio_pagamento_cod' => 4, 'arranjo_ur' => 'DEBIT_MASTERCARD']);
        DB::table('plano_taxas')->where('instituicao', 'ELO')->where('tipo_transacao', 'credito')->update(['meio_pagamento_cod' => 3, 'arranjo_ur' => 'CREDIT_ELO']);
        DB::table('plano_taxas')->where('instituicao', 'ELO')->where('tipo_transacao', 'debito')->update(['meio_pagamento_cod' => 4, 'arranjo_ur' => 'DEBIT_ELO']);
        DB::table('plano_taxas')->where('instituicao', 'AMEX')->where('tipo_transacao', 'credito')->update(['meio_pagamento_cod' => 3, 'arranjo_ur' => 'CREDIT_AMEX']);
        DB::table('plano_taxas')->where('instituicao', 'DINERS')->where('tipo_transacao', 'credito')->update(['meio_pagamento_cod' => 3, 'arranjo_ur' => 'CREDIT_DINERS']);
        DB::table('plano_taxas')->where('instituicao', 'HIPERCARD')->where('tipo_transacao', 'credito')->update(['meio_pagamento_cod' => 3, 'arranjo_ur' => 'CREDIT_HIPERCARD']);
        DB::table('plano_taxas')->where('instituicao', 'HIPERCARD')->where('tipo_transacao', 'debito')->update(['meio_pagamento_cod' => 4, 'arranjo_ur' => 'DEBIT_HIPERCARD']);
        DB::table('plano_taxas')->where('instituicao', 'BANRICOMPRAS')->where('tipo_transacao', 'debito')->update(['meio_pagamento_cod' => 8, 'arranjo_ur' => 'DEBIT_BANRICOMPRAS']);
        DB::table('plano_taxas')->where('instituicao', 'CABAL')->where('tipo_transacao', 'credito')->update(['meio_pagamento_cod' => 3, 'arranjo_ur' => 'CREDIT_CABAL']);
        DB::table('plano_taxas')->where('instituicao', 'CABAL')->where('tipo_transacao', 'debito')->update(['meio_pagamento_cod' => 4, 'arranjo_ur' => 'DEBIT_CABAL']);
        DB::table('plano_taxas')->where('instituicao', 'BACEN')->where('tipo_transacao', 'pix')->update(['meio_pagamento_cod' => 11, 'arranjo_ur' => 'PIX']);

        Schema::table('plano_taxas', function (Blueprint $table) {
            $table->unique(['plano_id', 'arranjo_ur', 'parcelas'], 'unique_plano_arranjo_parcela');
            $table->index(['arranjo_ur', 'parcelas'], 'idx_arranjo_parcela');
        });
    }

    public function down(): void
    {
        Schema::table('plano_taxas', function (Blueprint $table) {
            $table->dropUnique('unique_plano_arranjo_parcela');
            $table->dropIndex('idx_arranjo_parcela');
            $table->dropColumn(['meio_pagamento_cod', 'arranjo_ur', 'ativo']);
        });
    }
};
