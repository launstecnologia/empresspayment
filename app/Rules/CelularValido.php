<?php

namespace App\Rules;

use App\Support\DocumentoBrasil;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CelularValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! DocumentoBrasil::celularValido((string) $value)) {
            $fail('Informe um celular válido com DDD + 9 dígitos (ex: (62) 99277-7240).');
        }
    }
}
