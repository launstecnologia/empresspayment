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

        if ($this->enumJaSimplificado()) {
            return;
        }

        DB::statement("ALTER TABLE estabelecimentos MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pendente'");

        DB::table('estabelecimentos')
            ->where('status', 'habilitado')
            ->update(['status' => 'aprovado']);

        DB::table('estabelecimentos')
            ->whereIn('status', ['desabilitado', 'inativo_sistema'])
            ->update(['status' => 'negado']);

        DB::table('estabelecimentos')
            ->whereIn('status', ['em_analise', 'qualidade', 'em_cadastro'])
            ->update(['status' => 'pendente']);

        DB::statement("ALTER TABLE estabelecimentos MODIFY COLUMN status ENUM('pendente', 'aprovado', 'negado') NOT NULL DEFAULT 'pendente'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('estabelecimentos')) {
            return;
        }

        if (! $this->enumJaSimplificado()) {
            return;
        }

        DB::statement("ALTER TABLE estabelecimentos MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pendente'");

        DB::table('estabelecimentos')
            ->where('status', 'aprovado')
            ->update(['status' => 'habilitado']);

        DB::table('estabelecimentos')
            ->where('status', 'negado')
            ->update(['status' => 'desabilitado']);

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

    private function enumJaSimplificado(): bool
    {
        $column = DB::selectOne("SHOW COLUMNS FROM estabelecimentos WHERE Field = 'status'");

        return str_contains(strtolower($column->Type ?? ''), 'aprovado');
    }
};
