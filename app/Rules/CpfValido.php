<?php

namespace App\Rules;

use App\Support\DocumentoBrasil;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CpfValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! DocumentoBrasil::cpfValido((string) $value)) {
            $fail('Informe um CPF válido.');
        }
    }
}
