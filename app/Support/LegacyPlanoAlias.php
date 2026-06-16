<?php

namespace App\Support;

use App\Models\Plano;
use Illuminate\Support\Str;

/**
 * Nomes/códigos do Excel legado → nome do plano cadastrado na plataforma.
 *
 * O resolver padrão compara texto exato (uppercase/ascii). No Excel os planos
 * usam convenções diferentes (DEBITOD0, D+0 29, nnexpresspay…).
 */
class LegacyPlanoAlias
{
    /** @var array<string, string> chave normalizada => nome do plano na plataforma */
    private const ALIAS_PARA_NOME = [
        'PLANO EXPRESS DEBITOD0' => 'PLANO EXPRESS DEBITODOR',
        'PLANO EXPRESS DEBITOCREDD0' => 'PLANO DEBITOCREDDO',
        'PLANO EXPRESS PARCED0' => 'PLANO PARCDO',
        'PLANO EXPRESS D+1 START' => 'EXPRESS73299p2',
        'PLANO EXPRESS D+1 ESPECIAL' => 'EXPRESS73299p2',
        'PLANO EXPRESSPAY D+0 27' => 'PLANO EXPRESSPAY 027',
        'PLANO EXPRESS D+0 27' => 'PLANO EXPRESSPAY 027',
        'PLANO EXPRESSPAY D+0 28' => 'PLANO EXPRESSPAY 28RETORNO',
        'PLANO EXPRESS D+0 28' => 'PLANO EXPRESSPAY 28RETORNO',
        'PLANO EXPRESSPAY D+0 29' => 'PLANO EXPRESSPAY 029',
        'PLANO EXPRESS D+0 29' => 'PLANO EXPRESSPAY 029',
        'PLANO EXPRESSPAY D+0 31' => 'PLANO EXPRESSPAY 031',
        'PLANO EXPRESSPAY D+30 32' => 'PLANO EXPRESSPAY 032',
        'NNEXPRESSPAY7299DEBITOD0R' => 'PLANO EXPRESS DEBITODOR',
        'NNEXPRESSPAY7399D028RETORNO' => 'EXPRESS73299p2',
        'COMERCIO GERAL PARCELE' => 'PLANO PARCDO',
    ];

    public static function nomePlataforma(?string $planCode): ?string
    {
        $planCode = trim((string) $planCode);
        if ($planCode === '' || strcasecmp($planCode, 'Sem Plano') === 0) {
            return null;
        }

        foreach (self::chaves($planCode) as $chave) {
            if (isset(self::ALIAS_PARA_NOME[$chave])) {
                return self::ALIAS_PARA_NOME[$chave];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function chaves(?string $texto): array
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return [];
        }

        $ascii = Str::ascii($texto);

        return array_values(array_unique(array_filter([
            mb_strtoupper($texto),
            mb_strtoupper($ascii),
        ])));
    }

    /**
     * @return array<string, string> excel plan_code => nome plano plataforma
     */
    public static function mapaExibicao(): array
    {
        $mapa = [];
        foreach (self::ALIAS_PARA_NOME as $chave => $nome) {
            $mapa[$chave] = $nome;
        }

        return $mapa;
    }

    public static function resolverPlano(?string $planCode, array $cachePlanoPorChave): ?Plano
    {
        $planCode = trim((string) $planCode);
        if ($planCode === '') {
            return null;
        }

        $nomeAlias = self::nomePlataforma($planCode);
        if ($nomeAlias !== null) {
            foreach (self::chaves($nomeAlias) as $chave) {
                if (isset($cachePlanoPorChave[$chave])) {
                    return $cachePlanoPorChave[$chave];
                }
            }
        }

        foreach (self::chaves($planCode) as $chave) {
            if (isset($cachePlanoPorChave[$chave])) {
                return $cachePlanoPorChave[$chave];
            }
        }

        return null;
    }
}
