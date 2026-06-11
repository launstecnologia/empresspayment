<?php

namespace App\Support;

class DocumentoBrasil
{
    public static function apenasDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    public static function cpfValido(?string $cpf): bool
    {
        $cpf = self::apenasDigitos($cpf);

        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * ($t + 1 - $i);
            }
            $rem = (10 * $sum) % 11 % 10;
            if ((int) $cpf[$t] !== $rem) {
                return false;
            }
        }

        return true;
    }

    public static function formatarCpf(?string $cpf): string
    {
        $n = self::apenasDigitos($cpf);

        if (strlen($n) !== 11) {
            return (string) $cpf;
        }

        return substr($n, 0, 3).'.'
            .substr($n, 3, 3).'.'
            .substr($n, 6, 3).'-'
            .substr($n, 9, 2);
    }
}
