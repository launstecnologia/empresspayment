<?php

namespace App\Http\Middleware;

use App\Support\UsuarioComercial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMarketplacePlanos
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(UsuarioComercial::tipo(), ['marketplace', 'revenda'], true)) {
            $plano = $request->route('plano');

            return redirect()->route('comissoes.meu-plano', array_filter([
                'plano' => $plano?->id ?? $request->integer('plano') ?: null,
            ]));
        }

        return $next($request);
    }
}
