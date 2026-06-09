<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->string('fv_proposta_status', 30)->nullable()->after('fv_concluido_em');
            $table->text('fv_proposta_erro')->nullable()->after('fv_proposta_status');
            $table->timestamp('fv_proposta_concluido_em')->nullable()->after('fv_proposta_erro');
        });
    }

    public function down(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->dropColumn(['fv_proposta_status', 'fv_proposta_erro', 'fv_proposta_concluido_em']);
        });
    }
};
