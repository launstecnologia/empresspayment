<?php

namespace App\Support;

use App\Models\Estabelecimento;
use Illuminate\Support\Collection;

class KycDocumentosObrigatorios
{
    /**
     * @return array<int, array{grupo: string, tipos: array<int, string>}>
     */
    public static function grupos(Estabelecimento $estabelecimento): array
    {
        $base = [
            [
                'grupo' => 'identidade_frente',
                'tipos' => ['rg_frente', 'cnh_frente'],
            ],
            [
                'grupo' => 'identidade_verso',
                'tipos' => ['rg_verso', 'cnh_verso'],
            ],
            [
                'grupo' => 'comprovante_endereco',
                'tipos' => ['comprovante_endereco'],
            ],
        ];

        if ($estabelecimento->pessoa_tipo === 'juridica') {
            $base[] = [
                'grupo' => 'empresa',
                'tipos' => ['contrato_social', 'cartao_cnpj'],
            ];
        }

        return $base;
    }

    public static function atendidos(Collection $documentos): bool
    {
        $tiposEnviados = $documentos->pluck('tipo');

        foreach (self::grupos($documentos->first()?->estabelecimento ?? new Estabelecimento) as $grupo) {
            if ($tiposEnviados->intersect($grupo['tipos'])->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public static function tiposParaEstabelecimento(Estabelecimento $estabelecimento): array
    {
        $tipos = ['rg_frente', 'rg_verso', 'cnh_frente', 'cnh_verso', 'comprovante_endereco', 'selfie_documento'];

        if ($estabelecimento->pessoa_tipo === 'juridica') {
            $tipos[] = 'contrato_social';
            $tipos[] = 'cartao_cnpj';
        }

        return $tipos;
    }

    public static function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            'rg_frente' => 'RG — Frente',
            'rg_verso' => 'RG — Verso',
            'cnh_frente' => 'CNH — Frente',
            'cnh_verso' => 'CNH — Verso',
            'comprovante_endereco' => 'Comprovante de endereço',
            'contrato_social' => 'Contrato social',
            'cartao_cnpj' => 'Cartão CNPJ',
            'selfie_documento' => 'Selfie com documento',
            default => ucfirst(str_replace('_', ' ', $tipo)),
        };
    }
}
