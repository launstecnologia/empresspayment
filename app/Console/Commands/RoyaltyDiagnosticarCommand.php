<?php

namespace App\Console\Commands;

use App\Models\EdiMovimento;
use App\Models\Estabelecimento;
use App\Models\EstabelecimentoRoyalty;
use App\Models\PlanoTaxa;
use App\Services\RoyaltyCalculadorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RoyaltyDiagnosticarCommand extends Command
{
    protected $signature = 'royalty:diagnosticar
                            {estabelecimento : ID interno ou token_pagseguro}
                            {--data= : Filtra movimentos de uma data (Y-m-d)}';

    protected $description = 'Explica por que a comissão de um estabelecimento está zerada';

    public function handle(RoyaltyCalculadorService $royaltyService): int
    {
        $chave = trim((string) $this->argument('estabelecimento'));

        $estabelecimento = Estabelecimento::withoutGlobalScopes()
            ->with(['plano.taxas', 'master', 'marketplace', 'revenda'])
            ->when(ctype_digit($chave), fn ($q) => $q->where('id', (int) $chave))
            ->when(! ctype_digit($chave), fn ($q) => $q->where('token_pagseguro', $chave))
            ->first();

        if (! $estabelecimento) {
            $this->error("Estabelecimento não encontrado: {$chave}");

            return self::FAILURE;
        }

        $this->info("Estabelecimento #{$estabelecimento->id} — ".($estabelecimento->nome_fantasia ?: $estabelecimento->razao_social ?: $estabelecimento->nome_completo ?: '—'));
        $this->line('Token PagBank: '.($estabelecimento->token_pagseguro ?: '—'));

        // 1. Plano
        $plano = $estabelecimento->plano;
        $taxasAtivas = $plano ? $plano->taxas->where('ativo', true) : collect();
        $this->newLine();
        $this->table(['Item', 'Valor'], [
            ['Plano', $plano ? "#{$plano->id} {$plano->nome}" : '— (SEM PLANO)'],
            ['Taxas ativas no plano', $taxasAtivas->count()],
        ]);

        // 2. Cadeia comercial
        $this->comment('Cadeia comercial (quem recebe comissão)');
        $this->table(['Nível', 'Usuário'], [
            ['Master', $estabelecimento->master?->nomeExibicao() ?? '—'],
            ['Marketplace', $estabelecimento->marketplace?->nomeExibicao() ?? '—'],
            ['Revenda', $estabelecimento->revenda?->nomeExibicao() ?? '—'],
        ]);
        $temCadeia = $estabelecimento->master_id || $estabelecimento->marketplace_id || $estabelecimento->revenda_id;

        // 3. EstabelecimentoRoyalty (config da cadeia)
        $totalEstabRoyalty = EstabelecimentoRoyalty::query()
            ->where('estabelecimento_id', $estabelecimento->id)
            ->count();

        // 4. Movimentos
        $movQuery = EdiMovimento::withoutGlobalScopes()
            ->where('estabelecimento_id', $estabelecimento->id)
            ->when($this->option('data'), fn ($q) => $q->whereDate('data_inicial_transacao', $this->option('data')));

        $totalMov = (clone $movQuery)->count();
        $processados = (clone $movQuery)->where('processado', true)->count();
        $pendentes = (clone $movQuery)->where('processado', false)->count();
        $somaMov = (float) (clone $movQuery)->sum('valor_total_transacao');

        $comRoyalty = (clone $movQuery)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('transacao_royalties')
                ->whereColumn('transacao_royalties.edi_movimento_id', 'edi_movimentos.id'))
            ->count();

        $this->newLine();
        $this->comment('Movimentos EDI'.($this->option('data') ? ' em '.$this->option('data') : ''));
        $this->table(['Métrica', 'Valor'], [
            ['Total de movimentos', $totalMov],
            ['Faturamento', 'R$ '.number_format($somaMov, 2, ',', '.')],
            ['Já processados', $processados],
            ['Pendentes (processado=false)', $pendentes],
            ['Com comissão lançada', $comRoyalty],
            ['EstabelecimentoRoyalty (config cadeia)', $totalEstabRoyalty],
        ]);

        // 5. Amostra: último movimento e match de taxa
        $amostra = (clone $movQuery)->latest('data_inicial_transacao')->first();
        $taxaCasada = null;

        if ($amostra) {
            $taxaCasada = $royaltyService->planoTaxaDoMovimento($amostra);

            $this->newLine();
            $this->comment('Amostra — movimento '.$amostra->movimento_api_codigo);
            $comissaoPct = $taxaCasada?->comissao_percentual;

            $this->table(['Campo', 'Valor'], [
                ['Instituição', $amostra->instituicao_financeira ?: '—'],
                ['Tipo transação', $amostra->tipo_transacao ?: '—'],
                ['Arranjo UR', $amostra->arranjo_ur ?: '—'],
                ['Parcelas', (int) ($amostra->quantidade_parcela ?: 1)],
                ['Plano taxa casada', $taxaCasada ? "#{$taxaCasada->id}" : '— NENHUMA'],
                ['taxa_percentual (cobrada do EC)', $taxaCasada ? "{$taxaCasada->taxa_percentual}%" : '—'],
                ['comissao_percentual (admin)', $comissaoPct !== null ? "{$comissaoPct}%" : '— NULO'],
                ['Processado', $amostra->processado ? 'sim' : 'não'],
            ]);
        }

        // Comissão admin calculada (comissao_percentual × faturamento) no período
        $comissaoAdmin = (float) DB::table('edi_movimentos as em')
            ->join('estabelecimentos as e', 'e.id', '=', 'em.estabelecimento_id')
            ->join('plano_taxas as pt', function ($join) {
                $join->on('pt.plano_id', '=', 'e.plano_id')
                    ->on('pt.arranjo_ur', '=', 'em.arranjo_ur')
                    ->on('pt.parcelas', '=', DB::raw('COALESCE(em.quantidade_parcela, 1)'))
                    ->where('pt.ativo', true);
            })
            ->where('em.estabelecimento_id', $estabelecimento->id)
            ->when($this->option('data'), fn ($q) => $q->whereDate('em.data_inicial_transacao', $this->option('data')))
            ->whereNotNull('pt.comissao_percentual')
            ->sum(DB::raw('em.valor_total_transacao * pt.comissao_percentual / 100'));

        $this->newLine();
        $this->line('Comissão admin calculada no período: R$ '.number_format($comissaoAdmin, 2, ',', '.'));

        if ($plano && $taxasAtivas->isNotEmpty()) {
            $taxasSemComissao = $taxasAtivas->whereNull('comissao_percentual')->count();
            if ($taxasSemComissao > 0) {
                $this->warn("{$taxasSemComissao} de {$taxasAtivas->count()} taxas ativas do plano estão SEM comissao_percentual.");
            }
        }

        // 6. Diagnóstico
        $this->newLine();
        $this->comment('Diagnóstico');
        $causas = [];

        if (! $plano) {
            $causas[] = 'Estabelecimento SEM PLANO — vincule com legacy:backfill-planos.';
        } elseif ($taxasAtivas->isEmpty()) {
            $causas[] = "Plano #{$plano->id} não tem taxas ativas cadastradas.";
        } elseif ($amostra && ! $taxaCasada) {
            $causas[] = 'Nenhuma plano_taxa casa com o movimento (arranjo_ur/instituição/tipo/parcelas). Cadastre a taxa correspondente.';
        } elseif ($amostra && $taxaCasada && $taxaCasada->comissao_percentual === null) {
            $causas[] = "A taxa #{$taxaCasada->id} do plano está SEM comissao_percentual — por isso a COMISSÃO DO ADMIN fica R$ 0,00. Popule comissao_percentual nas plano_taxas do plano #{$plano->id}.";
        }

        if (! $temCadeia) {
            $causas[] = 'Estabelecimento SEM cadeia comercial (master/marketplace/revenda) — não há a quem pagar comissão. É o caso mais comum de comissão R$ 0,00.';
        } elseif ($totalEstabRoyalty === 0) {
            $causas[] = 'Cadeia existe, mas EstabelecimentoRoyalty está vazio — rode fixarCadeia (legacy:backfill-planos --force).';
        }

        if ($plano && $temCadeia && $totalEstabRoyalty > 0 && $processados > 0 && $comRoyalty === 0) {
            $causas[] = 'Movimentos foram processados ANTES do vínculo do plano/cadeia — estão como processado=true e não recalculam. Resete processado e rode royalties novamente.';
        }

        if ($causas === []) {
            $this->info('Sem causa óbvia — configuração parece correta. Verifique se CalcularRoyaltiesJob rodou para o período.');
        } else {
            foreach ($causas as $i => $causa) {
                $this->line('  '.($i + 1).'. '.$causa);
            }
        }

        return self::SUCCESS;
    }
}
