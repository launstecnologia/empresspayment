<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estabelecimento_emails', function (Blueprint $table) {
            $table->text('senha_criptografada')->nullable()->after('email_completo');
            $table->string('redirecionamento_para', 200)->nullable()->after('senha_criptografada');
            $table->string('imap_host', 200)->nullable()->after('redirecionamento_para');
            $table->unsignedSmallInteger('imap_porta')->default(993)->after('imap_host');
            $table->boolean('imap_ssl')->default(true)->after('imap_porta');
            $table->string('smtp_host', 200)->nullable()->after('imap_ssl');
            $table->unsignedSmallInteger('smtp_porta')->default(587)->after('smtp_host');
            $table->boolean('smtp_ssl')->default(true)->after('smtp_porta');
            $table->boolean('criado_automaticamente')->default(false)->after('smtp_ssl');
            $table->timestamp('ultimo_sync')->nullable()->after('ativo');
            $table->string('ultimo_erro_sync', 500)->nullable()->after('ultimo_sync');
        });

        Schema::create('email_caixa_entrada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_email_id')->constrained('estabelecimento_emails')->cascadeOnDelete();
            $table->string('uid', 100);
            $table->string('message_id', 500)->nullable();
            $table->string('pasta', 100)->default('INBOX');
            $table->string('de_nome', 200)->nullable();
            $table->string('de_email', 200)->nullable();
            $table->text('para')->nullable();
            $table->text('cc')->nullable();
            $table->string('assunto', 500)->nullable();
            $table->longText('corpo_texto')->nullable();
            $table->longText('corpo_html')->nullable();
            $table->boolean('tem_anexo')->default(false);
            $table->unsignedInteger('tamanho_bytes')->default(0);
            $table->boolean('lido')->default(false);
            $table->boolean('respondido')->default(false);
            $table->boolean('encaminhado')->default(false);
            $table->boolean('favorito')->default(false);
            $table->boolean('spam')->default(false);
            $table->boolean('deletado')->default(false);
            $table->string('thread_id', 200)->nullable();
            $table->dateTime('data_email')->nullable();
            $table->timestamps();

            $table->unique(['estabelecimento_email_id', 'uid', 'pasta'], 'email_uid_pasta_unique');
            $table->index(['estabelecimento_email_id', 'pasta']);
            $table->index('data_email');
        });

        Schema::create('email_anexos_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained('email_caixa_entrada')->cascadeOnDelete();
            $table->string('nome_original', 255);
            $table->string('nome_arquivo', 255);
            $table->string('caminho', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('tamanho_bytes')->default(0);
            $table->timestamps();

            $table->index('email_id');
        });

        Schema::create('email_enviados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estabelecimento_email_id')->constrained('estabelecimento_emails')->cascadeOnDelete();
            $table->text('para');
            $table->text('cc')->nullable();
            $table->text('cco')->nullable();
            $table->string('assunto', 500);
            $table->longText('corpo_html');
            $table->boolean('tem_anexo')->default(false);
            $table->string('status', 20)->default('enviado');
            $table->text('erro')->nullable();
            $table->foreignId('resposta_ao_id')->nullable()->constrained('email_caixa_entrada')->nullOnDelete();
            $table->timestamps();

            $table->index('estabelecimento_email_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_enviados');
        Schema::dropIfExists('email_anexos_cache');
        Schema::dropIfExists('email_caixa_entrada');

        Schema::table('estabelecimento_emails', function (Blueprint $table) {
            $table->dropColumn([
                'senha_criptografada',
                'redirecionamento_para',
                'imap_host',
                'imap_porta',
                'imap_ssl',
                'smtp_host',
                'smtp_porta',
                'smtp_ssl',
                'criado_automaticamente',
                'ultimo_sync',
                'ultimo_erro_sync',
            ]);
        });
    }
};
