<?php

namespace App\Services;

use App\Models\Plano;
use App\Models\Usuario;
use App\Support\UsuarioComercial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarketplacePlanoService
{
    public function marketplaceDoUsuario(?Usuario $usuario): ?Usuario
    {
        if (! $usuario) {
            return null;
        }

        return match ($usuario->tipo) {
            'marketplace' => $usuario,
            'revenda' => $usuario->paiHierarquico()?->tipo === 'marketplace'
                ? $usuario->paiHierarquico()
                : null,
            default => null,
        };
    }

    public function planosDisponiveis(?Usuario $usuario = null, ?int $marketplaceId = null): Collection
    {
        $usuario ??= UsuarioComercial::principal();

        if (UsuarioComercial::ehAdmin() || UsuarioComercial::tipo() === 'master') {
            if ($marketplaceId) {
                return $this->planosDoMarketplace($marketplaceId);
            }

            return Plano::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get();
        }

        $marketplace = $this->marketplaceDoUsuario($usuario);

        if (! $marketplace) {
            return collect();
        }

        return $this->planosDoMarketplace($marketplace->id);
    }

    public function planosDoMarketplace(int $marketplaceId): Collection
    {
        return Plano::query()
            ->where('ativo', true)
            ->whereHas('marketplaces', fn (Builder $q) => $q->whereKey($marketplaceId))
            ->orderBy('nome')
            ->get();
    }

    public function planoPermitido(?int $planoId, ?Usuario $usuario = null, ?int $marketplaceId = null): bool
    {
        if (! $planoId) {
            return true;
        }

        return $this->planosDisponiveis($usuario, $marketplaceId)
            ->contains(fn (Plano $plano) => (int) $plano->id === $planoId);
    }

    public function sincronizar(Usuario $marketplace, array $planoIds): void
    {
        abort_unless($marketplace->tipo === 'marketplace', 422);

        $planoIds = Plano::query()
            ->where('ativo', true)
            ->whereIn('id', $planoIds)
            ->pluck('id')
            ->all();

        $marketplace->planosHabilitados()->sync($planoIds);
    }
}
