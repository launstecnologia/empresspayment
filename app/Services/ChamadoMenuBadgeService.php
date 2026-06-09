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

    public function __construct(private ChamadoService $chamados) {}

    public function contar(?Authenticatable $usuario): int
    {
        if (! $usuario instanceof Usuario && ! $usuario instanceof SubUsuario) {
            return 0;
        }

        if ($usuario instanceof Usuario && $usuario->tipo === 'admin') {
            return Chamado::query()
                ->whereIn('status', self::STATUS_ABERTOS)
                ->count();
        }

        return $this->chamados->queryVisiveisPara($usuario)
            ->whereIn('status', self::STATUS_ABERTOS)
            ->count();
    }
}
