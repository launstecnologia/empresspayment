<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['admin', 'master', 'marketplace', 'revenda']);
            $table->enum('pessoa_tipo', ['juridica', 'fisica'])->default('juridica');
            $table->string('cnpj', 18)->nullable()->index();
            $table->string('razao_social', 200)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->date('data_abertura')->nullable();
            $table->string('cpf', 14)->nullable()->index();
            $table->string('nome_completo', 200)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('nome_fantasia', 200)->nullable();
            $table->string('segmento', 100)->nullable();
            $table->string('rep_nome', 200)->nullable();
            $table->string('rep_cpf', 14)->nullable();
            $table->date('rep_data_nascimento')->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('endereco', 200)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('uf', 2)->nullable();
            $table->string('telefone', 15)->nullable();
            $table->string('celular', 15)->nullable();
            $table->string('email', 150)->index();
            $table->string('password');
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->index('tipo');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('hierarquia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('pai_id')->nullable()->constrained('hierarquia')->nullOnDelete();
            $table->enum('nivel', ['admin', 'master', 'marketplace', 'revenda']);
            $table->timestamps();
            $table->index('usuario_id');
            $table->index('pai_id');
        });

        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('plano_taxas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $table->string('instituicao', 50);
            $table->string('tipo_transacao', 20);
            $table->unsignedTinyInteger('parcelas')->default(1);
            $table->decimal('taxa_percentual', 5, 2);
            $table->timestamps();
            $table->index('plano_id');
            $table->index(['instituicao', 'tipo_transacao', 'parcelas'], 'idx_inst_tipo');
        });

        Schema::create('plano_taxa_royalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_taxa_id')->constrained('plano_taxas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->enum('nivel', ['admin', 'master', 'marketplace', 'revenda']);
            $table->decimal('percentual', 5, 2);
            $table->timestamps();
            $table->unique(['plano_taxa_id', 'usuario_id'], 'unique_taxa_usuario');
            $table->index('plano_taxa_id');
            $table->index('nivel');
        });

        Schema::create('estabelecimentos', function (Blueprint $table) {
            $table->id();
            $table->enum('pessoa_tipo', ['juridica', 'fisica'])->default('juridica');
            $table->string('cnpj', 18)->nullable()->index();
            $table->string('razao_social', 200)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->date('data_abertura')->nullable();
            $table->string('cpf', 14)->nullable()->index();
            $table->string('nome_completo', 200)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('nome_fantasia', 200)->nullable();
            $table->string('segmento', 100)->nullable();
            $table->string('rep_nome', 200)->nullable();
            $table->string('rep_cpf', 14)->nullable();
            $table->date('rep_data_nascimento')->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('endereco', 200)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('uf', 2)->nullable();
            $table->string('telefone', 15)->nullable();
            $table->string('celular', 15)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('token_pagseguro')->nullable()->index();
            $table->foreignId('plano_id')->nullable()->constrained('planos')->nullOnDelete();
            $table->string('subdominio', 100)->nullable()->unique();
            $table->foreignId('cadastrado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->enum('cadastrado_por_nivel', ['admin', 'master', 'marketplace', 'revenda'])->nullable();
            $table->foreignId('master_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('marketplace_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('revenda_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->enum('status', ['habilitado', 'desabilitado', 'em_analise', 'pendente', 'qualidade'])->default('pendente')->index();
            $table->enum('risco', ['confiavel', 'atencao', 'bloqueado'])->default('confiavel');
            $table->text('anotacoes_interno')->nullable();
            $table->text('anotacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index('master_id');
            $table->index('marketplace_id');
            $table->index('revenda_id');
        });

        Schema::create('estabelecimento_royalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_id')->constrained('estabelecimentos')->cascadeOnDelete();
            $table->foreignId('plano_taxa_id')->constrained('plano_taxas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->enum('nivel', ['admin', 'master', 'marketplace', 'revenda']);
            $table->decimal('percentual_recebe', 5, 2);
            $table->decimal('percentual_repassa', 5, 2);
            $table->decimal('percentual_royalty', 5, 2);
            $table->unsignedTinyInteger('ordem');
            $table->timestamps();
            $table->index('estabelecimento_id');
            $table->index('plano_taxa_id');
            $table->index('usuario_id');
            $table->unique(['estabelecimento_id', 'plano_taxa_id', 'usuario_id'], 'unique_estab_taxa_usuario');
        });

        Schema::create('estabelecimento_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_id')->constrained('estabelecimentos')->cascadeOnDelete();
            $table->string('nome_email', 100);
            $table->string('email_completo', 200);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index('estabelecimento_id');
        });

        Schema::create('estabelecimento_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_id')->constrained('estabelecimentos')->cascadeOnDelete();
            $table->string('tipo_documento', 100);
            $table->string('arquivo_path');
            $table->string('arquivo_nome')->nullable();
            $table->string('token_publico', 100)->nullable()->unique();
            $table->timestamp('token_expira_em')->nullable();
            $table->timestamps();
            $table->index('estabelecimento_id');
        });

        Schema::create('edi_movimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_id')->nullable()->constrained('estabelecimentos')->nullOnDelete();
            $table->string('id_cliente', 64)->nullable();
            $table->string('movimento_api_codigo', 64)->unique();
            $table->string('tipo_registro', 4)->nullable();
            $table->string('estabelecimento', 32)->nullable()->index();
            $table->date('data_inicial_transacao')->nullable()->index();
            $table->time('hora_inicial_transacao')->nullable();
            $table->date('data_venda_ajuste')->nullable();
            $table->time('hora_venda_ajuste')->nullable();
            $table->date('data_prevista_pagamento')->nullable();
            $table->string('tipo_evento', 4)->nullable();
            $table->string('tipo_transacao', 20)->nullable()->index();
            $table->string('codigo_transacao', 64)->nullable();
            $table->string('codigo_venda', 64)->nullable();
            $table->decimal('valor_total_transacao', 10, 2)->nullable();
            $table->decimal('valor_parcela', 10, 2)->nullable();
            $table->decimal('valor_original_transacao', 10, 2)->nullable();
            $table->decimal('valor_liquido_transacao', 10, 2)->nullable();
            $table->decimal('taxa_intermediacao', 10, 2)->nullable();
            $table->decimal('tarifa_intermediacao', 10, 2)->nullable();
            $table->string('pagamento_prazo', 4)->nullable();
            $table->string('plano', 10)->nullable();
            $table->string('parcela', 4)->nullable();
            $table->string('quantidade_parcela', 4)->nullable();
            $table->string('status_pagamento', 4)->nullable()->index();
            $table->string('meio_pagamento', 4)->nullable();
            $table->string('instituicao_financeira', 32)->nullable()->index();
            $table->string('canal_entrada', 8)->nullable();
            $table->string('leitor', 8)->nullable();
            $table->string('meio_captura', 8)->nullable();
            $table->string('num_logico', 32)->nullable();
            $table->string('nsu', 64)->nullable();
            $table->string('cartao_bin', 16)->nullable();
            $table->string('cartao_holder', 16)->nullable();
            $table->string('codigo_autorizacao', 32)->nullable();
            $table->string('codigo_cv', 32)->nullable();
            $table->string('numero_serie_leitor', 64)->nullable();
            $table->string('tx_id', 128)->nullable();
            $table->boolean('processado')->default(false)->index();
            $table->dateTime('data_importacao')->useCurrent();
            $table->timestamps();
            $table->index('estabelecimento_id');
        });

        Schema::create('transacao_royalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edi_movimento_id')->constrained('edi_movimentos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->enum('nivel', ['admin', 'master', 'marketplace', 'revenda']);
            $table->decimal('percentual_royalty', 5, 2);
            $table->decimal('valor_royalty', 12, 2);
            $table->timestamps();
            $table->index('edi_movimento_id');
            $table->index('usuario_id');
        });

        Schema::create('aggregated_revenue', function (Blueprint $table) {
            $table->id();
            $table->date('data')->nullable();
            $table->unsignedSmallInteger('ano')->nullable();
            $table->unsignedTinyInteger('mes')->nullable();
            $table->foreignId('estabelecimento_id')->nullable()->constrained('estabelecimentos')->nullOnDelete();
            $table->foreignId('master_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('marketplace_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('revenda_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('instituicao', 32)->nullable();
            $table->string('tipo_transacao', 20)->nullable();
            $table->string('status_pagamento', 4)->nullable();
            $table->decimal('total_valor', 15, 2)->default(0);
            $table->decimal('total_royalty', 15, 2)->default(0);
            $table->unsignedInteger('total_transacoes')->default(0);
            $table->dateTime('atualizado_em')->nullable();
            $table->timestamps();
            $table->index(['estabelecimento_id', 'data'], 'idx_data_estab');
            $table->index(['master_id', 'data'], 'idx_data_master');
            $table->index(['marketplace_id', 'data'], 'idx_data_mkt');
            $table->index(['revenda_id', 'data'], 'idx_data_rev');
            $table->index(['ano', 'mes'], 'idx_ano_mes');
            $table->index(['instituicao', 'ano', 'mes'], 'idx_instituicao');
        });

        Schema::create('perfis_permissao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dono_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index('dono_id');
        });

        Schema::create('sub_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dono_id')->constrained('usuarios')->cascadeOnDelete();
            $table->enum('dono_tipo', ['admin', 'master', 'marketplace', 'revenda']);
            $table->string('nome', 200);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->foreignId('perfil_id')->nullable()->constrained('perfis_permissao')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index('dono_id');
        });

        Schema::create('perfil_modulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_id')->constrained('perfis_permissao')->cascadeOnDelete();
            $table->string('modulo', 100);
            $table->boolean('pode_ver')->default(false);
            $table->boolean('pode_editar')->default(false);
            $table->timestamps();
            $table->unique(['perfil_id', 'modulo'], 'unique_perfil_modulo');
            $table->index('perfil_id');
        });

        Schema::create('sub_usuario_modulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_usuario_id')->constrained('sub_usuarios')->cascadeOnDelete();
            $table->string('modulo', 100);
            $table->boolean('pode_ver')->default(false);
            $table->boolean('pode_editar')->default(false);
            $table->timestamps();
            $table->unique(['sub_usuario_id', 'modulo'], 'unique_subusuario_modulo');
            $table->index('sub_usuario_id');
        });

        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('entidade', 50);
            $table->unsignedBigInteger('entidade_id');
            $table->string('acao', 50);
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('usuario_nome', 200)->nullable();
            $table->string('mensagem')->nullable();
            $table->json('dados_anteriores')->nullable();
            $table->json('dados_novos')->nullable();
            $table->timestamps();
            $table->index(['entidade', 'entidade_id']);
            $table->index('usuario_id');
            $table->index('created_at');
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('logs');
        Schema::dropIfExists('sub_usuario_modulos');
        Schema::dropIfExists('perfil_modulos');
        Schema::dropIfExists('sub_usuarios');
        Schema::dropIfExists('perfis_permissao');
        Schema::dropIfExists('aggregated_revenue');
        Schema::dropIfExists('transacao_royalties');
        Schema::dropIfExists('edi_movimentos');
        Schema::dropIfExists('estabelecimento_documentos');
        Schema::dropIfExists('estabelecimento_emails');
        Schema::dropIfExists('estabelecimento_royalties');
        Schema::dropIfExists('estabelecimentos');
        Schema::dropIfExists('plano_taxa_royalties');
        Schema::dropIfExists('plano_taxas');
        Schema::dropIfExists('planos');
        Schema::dropIfExists('hierarquia');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
    }
};
