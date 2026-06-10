<?php

namespace App\Rules;

use App\Models\SubUsuario;
use App\Models\Usuario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EmailUnicoAutenticacao implements ValidationRule
{
    public function __construct(
        private ?int $ignoreUsuarioId = null,
        private ?int $ignoreSubUsuarioId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim((string) $value));

        if ($email === '') {
            return;
        }

        $emUso = Usuario::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($this->ignoreUsuarioId, fn ($query) => $query->whereKeyNot($this->ignoreUsuarioId))
            ->exists()
            || SubUsuario::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->when($this->ignoreSubUsuarioId, fn ($query) => $query->whereKeyNot($this->ignoreSubUsuarioId))
                ->exists();

        if ($emUso) {
            $fail('Este e-mail já está cadastrado no sistema.');
        }
    }
}
