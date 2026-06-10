<?php

namespace App\Support;

use App\Models\Estabelecimento;
use Illuminate\Database\Eloquent\Builder;

class EstabelecimentoEtapaListagem
{
    public const PENDENTE = 'pendente';

    public const APROVADO = 'aprovado';

    public const NEGADO = 'negado';

    public static function statusKyc(Estabelecimento $estabelecimento): string
    {
        return match ($estabelecimento->kycAnalise?->status) {
            'aprovado' => self::APROVADO,
            'reprovado' => self::NEGADO,
            default => self::PENDENTE,
        };
    }

    public static function statusPagBank(Estabelecimento $estabelecimento): string
    {
        if (in_array($estabelecimento->fv_status, ['erro', 'timeout'], true)) {
            return self::NEGADO;
        }

        if ($estabelecimento->fv_status === 'concluido' || filled($estabelecimento->pagbank_account_id)) {
            return self::APROVADO;
        }

        if ($estabelecimento->fv_status === 'erro_email' && filled($estabelecimento->pagbank_account_id)) {
            return self::APROVADO;
        }

        return self::PENDENTE;
    }

    public static function rotulo(string $etapa): string
    {
        return match ($etapa) {
            self::APROVADO => 'Aprovado',
            self::NEGADO => 'Negado',
            default => 'Pendente',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function badge(string $etapa): array
    {
        return match ($etapa) {
            self::APROVADO => ['bg-emerald-100 text-emerald-800', self::rotulo($etapa)],
            self::NEGADO => ['bg-red-100 text-red-800', self::rotulo($etapa)],
            default => ['bg-amber-100 text-amber-800', self::rotulo($etapa)],
        };
    }

    public static function aplicarFiltroStatus(Builder $query, string $etapa): void
    {
        match ($etapa) {
            self::APROVADO => $query->whereHas(
                'kycAnalise',
                fn (Builder $kyc) => $kyc->where('status', 'aprovado')
            ),
            self::NEGADO => $query->whereHas(
                'kycAnalise',
                fn (Builder $kyc) => $kyc->where('status', 'reprovado')
            ),
            default => $query->where(function (Builder $q) {
                $q->whereDoesntHave('kycAnalise')
                    ->orWhereHas(
                        'kycAnalise',
                        fn (Builder $kyc) => $kyc->whereIn('status', ['pendente', 'em_analise', 'revisao_manual'])
                    );
            }),
        };
    }

    public static function aplicarFiltroPagBank(Builder $query, string $etapa): void
    {
        match ($etapa) {
            self::APROVADO => $query->where(function (Builder $q) {
                $q->where('fv_status', 'concluido')
                    ->orWhereNotNull('pagbank_account_id')
                    ->orWhere(function (Builder $email) {
                        $email->where('fv_status', 'erro_email')
                            ->whereNotNull('pagbank_account_id');
                    });
            }),
            self::NEGADO => $query->whereIn('fv_status', ['erro', 'timeout']),
            default => $query->where(function (Builder $q) {
                $q->where(function (Builder $pendente) {
                    $pendente->whereNull('fv_status')
                        ->orWhereIn('fv_status', ['pendente', 'em_andamento']);
                })->whereNull('pagbank_account_id');
            }),
        };
    }
}
