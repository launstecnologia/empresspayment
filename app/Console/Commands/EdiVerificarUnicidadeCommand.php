<?php

namespace App\Console\Commands;

use App\Models\EdiMovimento;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EdiVerificarUnicidadeCommand extends Command
{
    protected $signature = 'edi:verificar-unicidade
                            {--de= : Data inicial (Y-m-d)}
                            {--ate= : Data final (Y-m-d)}
                            {--top=10 : Exemplos por tipo de duplicata}';

    protected $description = 'Verifica se os movimentos EDI são transações únicas (chave API e chaves de negócio)';

    public function handle(): int
    {
        $ate = filled($this->option('ate'))
            ? Carbon::parse($this->option('ate'))->startOfDay()
            : now()->subDay()->startOfDay();

        $de = filled($this->option('de'))
            ? Carbon::parse($this->option('de'))->startOfDay()
            : $ate->copy()->subDays(29)->startOfDay();

        $top = max(1, (int) $this->option('top'));
        $inicio = $de->format('Y-m-d');
        $fim = $ate->format('Y-m-d');

        $this->info('Verificação de unicidade — edi_movimentos');
        $this->line("Período: {$inicio} → {$fim}");
        $this->newLine();

        $base = EdiMovimento::withoutGlobalScopes()
            ->whereBetween('data_inicial_transacao', [$inicio, $fim]);

        $total = (clone $base)->count();
        $soma = (float) (clone $base)->sum('valor_total_transacao');

        if ($total === 0) {
            $this->warn('Nenhum movimento EDI no período.');

            return self::SUCCESS;
        }

        $this->secaoChaveApi($inicio, $fim, $total, $soma);
        $this->newLine();
        $this->secaoCodigoTransacao($inicio, $fim, $top);
        $this->newLine();
        $this->secaoNsu($inicio, $fim, $top);
        $this->newLine();
        $this->secaoTxIdPix($inicio, $fim, $top);
        $this->newLine();
        $this->secaoResumo($inicio, $fim);

        return self::SUCCESS;
    }

    private function secaoChaveApi(string $de, string $ate, int $total, float $soma): void
    {
        $this->comment('── Chave técnica: movimento_api_codigo ──');

        $base = EdiMovimento::withoutGlobalScopes()->whereBetween('data_inicial_transacao', [$de, $ate]);

        $unicos = (clone $base)->distinct('movimento_api_codigo')->count('movimento_api_codigo');
        $semCodigo = (clone $base)->where(function ($q) {
            $q->whereNull('movimento_api_codigo')->orWhere('movimento_api_codigo', '');
        })->count();

        $duplicadosQuery = DB::table('edi_movimentos')
            ->whereBetween('data_inicial_transacao', [$de, $ate])
            ->whereNotNull('movimento_api_codigo')
            ->where('movimento_api_codigo', '!=', '')
            ->selectRaw('movimento_api_codigo, COUNT(*) as qtd, SUM(valor_total_transacao) as valor')
            ->groupBy('movimento_api_codigo')
            ->having('qtd', '>', 1);

        $gruposDup = (clone $duplicadosQuery)->count();
        $linhasExtras = (int) DB::query()->fromSub($duplicadosQuery, 'd')->sum(DB::raw('qtd - 1'));

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de linhas', number_format($total, 0, ',', '.')],
                ['movimento_api_codigo distintos', number_format($unicos, 0, ',', '.')],
                ['Sem movimento_api_codigo', number_format($semCodigo, 0, ',', '.')],
                ['Faturamento somado', $this->moeda($soma)],
                ['Grupos com código repetido', number_format($gruposDup, 0, ',', '.')],
                ['Linhas extras (além da 1ª por código)', number_format($linhasExtras, 0, ',', '.')],
            ],
        );

        if ($total === $unicos && $gruposDup === 0) {
            $this->info('✓ Cada linha tem movimento_api_codigo único — reimportação faz upsert, não duplica.');
        } elseif ($gruposDup > 0) {
            $this->error('✗ Códigos API repetidos encontrados (não deveria ocorrer com UNIQUE no banco).');

            $exemplos = (clone $duplicadosQuery)->orderByDesc('qtd')->limit((int) $this->option('top'))->get();
            $this->table(
                ['movimento_api_codigo', 'Qtd', 'Valor'],
                $exemplos->map(fn ($r) => [$r->movimento_api_codigo, $r->qtd, $this->moeda((float) $r->valor)])->all(),
            );
        }
    }

    private function secaoCodigoTransacao(string $de, string $ate, int $top): void
    {
        $this->comment('── Chave de negócio: codigo_transacao ──');
        $this->line('Mesma transação PagBank com movimento_api_codigo diferente (eventos/parcelas legítimos vs duplicata).');

        $sub = DB::table('edi_movimentos')
            ->whereBetween('data_inicial_transacao', [$de, $ate])
            ->whereNotNull('codigo_transacao')
            ->where('codigo_transacao', '!=', '')
            ->selectRaw('
                codigo_transacao,
                COUNT(*) as qtd,
                COUNT(DISTINCT movimento_api_codigo) as api_distintos,
                COUNT(DISTINCT estabelecimento_id) as estabelecimentos,
                SUM(valor_total_transacao) as valor
            ')
            ->groupBy('codigo_transacao')
            ->having('qtd', '>', 1);

        $grupos = (clone $sub)->count();
        $linhasExtras = (int) DB::query()->fromSub($sub, 'd')->sum(DB::raw('qtd - 1'));

        $valorExtras = (float) DB::query()->fromSub(
            DB::table('edi_movimentos as em')
                ->joinSub($sub, 'dup', 'dup.codigo_transacao', '=', 'em.codigo_transacao')
                ->whereBetween('em.data_inicial_transacao', [$de, $ate])
                ->selectRaw('em.codigo_transacao, em.valor_total_transacao,
                    ROW_NUMBER() OVER (PARTITION BY em.codigo_transacao ORDER BY em.id) as rn'),
            'ranked'
        )->where('rn', '>', 1)->sum('valor_total_transacao');

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['codigo_transacao com mais de 1 linha', number_format($grupos, 0, ',', '.')],
                ['Linhas além da 1ª por codigo_transacao', number_format($linhasExtras, 0, ',', '.')],
                ['Valor das linhas extras', $this->moeda($valorExtras)],
            ],
        );

        if ($grupos === 0) {
            $this->info('✓ Nenhum codigo_transacao repetido no período.');

            return;
        }

        $this->warn('Há codigo_transacao repetidos — pode ser parcela/evento distinto ou duplicata lógica.');

        $exemplos = (clone $sub)->orderByDesc('qtd')->limit($top)->get();
        $this->table(
            ['codigo_transacao', 'Linhas', 'API distintos', 'Estab.', 'Valor total'],
            $exemplos->map(fn ($r) => [
                mb_strimwidth((string) $r->codigo_transacao, 0, 36, '…'),
                $r->qtd,
                $r->api_distintos,
                $r->estabelecimentos,
                $this->moeda((float) $r->valor),
            ])->all(),
        );
    }

    private function secaoNsu(string $de, string $ate, int $top): void
    {
        $this->comment('── Assinatura operacional: estabelecimento + data + NSU + valor ──');

        $sub = DB::table('edi_movimentos')
            ->whereBetween('data_inicial_transacao', [$de, $ate])
            ->whereNotNull('estabelecimento_id')
            ->whereNotNull('nsu')
            ->where('nsu', '!=', '')
            ->selectRaw('
                estabelecimento_id,
                DATE(data_inicial_transacao) as dia,
                nsu,
                valor_total_transacao,
                COUNT(*) as qtd,
                COUNT(DISTINCT movimento_api_codigo) as api_distintos,
                SUM(valor_total_transacao) as valor
            ')
            ->groupBy('estabelecimento_id', DB::raw('DATE(data_inicial_transacao)'), 'nsu', 'valor_total_transacao')
            ->having('qtd', '>', 1);

        $grupos = (clone $sub)->count();
        $linhasExtras = (int) DB::query()->fromSub($sub, 'd')->sum(DB::raw('qtd - 1'));

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Grupos NSU+valor repetidos', number_format($grupos, 0, ',', '.')],
                ['Linhas extras', number_format($linhasExtras, 0, ',', '.')],
            ],
        );

        if ($grupos === 0) {
            $this->info('✓ Nenhuma duplicata por NSU+valor no mesmo dia/estabelecimento.');

            return;
        }

        $this->warn('Possível duplicata operacional (mesmo NSU e valor).');

        $exemplos = DB::table('edi_movimentos as em')
            ->joinSub($sub, 'dup', function ($join) {
                $join->on('em.estabelecimento_id', '=', 'dup.estabelecimento_id')
                    ->on(DB::raw('DATE(em.data_inicial_transacao)'), '=', 'dup.dia')
                    ->on('em.nsu', '=', 'dup.nsu')
                    ->on('em.valor_total_transacao', '=', 'dup.valor_total_transacao');
            })
            ->whereBetween('em.data_inicial_transacao', [$de, $ate])
            ->selectRaw('em.estabelecimento_id, dup.dia, em.nsu, em.valor_total_transacao, dup.qtd, dup.api_distintos')
            ->groupBy('em.estabelecimento_id', 'dup.dia', 'em.nsu', 'em.valor_total_transacao', 'dup.qtd', 'dup.api_distintos')
            ->orderByDesc('dup.qtd')
            ->limit($top)
            ->get();

        $this->table(
            ['Estab.', 'Dia', 'NSU', 'Valor', 'Linhas', 'API distintos'],
            $exemplos->map(fn ($r) => [
                $r->estabelecimento_id,
                $r->dia,
                mb_strimwidth((string) $r->nsu, 0, 20, '…'),
                $this->moeda((float) $r->valor_total_transacao),
                $r->qtd,
                $r->api_distintos,
            ])->all(),
        );
    }

    private function secaoTxIdPix(string $de, string $ate, int $top): void
    {
        $this->comment('── PIX: tx_id ──');

        $sub = DB::table('edi_movimentos')
            ->whereBetween('data_inicial_transacao', [$de, $ate])
            ->whereNotNull('tx_id')
            ->where('tx_id', '!=', '')
            ->selectRaw('tx_id, COUNT(*) as qtd, COUNT(DISTINCT movimento_api_codigo) as api_distintos, SUM(valor_total_transacao) as valor')
            ->groupBy('tx_id')
            ->having('qtd', '>', 1);

        $grupos = (clone $sub)->count();

        if ($grupos === 0) {
            $this->info('✓ Nenhum tx_id PIX repetido no período.');

            return;
        }

        $this->warn("{$grupos} tx_id com mais de uma linha.");

        $exemplos = (clone $sub)->orderByDesc('qtd')->limit($top)->get();
        $this->table(
            ['tx_id', 'Linhas', 'API distintos', 'Valor'],
            $exemplos->map(fn ($r) => [
                mb_strimwidth((string) $r->tx_id, 0, 40, '…'),
                $r->qtd,
                $r->api_distintos,
                $this->moeda((float) $r->valor),
            ])->all(),
        );
    }

    private function secaoResumo(string $de, string $ate): void
    {
        $this->comment('── Conclusão ──');

        $edi = (float) EdiMovimento::withoutGlobalScopes()
            ->whereBetween('data_inicial_transacao', [$de, $ate])
            ->sum('valor_total_transacao');

        $agg = (float) DB::table('aggregated_revenue')
            ->whereBetween('data', [$de, $ate])
            ->sum('total_valor');

        $diff = $agg - $edi;

        $this->line('1. movimento_api_codigo é a chave de upsert na importação — garante 1 linha por movimento da API.');
        $this->line('2. codigo_transacao / NSU repetidos com API distintos podem ser eventos legítimos (parcela, ajuste).');
        $this->line('3. Se aggregated_revenue > EDI, o problema tende a ser agregação stale, não duplicata na importação.');

        $this->newLine();
        $this->table(
            ['Comparação período', 'Valor'],
            [
                ['Soma edi_movimentos', $this->moeda($edi)],
                ['Soma aggregated_revenue', $this->moeda($agg)],
                ['Diferença (agg − EDI)', $this->moeda($diff)],
            ],
        );
    }

    private function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
