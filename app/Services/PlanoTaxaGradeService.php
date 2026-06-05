<?php

namespace App\Services;

use App\Models\Plano;
use App\Models\PlanoTaxa;
use App\Models\PlanoTaxaRoyalty;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;

class PlanoTaxaGradeService
{
    public const MAPA = [
        'VISA' => [
            'credito' => ['meio' => 3, 'arranjo' => 'CREDIT_VISA'],
            'debito' => ['meio' => 4, 'arranjo' => 'DEBIT_VISA'],
        ],
        'MASTERCARD' => [
            'credito' => ['meio' => 3, 'arranjo' => 'CREDIT_MASTERCARD'],
            'debito' => ['meio' => 4, 'arranjo' => 'DEBIT_MASTERCARD'],
        ],
        'ELO' => [
            'credito' => ['meio' => 3, 'arranjo' => 'CREDIT_ELO'],
            'debito' => ['meio' => 4, 'arranjo' => 'DEBIT_ELO'],
        ],
        'AMEX' => [
            'credito' => ['meio' => 3, 'arranjo' => 'CREDIT_AMEX'],
        ],
        'DINERS' => [
            'credito' => ['meio' => 3, 'arranjo' => 'CREDIT_DINERS'],
        ],
        'HIPERCARD' => [
            'credito' => ['meio' => 3, 'arranjo' => 'CREDIT_HIPERCARD'],
            'debito' => ['meio' => 4, 'arranjo' => 'DEBIT_HIPERCARD'],
        ],
        'BANRICOMPRAS' => [
            'debito' => ['meio' => 8, 'arranjo' => 'DEBIT_BANRICOMPRAS'],
        ],
        'CABAL' => [
            'credito' => ['meio' => 3, 'arranjo' => 'CREDIT_CABAL'],
            'debito' => ['meio' => 4, 'arranjo' => 'DEBIT_CABAL'],
        ],
        'BACEN' => [
            'pix' => ['meio' => 11, 'arranjo' => 'PIX'],
        ],
    ];

    public const DEBITO_GRUPOS = [
        'visa' => ['label' => 'VISA', 'instituicoes' => ['VISA']],
        'master' => ['label' => 'MASTER', 'instituicoes' => ['MASTERCARD']],
        'elo' => ['label' => 'ELO', 'instituicoes' => ['ELO']],
        'hiper' => ['label' => 'HIPER', 'instituicoes' => ['HIPERCARD']],
        'outros' => ['label' => 'OUTROS', 'instituicoes' => ['BANRICOMPRAS', 'CABAL']],
    ];

    public const CREDITO_GRUPOS = [
        'visa' => ['label' => 'VISA', 'instituicoes' => ['VISA']],
        'master' => ['label' => 'MASTER', 'instituicoes' => ['MASTERCARD']],
        'elo' => ['label' => 'ELO', 'instituicoes' => ['ELO']],
        'hiper' => ['label' => 'HIPER', 'instituicoes' => ['HIPERCARD']],
        'amex' => ['label' => 'AMEX', 'instituicoes' => ['AMEX']],
        'outros' => ['label' => 'OUTROS', 'instituicoes' => ['DINERS', 'CABAL']],
    ];

    public function dadosGrade(Plano $plano): array
    {
        $plano->loadMissing('taxas.royalties');

        return [
            'debito' => $this->dadosDebito($plano),
            'credito' => $this->dadosCredito($plano),
            'pix' => $this->dadosPix($plano),
        ];
    }

    public function salvar(Plano $plano, array $grade): void
    {
        foreach (self::DEBITO_GRUPOS as $grupo => $config) {
            $linha = $grade['debito'][$grupo] ?? [];
            $linha['comissao'] ??= $grade['debito']['comissao'] ?? null;
            $linha['ativo'] ??= $grade['debito']['ativo'] ?? false;
            $this->salvarGrupo($plano, 'debito', 1, $config['instituicoes'], $linha);
        }

        for ($parcelas = 1; $parcelas <= 18; $parcelas++) {
            foreach (self::CREDITO_GRUPOS as $grupo => $config) {
                $linha = $grade['credito'][$parcelas][$grupo] ?? [];
                $linha['comissao'] ??= $grade['credito'][$parcelas]['comissao'] ?? null;
                $linha['ativo'] ??= $grade['credito'][$parcelas]['ativo'] ?? false;
                $this->salvarGrupo($plano, 'credito', $parcelas, $config['instituicoes'], $linha);
            }
        }

        $this->salvarGrupo($plano, 'pix', 1, ['BACEN'], $grade['pix']['bacen'] ?? []);
    }

