<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estabelecimentos')) {
            return;
        }

        DB::statement("ALTER TABLE estabelecimentos MODIFY COLUMN status ENUM(
            'habilitado',
            'desabilitado',
            'em_analise',
            'pendente',
            'qualidade',
            'em_cadastro',
            'inativo_sistema'
        ) NOT NULL DEFAULT 'pendente'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('estabelecimentos')) {
            return;
        }

        DB::table('estabelecimentos')
            ->where('status', 'inativo_sistema')
            ->update(['status' => 'desabilitado']);

        DB::statement("ALTER TABLE estabelecimentos MODIFY COLUMN status ENUM(
            'habilitado',
            'desabilitado',
            'em_analise',
            'pendente',
            'qualidade',
            'em_cadastro'
        ) NOT NULL DEFAULT 'pendente'");
    }
};
