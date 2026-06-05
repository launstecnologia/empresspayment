<?php

namespace App\Support;

use App\Models\SubUsuario;
use App\Models\Usuario;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;

class AvatarUsuario
{
    public static function url(?Authenticatable $usuario): ?string
    {
        if (! $usuario || ! $usuario->getAttribute('avatar_path')) {
            return null;
        }

        return Storage::disk('public')->url($usuario->getAttribute('avatar_path'));
    }

    public static function iniciais(?Authenticatable $usuario): string
    {
        if (! $usuario) {
            return '?';
        }

        $nome = method_exists($usuario, 'nomeExibicao')
            ? $usuario->nomeExibicao()
            : ($usuario->getAttribute('nome') ?? $usuario->getAttribute('email') ?? '?');

        return mb_strtoupper(mb_substr($nome, 0, 1));
    }

    public static function salvar(?Authenticatable $usuario, $arquivo): ?string
    {
        if (! $usuario || ! $arquivo) {
            return $usuario?->getAttribute('avatar_path');
        }

        $pasta = $usuario instanceof SubUsuario ? 'sub_usuarios' : 'usuarios';
        $caminhoAnterior = $usuario->getAttribute('avatar_path');

        if ($caminhoAnterior) {
            Storage::disk('public')->delete($caminhoAnterior);
        }

        $caminho = $arquivo->store("avatars/{$pasta}/{$usuario->getAuthIdentifier()}", 'public');

        return $caminho;
    }
}
