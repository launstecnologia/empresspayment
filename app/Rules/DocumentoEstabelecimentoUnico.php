<?php

namespace App\Rules;

use App\Models\Estabelecimento;
use App\Support\DocumentoBrasil;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DocumentoEstabelecimentoUnico implements ValidationRule
{
    public function __construct(
        private string $campo,
        private ?int $ignoreEstabelecimentoId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = DocumentoBrasil::apenasDigitos((string) $value);

        if ($digits === '') {
            return;
        }

        if (! in_array($this->campo, ['cpf', 'cnpj'], true)) {
            return;
        }

        $coluna = $this->campo;
        $sql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$coluna}, '.', ''), '-', ''), '/', ''), ' ', ''), ',', '') = ?";

        $existe = Estabelecimento::withoutGlobalScopes()
            ->whereRaw($sql, [$digits])
            ->when($this->ignoreEstabelecimentoId, fn ($query) => $query->whereKeyNot($this->ignoreEstabelecimentoId))
            ->exists();

        if ($existe) {
            $fail($this->campo === 'cpf'
                ? 'Já existe um estabelecimento cadastrado com este CPF.'
                : 'Já existe um estabelecimento cadastrado com este CNPJ.');
        }
    }
}
