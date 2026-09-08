<?php

namespace App\Services;

use App\Models\Estabelecimento;
use App\Models\Hierarquia;
use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Support\EstabelecimentoEtapaListagem;
use App\Support\EstabelecimentoSchema;
use Illuminate\Support\Facades\DB;

class InativacaoComercialService
{
    /**
     * @return array{usuarios:int, sub_usuarios:int, estabelecimentos:int}
     */
    public function desativarUsuarioECascata(Usuario $usuario): array
    {
        return DB::transaction(function () use ($usuario) {
            $usuarioIds = $this->idsUsuarioComDescendentes($usuario);
            $agora = now();

            $usuarios = Usuario::query()
                ->whereIn('id', $usuarioIds)
                ->where('ativo', true)
                ->update(['ativo' => false, 'updated_at' => $agora]);

            $subUsuarios = SubUsuario::query()
                ->whereIn('dono_id', $usuarioIds)
                ->where('ativo', true)
                ->update(['ativo' => false, 'updated_at' => $agora]);

            $estabelecimentos = 0;

            if (in_array($usuario->tipo, ['master', 'marketplace', 'revenda'], true)) {
                $estabelecimentos = Estabelecimento::withoutGlobalScopes()
                    ->where('ativo', true)
                    ->where(function ($query) use ($usuarioIds) {
                        $query->whereIn('cadastrado_por_id', $usuarioIds)
                            ->orWhereIn('master_id', $usuarioIds)
                            ->orWhereIn('marketplace_id', $usuarioIds)
                            ->orWhereIn('revenda_id', $usuarioIds);
                    })
                    ->update([
                        'ativo' => false,
                        'status' => EstabelecimentoSchema::statusParaBanco(EstabelecimentoEtapaListagem::NEGADO),
                        'updated_at' => $agora,
                    ]);
            }

            return [
                'usuarios' => $usuarios,
                'sub_usuarios' => $subUsuarios,
                'estabelecimentos' => $estabelecimentos,
            ];
        });
    }

    /**
     * @return array<int>
     */
    private function idsUsuarioComDescendentes(Usuario $usuario): array
    {
        $ids = collect([(int) $usuario->id]);
        $nosAtuais = Hierarquia::query()
            ->where('usuario_id', $usuario->id)
            ->pluck('id');

        while ($nosAtuais->isNotEmpty()) {
            $filhos = Hierarquia::query()
                ->whereIn('pai_id', $nosAtuais)
                ->get(['id', 'usuario_id']);

            if ($filhos->isEmpty()) {
                break;
            }

            $ids = $ids->merge($filhos->pluck('usuario_id')->map(fn ($id) => (int) $id));
            $nosAtuais = $filhos->pluck('id');
        }

        return $ids->unique()->values()->all();
    }
}
