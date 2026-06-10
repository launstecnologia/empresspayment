<?php

namespace App\Support;

use App\Models\Estabelecimento;

class PagBankEstabelecimentoStatus
{
    public const PENDENTE = 'pendente';

    public const AGUARDANDO_CADASTRO = 'aguardando_cadastro';

    public const AGUARDANDO_EDI = 'aguardando_edi';

    public const EDI_ATIVO = 'edi_ativo';

    public static function codigo(Estabelecimento $estabelecimento): string
    {
        if ($estabelecimento->pagbank_account_id) {
            return $estabelecimento->pagbank_edi_ativo
                ? self::EDI_ATIVO
                : self::AGUARDANDO_EDI;
        }

        if (EstabelecimentoEtapaListagem::normalizarStatus($estabelecimento->status) === EstabelecimentoEtapaListagem::PENDENTE) {
            return self::AGUARDANDO_CADASTRO;
        }

        return self::PENDENTE;
    }

    public static function rotulo(Estabelecimento $estabelecimento): string
    {
        return match (self::codigo($estabelecimento)) {
            self::AGUARDANDO_CADASTRO => 'Aguardando cadastro PagBank',
            self::AGUARDANDO_EDI => 'Cadastrado — aguardando EDI',
            self::EDI_ATIVO => 'EDI ativo',
            default => 'Pendente',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function badge(Estabelecimento $estabelecimento): array
    {
        return match (self::codigo($estabelecimento)) {
            self::AGUARDANDO_CADASTRO => ['bg-sky-100 text-sky-800', self::rotulo($estabelecimento)],
            self::AGUARDANDO_EDI => ['bg-amber-100 text-amber-800', self::rotulo($estabelecimento)],
            self::EDI_ATIVO => ['bg-emerald-100 text-emerald-800', self::rotulo($estabelecimento)],
            default => ['bg-gray-100 text-gray-700', self::rotulo($estabelecimento)],
        };
    }

    public static function podeEnfileirarCadastro(Estabelecimento $estabelecimento): bool
    {
        if ($estabelecimento->pagbank_account_id) {
            return false;
        }

        $kyc = $estabelecimento->kycAnalise;

        return $kyc && $kyc->status === 'aprovado';
    }
}
