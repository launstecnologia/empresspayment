<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edi_movimentos', function (Blueprint $table) {
            $table->index(['data_inicial_transacao', 'status_pagamento'], 'edi_movimentos_data_status_idx');
            $table->index(['estabelecimento_id', 'data_inicial_transacao', 'status_pagamento'], 'edi_movimentos_estab_data_status_idx');
            $table->index(['tipo_transacao', 'data_inicial_transacao'], 'edi_movimentos_tipo_data_idx');
            $table->index('tx_id', 'edi_movimentos_tx_id_idx');
            $table->index('codigo_transacao', 'edi_movimentos_codigo_transacao_idx');
            $table->index('nsu', 'edi_movimentos_nsu_idx');
        });
    }

    public function down(): void
    {
        Schema::table('edi_movimentos', function (Blueprint $table) {
            $table->dropIndex('edi_movimentos_data_status_idx');
            $table->dropIndex('edi_movimentos_estab_data_status_idx');
            $table->dropIndex('edi_movimentos_tipo_data_idx');
            $table->dropIndex('edi_movimentos_tx_id_idx');
            $table->dropIndex('edi_movimentos_codigo_transacao_idx');
            $table->dropIndex('edi_movimentos_nsu_idx');
        });
    }
};
