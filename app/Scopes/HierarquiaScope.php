<?php

namespace App\Scopes;

use App\Models\SubUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class HierarquiaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $usuario = Auth::user();

        if (! $usuario) {
            return;
        }

        if ($usuario instanceof SubUsuario) {
            $usuario = $usuario->dono;
        }

        if (! $usuario instanceof Usuario || $usuario->tipo === 'admin') {
            return;
        }

        match ($usuario->tipo) {
            'master' => $builder->where('master_id', $usuario->id),
            'marketplace' => $builder->where('marketplace_id', $usuario->id),
            'revenda' => $builder->where('revenda_id', $usuario->id),
            default => null,
        };
    }
}
