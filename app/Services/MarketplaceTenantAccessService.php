<?php

namespace App\Services;

use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use Illuminate\Http\Request;

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
        if (! $this->sessaoAutenticadaCompativelComHost()) {
            auth()->logout();
            abort(403, 'Você não tem permissão para acessar este marketplace.');
        }
    }

    public function sessaoAutenticadaCompativelComHost(): bool
    {
        if (! auth()->check()) {
            return true;
        }

        if (TenantBranding::scope() !== 'host' || ! TenantBranding::current()) {
            return true;
        }

        $hostMarketplaceId = TenantBranding::marketplaceId();

        if (! $hostMarketplaceId) {
            return true;
        }

        if (TenantContext::usuarioEhAdmin()) {
            return true;
        }

        if (! $this->usuarioPodeAcessarTenant(auth()->user(), $hostMarketplaceId)) {
            return false;
        }

        $escopoLogin = session('auth_tenant_marketplace_id');

        if ($escopoLogin !== null && (int) $escopoLogin !== (int) $hostMarketplaceId) {
            return false;
        }

        return true;
    }

    public function gravarEscopoAutenticacao(Request $request): void
    {
        $marketplaceId = TenantBranding::scope() === 'host' ? TenantBranding::marketplaceId() : null;

        $request->session()->put('auth_tenant_marketplace_id', $marketplaceId);

        if ($marketplaceId && TenantBranding::current()) {
            $request->session()->put('tenant_slug', TenantBranding::current()->slug);
        }
    }

    public function encerrarSessaoIncompativel(Request $request): void
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
