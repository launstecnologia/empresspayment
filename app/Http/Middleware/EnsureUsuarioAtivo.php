<?php

namespace App\Http\Middleware;

use App\Models\SubUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUsuarioAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && method_exists($user, 'getAttribute')
            && (
                $user->getAttribute('ativo') === false
                || ($user instanceof SubUsuario && $user->dono?->ativo === false)
            )
        ) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Seu acesso foi desativado.']);
        }

        return $next($request);
    }
}
