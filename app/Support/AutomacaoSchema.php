<?php

namespace App\Support;

use App\Models\Estabelecimento;
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

    /**
     * @return array<string, mixed>
     */
    public static function atualizacaoErroProposta(Estabelecimento $estab, string $erro): array
    {
        $update = self::atualizacaoProposta('erro', $erro);

        if (filled($estab->fv_concluido_em)) {
            return $update;
        }

        return array_merge($update, [
            'fv_status' => 'erro_proposta',
            'fv_erro' => $erro,
        ]);
    }
}
