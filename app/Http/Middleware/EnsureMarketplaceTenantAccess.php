<?php

namespace App\Http\Middleware;

use App\Services\MarketplaceTenantAccessService;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use App\Support\TenantUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketplaceTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && TenantBranding::deveExibirMarcaTenant()) {
            $marketplaceId = TenantBranding::marketplaceId();

            if ($marketplaceId && ! app(MarketplaceTenantAccessService::class)->usuarioPodeAcessarTenant(auth()->user(), $marketplaceId)) {
                $slug = TenantBranding::current()?->slug;
                auth()->logout();

                return redirect()->to(TenantUrl::aplicarTenant(route('login'), $slug))
                    ->withErrors(['email' => 'Você não tem permissão para acessar este marketplace.'])
                    ->withInput([TenantContext::parametro() => $slug]);
            }
        }

        return $next($request);
    }
}
