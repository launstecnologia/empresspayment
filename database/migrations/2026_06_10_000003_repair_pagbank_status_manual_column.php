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

        if (Schema::hasColumn('estabelecimentos', 'pagbank_status_manual')) {
            return;
        }

        DB::statement(
            "ALTER TABLE estabelecimentos ADD COLUMN pagbank_status_manual ENUM('pendente', 'aprovado', 'negado') NULL DEFAULT NULL"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('estabelecimentos')) {
            return;
        }

        if (! Schema::hasColumn('estabelecimentos', 'pagbank_status_manual')) {
            return;
        }

        DB::statement('ALTER TABLE estabelecimentos DROP COLUMN pagbank_status_manual');
    }
};
