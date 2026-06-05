<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->string('pagbank_account_id', 100)->nullable()->after('token_pagseguro');
            $table->text('pagbank_access_token')->nullable()->after('pagbank_account_id');
            $table->text('pagbank_refresh_token')->nullable()->after('pagbank_access_token');
            $table->dateTime('pagbank_token_expira')->nullable()->after('pagbank_refresh_token');
            $table->dateTime('pagbank_cadastrado_em')->nullable()->after('pagbank_token_expira');
            $table->boolean('pagbank_edi_ativo')->default(false)->after('pagbank_cadastrado_em');
            $table->string('ip_cadastro', 45)->nullable()->after('pagbank_edi_ativo');

            $table->index('pagbank_account_id', 'idx_pagbank_account');
        });

        Schema::create('pagbank_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_id')->nullable()->constrained('estabelecimentos')->nullOnDelete();
            $table->string('tipo', 50);
            $table->string('endpoint', 200);
            $table->string('metodo', 10);
            $table->json('request_body')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->boolean('sucesso')->default(false);
            $table->text('erro')->nullable();
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('estabelecimento_id', 'idx_pagbank_logs_estab');
            $table->index('tipo', 'idx_pagbank_logs_tipo');
            $table->index('sucesso', 'idx_pagbank_logs_sucesso');
            $table->index('created_at', 'idx_pagbank_logs_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagbank_logs');

        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->dropIndex('idx_pagbank_account');
            $table->dropColumn([
                'pagbank_account_id',
                'pagbank_access_token',
                'pagbank_refresh_token',
                'pagbank_token_expira',
                'pagbank_cadastrado_em',
                'pagbank_edi_ativo',
                'ip_cadastro',
            ]);
        });
    }
};