    private function dadosDebito(Plano $plano): array
    {
        return collect(self::DEBITO_GRUPOS)
            ->map(fn (array $config) => $this->linha($plano, 'debito', 1, $config['instituicoes'][0]))
            ->all();
    }

    private function dadosCredito(Plano $plano): array
    {
        $linhas = [];

        for ($parcelas = 1; $parcelas <= 18; $parcelas++) {
            foreach (self::CREDITO_GRUPOS as $grupo => $config) {
                $linhas[$parcelas][$grupo] = $this->linha($plano, 'credito', $parcelas, $config['instituicoes'][0]);
            }
        }

        return $linhas;
    }

    private function dadosPix(Plano $plano): array
    {
        return ['bacen' => $this->linha($plano, 'pix', 1, 'BACEN')];
    }

    private function linha(Plano $plano, string $tipo, int $parcelas, string $instituicao): array
    {
        $taxa = $plano->taxas
            ->where('tipo_transacao', $tipo)
            ->where('parcelas', $parcelas)
            ->firstWhere('instituicao', $instituicao);

        return [
            'taxa' => $taxa?->taxa_percentual,
            'comissao' => $taxa?->royalties?->firstWhere('nivel', 'admin')?->percentual,
            'ativo' => $taxa?->ativo ?? false,
            'existe' => $taxa !== null,
            'arranjo' => $this->mapa($instituicao, $tipo)['arranjo'] ?? null,
        ];
    }

    private function salvarGrupo(Plano $plano, string $tipo, int $parcelas, array $instituicoes, array $linha): void
    {
        $taxa = $this->normalizarPercentual($linha['taxa'] ?? null);
        $comissao = $this->normalizarPercentual($linha['comissao'] ?? null);
        $ativo = (bool) ($linha['ativo'] ?? false);

        foreach ($instituicoes as $instituicao) {
            $mapa = $this->mapa($instituicao, $tipo);

            if (! $mapa) {
                continue;
            }

            $planoTaxa = PlanoTaxa::where([
                'plano_id' => $plano->id,
                'arranjo_ur' => $mapa['arranjo'],
                'parcelas' => $parcelas,
            ])->first();

            if ($taxa === null && $comissao === null && ! $planoTaxa) {
                continue;
            }

            $planoTaxa = PlanoTaxa::updateOrCreate(
                [
                    'plano_id' => $plano->id,
                    'arranjo_ur' => $mapa['arranjo'],
                    'parcelas' => $parcelas,
                ],
                [
                    'instituicao' => $instituicao,
                    'tipo_transacao' => $tipo,
                    'meio_pagamento_cod' => $mapa['meio'],
                    'taxa_percentual' => $taxa ?? $planoTaxa?->taxa_percentual ?? 0,
                    'ativo' => $ativo,
                ]
            );

            if ($comissao !== null) {
                $admin = $this->adminUsuario();

                if ($admin) {
                    PlanoTaxaRoyalty::updateOrCreate(
                        ['plano_taxa_id' => $planoTaxa->id, 'usuario_id' => $admin->id],
                        ['nivel' => 'admin', 'percentual' => $comissao]
                    );
                }
            }
        }
    }

    private function normalizarPercentual(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $valor);
    }

    private function mapa(string $instituicao, string $tipo): ?array
    {
        return self::MAPA[$instituicao][$tipo] ?? null;
    }

    private function adminUsuario(): ?Usuario
    {
        $usuario = Auth::user();

        if ($usuario instanceof Usuario && $usuario->tipo === 'admin') {
            return $usuario;
        }

        return Usuario::where('tipo', 'admin')->oldest()->first();
    }
}
