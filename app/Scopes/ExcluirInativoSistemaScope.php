<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ExcluirInativoSistemaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('estabelecimentos.ativo', true);
    }
}
