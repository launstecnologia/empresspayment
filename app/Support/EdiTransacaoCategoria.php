<?php

namespace App\Support;

class EdiTransacaoCategoria
{
    /**
     * Normaliza tipo_transacao para armazenamento (debito, credito, pix).
     */
    public static function normalizarParaArmazenamento(
        ?string $tipoTransacao,
        ?string $meioPagamento,
        ?string $arranjoUr,
    ): string {
        $categoria = self::resolver($tipoTransacao, $meioPagamento, $arranjoUr, '1');

        return match ($categoria) {
            'debito', 'credito', 'pix' => $categoria,
            default => in_array(strtolower(trim((string) $tipoTransacao)), ['debito', 'credito', 'pix'], true)
                ? strtolower(trim((string) $tipoTransacao))
                : 'outros',
        };
    }

    /**
     * Categoria para dashboard/relatórios: debito, credito, parcelado, pix ou outros.
     */
    public static function resolver(
        ?string $tipoTransacao,
        ?string $meioPagamento,
        ?string $arranjoUr,
        ?string $quantidadeParcela,
    ): string {
        $tipo = strtolower(trim((string) $tipoTransacao));
        $parcelas = self::parcelas($quantidadeParcela);

        if ($tipo === 'pix') {
            return 'pix';
        }

        if ($tipo === 'debito') {
            return 'debito';
        }

        if ($tipo === 'credito') {
            return $parcelas > 1 ? 'parcelado' : 'credito';
        }

        $arranjo = strtoupper(trim((string) $arranjoUr));
        $meio = trim((string) $meioPagamento);

        if ($arranjo === 'PIX' || $meio === '11') {
            return 'pix';
        }

        if (str_starts_with($arranjo, 'DEBIT_') || in_array($meio, ['4', '8'], true)) {
            return 'debito';
        }

        if (str_starts_with($arranjo, 'CREDIT_') || $meio === '3') {
            return $parcelas > 1 ? 'parcelado' : 'credito';
        }

        return 'outros';
    }

    public static function sqlCategoria(string $alias = 'em'): string
    {
        $a = $alias;

        return "
            CASE
                WHEN {$a}.arranjo_ur = 'PIX' OR {$a}.meio_pagamento = '11' THEN 'pix'
                WHEN {$a}.arranjo_ur LIKE 'DEBIT_%' OR {$a}.meio_pagamento IN ('4', '8') THEN 'debito'
                WHEN ({$a}.arranjo_ur LIKE 'CREDIT_%' OR {$a}.meio_pagamento = '3')
                    AND CAST(COALESCE(NULLIF({$a}.quantidade_parcela, ''), '1') AS UNSIGNED) > 1 THEN 'parcelado'
                WHEN {$a}.arranjo_ur LIKE 'CREDIT_%' OR {$a}.meio_pagamento = '3' THEN 'credito'
                WHEN {$a}.tipo_transacao = 'pix' THEN 'pix'
                WHEN {$a}.tipo_transacao = 'debito' THEN 'debito'
                WHEN {$a}.tipo_transacao = 'credito'
                    AND CAST(COALESCE(NULLIF({$a}.quantidade_parcela, ''), '1') AS UNSIGNED) > 1 THEN 'parcelado'
                WHEN {$a}.tipo_transacao = 'credito' THEN 'credito'
                ELSE 'outros'
            END
        ";
    }

    private static function parcelas(?string $quantidadeParcela): int
    {
        $parcelas = (int) preg_replace('/\D/', '', (string) $quantidadeParcela);

        return max($parcelas, 1);
    }
}
