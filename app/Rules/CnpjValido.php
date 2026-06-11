<?php

namespace App\Rules;

use App\Support\DocumentoBrasil;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CnpjValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! DocumentoBrasil::cnpjValido((string) $value)) {
            $fail('Informe um CNPJ válido.');
        }
    }
}
