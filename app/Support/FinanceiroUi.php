<?php

namespace App\Support;

class FinanceiroUi
{
    /**
     * Valores, faturamento, comissões/repasses e transações ficam ocultos
     * enquanto este flag estiver desligado.
     */
    public static function visivel(): bool
    {
        return (bool) config('app.financeiro_visivel', false);
    }
}
