<?php

namespace App\Console\Commands;

use App\Models\EdiMovimento;
use App\Support\EdiTransacaoCategoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EdiNormalizarCategoriasCommand extends Command
{
    protected $signature = 'edi:normalizar-categorias
                            {--dry-run : Apenas exibe quantos registros seriam atualizados}';

    protected $description = 'Normaliza tipo_transacao dos movimentos EDI importados (códigos v3 → debito/credito/pix)';

    public function handle(): int
    {
        $categoriaSql = EdiTransacaoCategoria::sqlCategoria('em');

        $total = EdiMovimento::withoutGlobalScopes()->count();

        if ($total === 0) {
            $this->warn('Nenhum movimento EDI no banco.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $amostra = DB::table('edi_movimentos as em')
                ->selectRaw("{$categoriaSql} as categoria, COUNT(*) as qtd")
                ->groupBy(DB::raw($categoriaSql))
                ->get();

            $this->info("Total movimentos: {$total}");
            $this->table(['Categoria', 'Quantidade'], $amostra->map(fn ($r) => [$r->categoria, $r->qtd])->all());

            return self::SUCCESS;
        }

        $atualizados = DB::update("
            UPDATE edi_movimentos em
            SET tipo_transacao = CASE
                WHEN em.arranjo_ur = 'PIX' OR em.meio_pagamento = '11' THEN 'pix'
                WHEN em.arranjo_ur LIKE 'DEBIT_%' OR em.meio_pagamento IN ('4', '8') THEN 'debito'
                WHEN em.arranjo_ur LIKE 'CREDIT_%' OR em.meio_pagamento = '3' THEN 'credito'
                WHEN em.tipo_transacao IN ('debito', 'credito', 'pix') THEN em.tipo_transacao
                ELSE em.tipo_transacao
            END
            WHERE em.tipo_transacao NOT IN ('debito', 'credito', 'pix')
               OR em.tipo_transacao IS NULL
        ");

        $this->info("Movimentos atualizados: {$atualizados} de {$total}.");

        $amostra = DB::table('edi_movimentos')
            ->selectRaw('tipo_transacao, COUNT(*) as qtd')
            ->groupBy('tipo_transacao')
            ->orderByDesc('qtd')
            ->limit(10)
            ->get();

        $this->table(['tipo_transacao', 'qtd'], $amostra->map(fn ($r) => [$r->tipo_transacao, $r->qtd])->all());

        return self::SUCCESS;
    }
}
