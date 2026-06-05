<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LogService
{
    public function registrar(Model|string $entidade, int $entidadeId, string $acao, ?string $mensagem = null, ?array $anteriores = null, ?array $novos = null): Log
    {
        $usuario = Auth::user();
        $nome = $usuario instanceof Usuario ? $usuario->nomeExibicao() : ($usuario->nome ?? null);

        return Log::create([
            'entidade' => is_string($entidade) ? $entidade : class_basename($entidade),
            'entidade_id' => $entidadeId,
            'acao' => $acao,
            'usuario_id' => $usuario instanceof Usuario ? $usuario->id : null,
            'usuario_nome' => $nome,
            'mensagem' => $mensagem,
            'dados_anteriores' => $anteriores,
            'dados_novos' => $novos,
        ]);
    }
}
