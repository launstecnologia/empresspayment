<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automacao_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_id')->constrained('estabelecimentos')->cascadeOnDelete();
            $table->string('job_id', 36)->nullable();
            $table->string('nivel', 20)->default('info');
            $table->string('etapa', 150)->nullable();
            $table->text('mensagem');
            $table->json('detalhe')->nullable();
            $table->string('origem', 20)->default('laravel');
            $table->string('origem_ref', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['estabelecimento_id', 'created_at'], 'idx_automacao_logs_estab');
            $table->unique(['origem', 'origem_ref'], 'uniq_automacao_logs_origem_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacao_logs');
    }
};
