<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class EstabelecimentoSchema
{
    private static ?bool $temPagbankStatusManual = null;

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
}
