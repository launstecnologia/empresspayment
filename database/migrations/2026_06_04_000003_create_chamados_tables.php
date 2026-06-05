<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aberto_por_id');
            $table->enum('aberto_por_tipo', ['usuario', 'sub_usuario']);
            $table->enum('aberto_por_nivel', ['master', 'marketplace', 'revenda']);
            $table->foreignId('master_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('marketplace_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('revenda_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('titulo', 200);
            $table->enum('categoria', ['financeiro', 'tecnico', 'comercial', 'cadastro', 'integracao', 'outro']);
            $table->enum('prioridade', ['baixa', 'media', 'alta', 'urgente'])->default('media');
            $table->enum('status', ['aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'fechado'])->default('aberto');
            $table->string('numero', 20)->unique();
            $table->boolean('visualizado_admin')->default(false);
            $table->unsignedTinyInteger('avaliacao')->nullable();
            $table->text('avaliacao_comentario')->nullable();
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('prioridade');
            $table->index('categoria');
            $table->index(['aberto_por_id', 'aberto_por_tipo']);
            $table->index('master_id');
            $table->index('numero');
        });

        Schema::create('chamado_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamado_id')->constrained('chamados')->cascadeOnDelete();
            $table->unsignedBigInteger('autor_id');
            $table->enum('autor_tipo', ['admin', 'usuario', 'sub_usuario']);
            $table->string('autor_nome', 200);
            $table->text('mensagem');
            $table->boolean('interno')->default(false);
            $table->boolean('visualizado')->default(false);
            $table->timestamps();
            $table->index('chamado_id');
            $table->index('created_at');
        });

        Schema::create('chamado_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('chamado_mensagens')->cascadeOnDelete();
            $table->foreignId('chamado_id')->constrained('chamados')->cascadeOnDelete();
            $table->string('nome_original');
            $table->string('nome_arquivo');
            $table->string('caminho', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('tamanho_bytes');
            $table->string('extensao', 10);
            $table->timestamps();
            $table->index('mensagem_id');
            $table->index('chamado_id');
        });

        Schema::create('chamado_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamado_id')->constrained('chamados')->cascadeOnDelete();
            $table->unsignedBigInteger('autor_id');
            $table->string('autor_nome', 200);
            $table->string('acao', 50);
            $table->string('valor_anterior', 100)->nullable();
            $table->string('valor_novo', 100)->nullable();
            $table->timestamps();
            $table->index('chamado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamado_historico');
        Schema::dropIfExists('chamado_anexos');
        Schema::dropIfExists('chamado_mensagens');
        Schema::dropIfExists('chamados');
    }
};
