<?php

namespace App\Services;

use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Support\TenantBranding;

class MarketplaceTenantAccessService
{
    public function usuarioPodeAcessarTenant(?object $user, ?int $marketplaceId): bool
    {
        if (! $marketplaceId) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user instanceof SubUsuario) {
            $user = $user->dono;
        }

        if (! $user instanceof Usuario) {
            return false;
        }

        if ($user->tipo === 'admin') {
            return true;
        }

        if ($user->tipo === 'marketplace') {
            return (int) $user->id === (int) $marketplaceId;
        }

        if (in_array($user->tipo, ['master', 'revenda'], true)) {
            return $this->usuarioPertenceAMarketplace($user, $marketplaceId);
        }

        return false;
    }

    private function usuarioPertenceAMarketplace(Usuario $usuario, int $marketplaceId): bool
    {
        $usuario->loadMissing('hierarquia.pai.usuario.hierarquia.pai.usuario');

        $atual = $usuario;

        for ($i = 0; $i < 5 && $atual; $i++) {
            if ($atual->tipo === 'marketplace' && (int) $atual->id === $marketplaceId) {
                return true;
            }

            $atual = $atual->hierarquia?->pai?->usuario;
        }

        return false;
    }

    public function validarSessaoAtual(): void
    {
        $marketplaceId = TenantBranding::marketplaceId();

        if (! $marketplaceId) {
            return;
        }

        if (! $this->usuarioPodeAcessarTenant(auth()->user(), $marketplaceId)) {
            auth()->logout();
            abort(403, 'Você não tem permissão para acessar este marketplace.');
        }
    }
}
