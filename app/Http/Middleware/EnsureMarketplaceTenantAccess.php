<?php

namespace App\Http\Middleware;

use App\Services\MarketplaceTenantAccessService;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketplaceTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return $next($request);
        }

        $service = app(MarketplaceTenantAccessService::class);

        if ($service->sessaoAutenticadaCompativelComHost()) {
            return $next($request);
        }

        $slug = TenantBranding::current()?->slug;
        $service->encerrarSessaoIncompativel($request);

        return redirect()
            ->to(route('login'))
            ->withErrors(['email' => 'Faça login neste marketplace para continuar.']);
    }
}
