<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EstabelecimentoSchema
{
    private static ?bool $temPagbankStatusManual = null;

    private static ?bool $statusEnumSimplificado = null;

    public static function temPagbankStatusManual(): bool
    {
        if (self::$temPagbankStatusManual !== null) {
            return self::$temPagbankStatusManual;
        }

        if (! Schema::hasTable('estabelecimentos')) {
            return self::$temPagbankStatusManual = false;
        }

        return self::$temPagbankStatusManual = Schema::hasColumn('estabelecimentos', 'pagbank_status_manual');
    }

    public static function statusEnumSimplificado(): bool
    {
        if (self::$statusEnumSimplificado !== null) {
            return self::$statusEnumSimplificado;
        }

        if (! Schema::hasTable('estabelecimentos')) {
            return self::$statusEnumSimplificado = false;
        }

        try {
            $column = DB::selectOne("SHOW COLUMNS FROM estabelecimentos WHERE Field = 'status'");
            self::$statusEnumSimplificado = str_contains(strtolower($column->Type ?? ''), 'aprovado');
        } catch (\Throwable) {
            self::$statusEnumSimplificado = false;
        }

        return self::$statusEnumSimplificado;
    }

    public static function statusParaBanco(string $status): string
    {
        $status = EstabelecimentoEtapaListagem::normalizarStatus($status);

        if (self::statusEnumSimplificado()) {
            return $status;
        }

        return match ($status) {
            EstabelecimentoEtapaListagem::APROVADO => 'habilitado',
            EstabelecimentoEtapaListagem::NEGADO => 'desabilitado',
            default => 'pendente',
        };
    }
}
