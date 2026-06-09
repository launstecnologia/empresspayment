<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('estabelecimentos', 'fv_proposta_status')) {
            return;
        }

        DB::table('estabelecimentos')
            ->whereNotNull('fv_concluido_em')
            ->where('fv_status', 'erro_proposta')
            ->orderBy('id')
            ->each(function ($row) {
                $update = [
                    'fv_status' => 'concluido',
                    'fv_erro' => null,
                ];

                if (empty($row->fv_proposta_status)) {
                    $update['fv_proposta_status'] = 'erro';
                }

                if (empty($row->fv_proposta_erro) && ! empty($row->fv_erro)) {
                    $update['fv_proposta_erro'] = $row->fv_erro;
                }

                DB::table('estabelecimentos')->where('id', $row->id)->update($update);
            });
    }

    public function down(): void
    {
        // Reparo de dados — não reversível com segurança
    }
};
