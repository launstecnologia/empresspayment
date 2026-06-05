<?php

namespace App\Services;

use App\Models\Hierarquia;
use App\Models\Usuario;
use Illuminate\Support\Collection;

class HierarquiaService
{
    public const ORDEM = ['admin', 'master', 'marketplace', 'revenda'];

    public function criarNo(Usuario $usuario, ?Usuario $pai = null): Hierarquia
    {
        if ($pai) {
            $this->validarPai($usuario->tipo, $pai->tipo);
        }

        return Hierarquia::updateOrCreate(
            ['usuario_id' => $usuario->id],
            [
                'pai_id' => $pai?->hierarquia?->id,
                'nivel' => $usuario->tipo,
            ]
        );
    }

    public function ancestrais(Usuario $usuario): Collection
    {
        $no = $usuario->hierarquia;
        $ancestrais = collect();

        while ($no?->pai) {
            $ancestrais->prepend($no->pai->usuario);
            $no = $no->pai;
        }

        return $ancestrais->filter();
    }

    public function cadeiaParaEstabelecimento(Usuario $usuario): array
    {
        $cadeia = $this->ancestrais($usuario)->push($usuario);

        return [
            'cadastrado_por_id' => $usuario->id,
            'cadastrado_por_nivel' => $usuario->tipo,
            'master_id' => $cadeia->firstWhere('tipo', 'master')?->id,
            'marketplace_id' => $cadeia->firstWhere('tipo', 'marketplace')?->id,
            'revenda_id' => $cadeia->firstWhere('tipo', 'revenda')?->id,
        ];
    }

    public function proximosNiveisPermitidos(Usuario $usuario): array
    {
        return match ($usuario->tipo) {
            'admin' => ['master', 'marketplace', 'revenda'],
            'master' => ['marketplace'],
            'marketplace' => ['revenda'],
            default => [],
        };
    }

    private function validarPai(string $tipoFilho, string $tipoPai): void
    {
        $ordemFilho = array_search($tipoFilho, self::ORDEM, true);
        $ordemPai = array_search($tipoPai, self::ORDEM, true);

        abort_if($ordemFilho === false || $ordemPai === false || $ordemFilho <= $ordemPai, 422, 'Hierarquia invalida.');
    }
}
