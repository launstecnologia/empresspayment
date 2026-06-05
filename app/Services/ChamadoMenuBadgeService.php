<?php

namespace App\Services;

use App\Models\Chamado;
use App\Models\SubUsuario;
use App\Models\Usuario;
use Illuminate\Contracts\Auth\Authenticatable;

class ChamadoMenuBadgeService
{
    /** @var list<string> */
    public const STATUS_ABERTOS = ['aberto', 'em_atendimento', 'aguardando_cliente'];

    public function contar(?Authenticatable $usuario): int
    {
        if (! $usuario) {
            return 0;
        }

        $query = Chamado::query()->whereIn('status', self::STATUS_ABERTOS);

        if ($usuario instanceof SubUsuario) {
            return $query
                ->where('aberto_por_tipo', 'sub_usuario')
                ->where('aberto_por_id', $usuario->id)
                ->count();
        }

        if ($usuario instanceof Usuario && $usuario->tipo === 'admin') {
            return $query->count();
        }

        if ($usuario instanceof Usuario) {
            return $query
                ->where('aberto_por_tipo', 'usuario')
                ->where('aberto_por_id', $usuario->id)
                ->count();
        }

        return 0;
    }
}
