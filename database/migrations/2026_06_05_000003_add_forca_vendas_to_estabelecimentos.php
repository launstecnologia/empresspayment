<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->string('fv_job_id', 36)->nullable()->after('pagbank_edi_ativo')
                ->comment('UUID do job de automação Força de Vendas');
            $table->string('fv_status', 30)->nullable()->after('fv_job_id')
                ->comment('pendente|em_andamento|concluido|erro|erro_email');
            $table->string('fv_senha_6', 6)->nullable()->after('fv_status')
                ->comment('Senha numérica de 6 dígitos criada no PagBank');
            $table->text('fv_erro')->nullable()->after('fv_senha_6');
            $table->timestamp('fv_iniciado_em')->nullable()->after('fv_erro');
            $table->timestamp('fv_concluido_em')->nullable()->after('fv_iniciado_em');
        });
    }

    public function down(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->dropColumn([
                'fv_job_id',
                'fv_status',
                'fv_senha_6',
                'fv_erro',
                'fv_iniciado_em',
                'fv_concluido_em',
            ]);
        });
    }
};
