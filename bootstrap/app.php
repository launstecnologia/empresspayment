<?php

use App\Http\Middleware\ChecarTrocaSenha;
use App\Http\Middleware\EnsureMarketplaceTenantAccess;
use App\Http\Middleware\EnsureNivel;
use App\Http\Middleware\EnsurePermissao;
use App\Http\Middleware\ResolveMarketplaceTenant;
use App\Support\TenantUrl;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Http\Request;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            ResolveMarketplaceTenant::class,
        ]);

        $middleware->priority([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ResolveMarketplaceTenant::class,
        ]);

        $middleware->alias([
            'nivel' => EnsureNivel::class,
            'permissao' => EnsurePermissao::class,
            'tenant.access' => EnsureMarketplaceTenantAccess::class,
            'trocar.senha' => ChecarTrocaSenha::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            return TenantUrl::aplicarTenant(route('login'), TenantUrl::slugAtual($request));
        });

        $middleware->redirectUsersTo(function (Request $request) {
            return TenantUrl::aplicarTenant(route('dashboard'), TenantUrl::slugAtual($request));
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
