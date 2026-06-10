<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estabelecimentos')) {
            return;
        }

        Schema::table('estabelecimentos', function (Blueprint $table) {
            if (! Schema::hasColumn('estabelecimentos', 'pagbank_status_manual')) {
                $table->enum('pagbank_status_manual', ['pendente', 'aprovado', 'negado'])
                    ->nullable()
                    ->after('pagbank_edi_ativo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('estabelecimentos')) {
            return;
        }

        Schema::table('estabelecimentos', function (Blueprint $table) {
            if (Schema::hasColumn('estabelecimentos', 'pagbank_status_manual')) {
                $table->dropColumn('pagbank_status_manual');
            }
        });
    }
};
