<?php

namespace App\Support;

class EdiStatusPagamento
{
    private const CODIGOS_CANCELADOS = ['04', '4'];

    private const TERMOS_CANCELAMENTO = [
        'cancel',
        'estorn',
        'chargeback',
        'refund',
        'devol',
    ];

    public static function cancelado(mixed $status): bool
    {
        $valor = trim((string) $status);

        if ($valor === '') {
            return false;
        }

        if (in_array($valor, self::CODIGOS_CANCELADOS, true)) {
            return true;
        }

        $normalizado = mb_strtolower($valor);

        foreach (self::TERMOS_CANCELAMENTO as $termo) {
            if (str_contains($normalizado, $termo)) {
                return true;
            }
        }

        return false;
    }

    public static function aplicarSomenteFaturaveis(mixed $query, string $coluna = 'status_pagamento'): mixed
    {
        return $query->where(function ($query) use ($coluna) {
            $query->whereNull($coluna)
                ->orWhere(function ($query) use ($coluna) {
                    $query->whereNotIn($coluna, self::CODIGOS_CANCELADOS);

                    foreach (self::TERMOS_CANCELAMENTO as $termo) {
                        $query->whereRaw("LOWER(COALESCE({$coluna}, '')) NOT LIKE ?", ["%{$termo}%"]);
                    }
                });
        });
    }
}
