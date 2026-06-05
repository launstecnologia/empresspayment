<?php

namespace App\Services;

use App\Models\Chamado;
use App\Models\SubUsuario;
use App\Models\Usuario;
use Illuminate\Contracts\Auth\Authenticatable;

class NotificacaoHeaderService
{
    /**
     * @return list<array{tipo: string, titulo: string, mensagem: string, url: string, icone: string}>
     */
    public function listar(?Authenticatable $usuario, int $limite = 8): array
    {
        if (! $usuario) {
            return [];
        }

        $itens = [];

        if ($usuario instanceof SubUsuario) {
            $usuario = $usuario->dono;
        }

        $chamadosAbertos = app(ChamadoMenuBadgeService::class)->contar($usuario);

        if ($chamadosAbertos > 0) {
            $url = ($usuario instanceof Usuario && $usuario->tipo === 'admin')
                ? route('admin.chamados.index')
                : route('chamados.index');

            $itens[] = [
                'tipo' => 'chamado',
                'titulo' => 'Chamados em aberto',
                'mensagem' => "{$chamadosAbertos} chamado(s) aguardando atendimento.",
                'url' => $url,
                'icone' => 'fa-ticket',
            ];
        }

        if ($usuario instanceof Usuario && $usuario->tipo === 'admin') {
            $novosAdmin = Chamado::query()
                ->where('visualizado_admin', false)
                ->whereIn('status', ChamadoMenuBadgeService::STATUS_ABERTOS)
                ->count();

            if ($novosAdmin > 0) {
                $itens[] = [
                    'tipo' => 'chamado_admin',
                    'titulo' => 'Novos chamados',
                    'mensagem' => "{$novosAdmin} chamado(s) ainda não visualizado(s) pelo admin.",
                    'url' => route('admin.chamados.index'),
                    'icone' => 'fa-bell',
                ];
            }
        }

        return array_slice($itens, 0, $limite);
    }

    public function total(?Authenticatable $usuario): int
    {
        return count($this->listar($usuario, 20));
    }
}
