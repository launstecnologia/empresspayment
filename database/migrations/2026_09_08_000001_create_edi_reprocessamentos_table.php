<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edi_reprocessamentos', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pendente', 'processando', 'concluido', 'erro'])->default('pendente')->index();
            $table->date('periodo_de');
            $table->date('periodo_ate');
            $table->enum('entrada_tipo', ['id', 'token'])->default('id');
            $table->unsignedInteger('total_itens')->default(0);
            $table->unsignedInteger('processados')->default(0);
            $table->unsignedInteger('total_dias')->default(0);
            $table->unsignedInteger('total_movimentos')->default(0);
            $table->unsignedInteger('total_cancelamentos')->default(0);
            $table->unsignedInteger('total_erros')->default(0);
            $table->foreignId('iniciado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('iniciado_por_nome', 200)->nullable();
            $table->json('entradas')->nullable();
            $table->json('resultado')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_reprocessamentos');
    }
};
