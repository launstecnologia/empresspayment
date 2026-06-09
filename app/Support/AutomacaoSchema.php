<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class AutomacaoSchema
{
    public static function temTabelaLogs(): bool
    {
        return Schema::hasTable('automacao_logs');
    }

    public static function temColunasProposta(): bool
    {
        return Schema::hasColumn('estabelecimentos', 'fv_proposta_status');
    }

    /**
     * @return array<string, mixed>
     */
    public static function atualizacaoProposta(string $status, ?string $erro = null): array
    {
        if (! self::temColunasProposta()) {
            return [];
        }

        $dados = ['fv_proposta_status' => $status];

        if ($erro !== null) {
            $dados['fv_proposta_erro'] = $erro;
        }

        if ($status === 'concluido') {
            $dados['fv_proposta_concluido_em'] = now();
        }

        return $dados;
    }
}
