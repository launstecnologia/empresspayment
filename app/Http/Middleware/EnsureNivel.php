<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNivel
{
    public function handle(Request $request, Closure $next, string ...$niveis): Response
    {
        abort_unless($request->user() && in_array($request->user()->tipo, $niveis, true), 403);

        return $next($request);
    }
}
