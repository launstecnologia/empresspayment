<?php

namespace App\Services;

use App\Models\KycAnalise;
use App\Models\KycHistorico;
use App\Models\SubUsuario;
use App\Models\Usuario;

class KycHistoricoService
{
    public function registrar(
        KycAnalise $kyc,
        string $evento,
        ?string $descricao = null,
        ?array $dados = null,
        Usuario|SubUsuario|null $autor = null,
    ): void {
        $usuario = $autor instanceof SubUsuario ? $autor : $autor;

        KycHistorico::create([
            'kyc_analise_id' => $kyc->id,
            'evento' => $evento,
            'descricao' => $descricao,
            'dados' => $dados,
            'autor_id' => $usuario?->id,
            'autor_nome' => $usuario instanceof Usuario
                ? $usuario->nomeExibicao()
                : ($autor instanceof SubUsuario ? $autor->nome : null),
            'created_at' => now(),
        ]);
    }
}
