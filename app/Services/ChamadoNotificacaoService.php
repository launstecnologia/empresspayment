<?php

namespace App\Services;

use App\Models\Chamado;
use App\Support\NotificacaoVars;

class ChamadoNotificacaoService
{
    public function __construct(
        private NotificacaoEmailService $notificacao,
    ) {}

    public function chamadoAberto(Chamado $chamado, string $mensagem): void
    {
        $vars = NotificacaoVars::chamado($chamado, $mensagem);
        $this->notificacao->enfileirarParaAdmins(
            'chamado.aberto',
            $vars,
            route('admin.chamados.show', $chamado->numero)
        );
    }

    public function respostaAdmin(Chamado $chamado, string $mensagem): void
    {
        $autor = NotificacaoVars::emailAutorChamado($chamado);

        if (blank($autor['email'])) {
            return;
        }

        $vars = NotificacaoVars::chamado($chamado, $mensagem);
        $link = route('chamados.show', $chamado->numero);
        $vars['link'] = $link;

        $this->notificacao->enfileirar('chamado.resposta', $autor['email'], $vars, $link);
    }

    public function respostaCliente(Chamado $chamado, string $mensagem): void
    {
        $vars = NotificacaoVars::chamado($chamado, $mensagem);
        $this->notificacao->enfileirarParaAdmins(
            'chamado.resposta',
            $vars,
            route('admin.chamados.show', $chamado->numero)
        );
    }

    public function statusAlterado(Chamado $chamado): void
    {
        $autor = NotificacaoVars::emailAutorChamado($chamado);

        if (blank($autor['email'])) {
            return;
        }

        $vars = NotificacaoVars::chamado($chamado);
        $link = route('chamados.show', $chamado->numero);
        $vars['link'] = $link;

        $this->notificacao->enfileirar('chamado.status', $autor['email'], $vars, $link);
    }
}
