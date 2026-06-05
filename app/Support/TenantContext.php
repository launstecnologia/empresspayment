<?php

namespace App\Support;

use App\Models\SubUsuario;
use App\Models\Usuario;
use Illuminate\Http\Request;

class TenantContext
{
    public static function parametro(): string
    {
        return (string) config('tenant.local_query', 'tenant');
    }

    public static function slugNaRequisicao(?Request $request = null): ?string
    {
        $request ??= request();
        $param = self::parametro();
        $slug = $request->query($param) ?? $request->input($param);

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return strtolower(trim($slug));
    }

    public static function isPlatformHost(?string $host = null): bool
    {
        $host = strtolower(trim($host ?? request()->getHost()));

        $platformHosts = array_map('strtolower', config('tenant.platform_hosts', []));

        if (in_array($host, $platformHosts, true)) {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($appHost && $host === strtolower((string) $appHost)) {
            return true;
        }

        $base = strtolower((string) config('tenant.base_domain'));

        return $base !== '' && ($host === $base || $host === 'www.'.$base || $host === 'app.'.$base);
    }

    public static function usuarioEhAdmin(?object $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if ($user instanceof SubUsuario) {
            return $user->dono_tipo === 'admin';
        }

        return $user instanceof Usuario && $user->tipo === 'admin';
    }

    public static function usuarioEhMarketplace(?object $user = null): bool
    {
        $user ??= auth()->user();

        if ($user instanceof SubUsuario) {
            return $user->dono_tipo === 'marketplace';
        }

        return $user instanceof Usuario && $user->tipo === 'marketplace';
    }

    public static function limparSessaoLocal(?Request $request = null): void
    {
        $request ??= request();

        if ($request->hasSession()) {
            $request->session()->forget('tenant_slug');
        }
    }
}
