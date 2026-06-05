<?php

namespace App\Auth;

use App\Models\SubUsuario;
use App\Models\Usuario;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;

class MultiUserProvider implements UserProvider
{
    public function __construct(private readonly Hasher $hasher) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        if (is_string($identifier) && str_starts_with($identifier, 'sub:')) {
            return SubUsuario::with('dono')->find((int) substr($identifier, 4));
        }

        return Usuario::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        //
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;

        if (! $email) {
            return null;
        }

        return Usuario::where('email', $email)->first()
            ?: SubUsuario::with('dono')->where('email', $email)->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? '';

        if (method_exists($user, 'getAttribute') && $user->getAttribute('ativo') === false) {
            return false;
        }

        if ($user instanceof SubUsuario && ! $user->dono?->ativo) {
            return false;
        }

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        //
    }
}
