<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estabelecimentos')) {
            DB::statement("ALTER TABLE estabelecimentos MODIFY COLUMN status ENUM(
                'habilitado',
                'desabilitado',
                'em_analise',
                'pendente',
                'qualidade',
                'em_cadastro'
            ) NOT NULL DEFAULT 'pendente'");
        }

        Schema::create('kyc_analises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_id')->unique()->constrained('estabelecimentos')->cascadeOnDelete();
            $table->enum('status', ['pendente', 'em_analise', 'aprovado', 'reprovado', 'revisao_manual'])->default('pendente');
            $table->boolean('receita_consultado')->default(false);
            $table->string('receita_situacao', 50)->nullable();
            $table->string('receita_nome', 200)->nullable();
            $table->date('receita_data_abertura')->nullable();
            $table->json('receita_json')->nullable();
            $table->dateTime('receita_consultado_em')->nullable();
            $table->unsignedTinyInteger('score_risco')->nullable();
            $table->enum('risco_nivel', ['confiavel', 'atencao', 'bloqueado'])->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->enum('admin_decisao', ['aprovado', 'reprovado'])->nullable();
            $table->text('admin_motivo')->nullable();
            $table->dateTime('admin_decidido_em')->nullable();
            $table->unsignedTinyInteger('tentativas_analise')->default(0);
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('kyc_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_analise_id')->constrained('kyc_analises')->cascadeOnDelete();
            $table->foreignId('estabelecimento_id')->constrained('estabelecimentos')->cascadeOnDelete();
            $table->enum('tipo', [
                'rg_frente',
                'rg_verso',
                'cnh_frente',
                'cnh_verso',
                'comprovante_endereco',
                'contrato_social',
                'cartao_cnpj',
                'selfie_documento',
            ]);
            $table->string('nome_original');
            $table->string('caminho', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('tamanho_bytes');
            $table->unsignedBigInteger('enviado_por_id')->nullable();
            $table->enum('enviado_por_tipo', ['usuario', 'sub_usuario'])->nullable();
            $table->enum('openai_status', ['pendente', 'processando', 'aprovado', 'reprovado', 'revisao_manual'])->default('pendente');
            $table->json('openai_dados_extraidos')->nullable();
            $table->string('openai_motivo_reprovacao', 500)->nullable();
            $table->decimal('openai_confianca', 3, 2)->nullable();
            $table->unsignedInteger('openai_tokens_usados')->nullable();
            $table->string('openai_modelo', 50)->nullable();
            $table->dateTime('openai_analisado_em')->nullable();
            $table->enum('cruzamento_status', ['ok', 'divergencia', 'nao_verificado'])->default('nao_verificado');
            $table->json('cruzamento_divergencias')->nullable();
            $table->enum('admin_override', ['aprovado', 'reprovado'])->nullable();
            $table->text('admin_override_motivo')->nullable();
            $table->timestamps();

            $table->index('kyc_analise_id');
            $table->index('estabelecimento_id');
            $table->index('tipo');
            $table->index('openai_status');
        });

        Schema::create('kyc_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_analise_id')->constrained('kyc_analises')->cascadeOnDelete();
            $table->string('evento', 100);
            $table->text('descricao')->nullable();
            $table->json('dados')->nullable();
            $table->unsignedBigInteger('autor_id')->nullable();
            $table->string('autor_nome', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('kyc_analise_id');
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->boolean('kyc_ativo')->default(true)->after('mail_reset_corpo');
            $table->text('openai_api_key')->nullable()->after('kyc_ativo');
            $table->string('openai_modelo', 50)->default('gpt-4o')->after('openai_api_key');
            $table->string('brasilapi_url', 255)->default('https://brasilapi.com.br/api')->after('openai_modelo');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['kyc_ativo', 'openai_api_key', 'openai_modelo', 'brasilapi_url']);
        });

        Schema::dropIfExists('kyc_historico');
        Schema::dropIfExists('kyc_documentos');
        Schema::dropIfExists('kyc_analises');

        if (Schema::hasTable('estabelecimentos')) {
            DB::statement("ALTER TABLE estabelecimentos MODIFY COLUMN status ENUM(
                'habilitado',
                'desabilitado',
                'em_analise',
                'pendente',
                'qualidade'
            ) NOT NULL DEFAULT 'pendente'");
        }
    }
};
