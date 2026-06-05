<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->decimal('percentual_retencao_pai', 5, 2)
                ->nullable()
                ->after('ativo')
                ->comment('Percentual que o pai hierárquico retém sobre a comissão bruta deste usuário');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('percentual_retencao_pai');
        });
    }
};
