<?php

namespace App\Support;

class KycDivergenciaHelper
{
    private const LABELS = [
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'nome' => 'Nome',
        'razao_social' => 'Razão social',
        'data_nascimento' => 'Data de nascimento',
        'documento_expirado' => 'Validade do documento',
        'uf' => 'Estado (UF)',
        'cidade' => 'Cidade',
        'comprovante_vencido' => 'Validade do comprovante',
    ];

    /**
     * @param  array<string, mixed>  $divergencias
     */
    public static function mensagemReenvio(array $divergencias): string
    {
        $campos = self::listarCampos($divergencias);
        $provavelOcr = self::provavelErroLeitura($divergencias);

        if ($provavelOcr) {
            return 'Não foi possível confirmar os dados do documento com clareza. '
                .'A leitura automática pode ter errado ('.implode(', ', $campos).'). '
                .'Envie uma nova foto nítida, sem reflexo e com todos os dados visíveis.';
        }

        return 'Os dados lidos no documento não bateram com o cadastro ('.implode(', ', $campos).'). '
            .'Verifique se o arquivo está legível e, se necessário, envie uma nova foto na aba Documentos.';
    }

    /**
     * @param  array<string, mixed>  $divergencias
     * @return array<int, string>
     */
    public static function detalhesLegiveis(array $divergencias): array
    {
        $linhas = [];

        foreach ($divergencias as $campo => $info) {
            if (! is_array($info)) {
                continue;
            }

            $label = self::LABELS[$campo] ?? ucfirst(str_replace('_', ' ', $campo));

            if ($campo === 'documento_expirado') {
                $linhas[] = "{$label}: documento vencido em ".($info['expiracao'] ?? '—');
                continue;
            }

            if ($campo === 'comprovante_vencido') {
                $linhas[] = "{$label}: comprovante fora do prazo de 90 dias";
                continue;
            }

            if (isset($info['cadastro'], $info['documento'])) {
                $linhas[] = "{$label}: cadastro «{$info['cadastro']}» × documento «{$info['documento']}»";
                continue;
            }

            $linhas[] = "{$label}: divergência detectada";
        }

        return $linhas;
    }

    /**
     * @param  array<string, mixed>  $divergencias
     */
    public static function provavelErroLeitura(array $divergencias): bool
    {
        foreach ($divergencias as $campo => $info) {
            if (! is_array($info)) {
                continue;
            }

            if (in_array($campo, ['cpf', 'cnpj'], true) && isset($info['cadastro'], $info['documento'])) {
                $cad = preg_replace('/\D/', '', (string) $info['cadastro']);
                $doc = preg_replace('/\D/', '', (string) $info['documento']);
                if ($cad && $doc && self::diferencaPequenaNumeros($cad, $doc)) {
                    return true;
                }
            }

            if (in_array($campo, ['nome', 'razao_social', 'cidade'], true) && isset($info['similaridade'])) {
                if ((float) $info['similaridade'] >= 65) {
                    return true;
                }
            }
        }

        return count($divergencias) === 1
            && array_key_exists('cpf', $divergencias);
    }

    /**
     * @param  array<string, mixed>  $divergencias
     * @return array<int, string>
     */
    private static function listarCampos(array $divergencias): array
    {
        return array_values(array_map(
            fn (string $campo) => self::LABELS[$campo] ?? $campo,
            array_keys($divergencias),
        ));
    }

    private static function diferencaPequenaNumeros(string $a, string $b): bool
    {
        if ($a === $b) {
            return false;
        }

        if (strlen($a) !== strlen($b)) {
            return false;
        }

        $diff = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            if ($a[$i] !== $b[$i]) {
                $diff++;
            }
        }

        return $diff <= 2;
    }
}
