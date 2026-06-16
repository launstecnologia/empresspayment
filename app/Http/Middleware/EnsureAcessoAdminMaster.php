<?php

namespace App\Http\Middleware;

use App\Support\UsuarioComercial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcessoAdminMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            UsuarioComercial::ehMarketplaceOuRevenda(),
            403,
            'Acesso restrito ao administrador.'
        );

        return $next($request);
    }
}
