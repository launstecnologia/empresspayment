<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('nome', 150);
            $table->string('categoria', 40);
            $table->string('assunto', 200);
            $table->text('corpo');
            $table->string('botao_texto', 80)->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('placeholders_ajuda')->nullable();
            $table->timestamps();

            $table->index('categoria');
            $table->index('ativo');
        });

        Schema::create('email_notificacoes_log', function (Blueprint $table) {
            $table->id();
            $table->string('template_slug', 80)->nullable();
            $table->string('destinatario', 150);
            $table->string('assunto', 200);
            $table->enum('status', ['enviado', 'erro', 'ignorado'])->default('enviado');
            $table->text('erro')->nullable();
            $table->timestamps();

            $table->index('template_slug');
            $table->index('destinatario');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notificacoes_log');
        Schema::dropIfExists('email_templates');
    }
};
