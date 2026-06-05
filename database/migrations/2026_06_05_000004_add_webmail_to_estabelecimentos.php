<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->string('webmail_email', 191)->nullable()->after('fv_concluido_em')
                ->comment('Email de acesso ao webmail criado na plataforma');
            $table->text('webmail_senha')->nullable()->after('webmail_email')
                ->comment('Senha do webmail (criptografada)');
        });
    }

    public function down(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->dropColumn(['webmail_email', 'webmail_senha']);
        });
    }
};
