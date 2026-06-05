<?php

namespace App\Http\Middleware;

use App\Models\SubUsuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissao
{
    public function handle(Request $request, Closure $next, string $modulo, string $acao = 'ver'): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof SubUsuario) {
            return $next($request);
        }

        $campo = $acao === 'editar' ? 'pode_editar' : 'pode_ver';
        $permissaoIndividual = $usuario->modulos()->where('modulo', $modulo)->first();

        if ($permissaoIndividual) {
            abort_unless((bool) $permissaoIndividual->{$campo}, 403);

            return $next($request);
        }

        $permissaoPerfil = $usuario->perfil?->modulos()->where('modulo', $modulo)->first();

        abort_unless($permissaoPerfil && (bool) $permissaoPerfil->{$campo}, 403);

        return $next($request);
    }
}
