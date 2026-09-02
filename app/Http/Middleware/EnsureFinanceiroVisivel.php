<?php

namespace App\Http\Middleware;

use App\Support\FinanceiroUi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceiroVisivel
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(FinanceiroUi::visivel(), 404);

        return $next($request);
    }
}
