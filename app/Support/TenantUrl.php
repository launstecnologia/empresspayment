<?php

namespace App\Support;

use Illuminate\Http\Request;

class TenantUrl
{
    public static function parametro(): string
    {
        return TenantContext::parametro();
    }

    public static function slugAtual(?Request $request = null): ?string
    {
        $request ??= request();

        if (TenantBranding::scope() === 'host' && TenantBranding::current()) {
            return TenantBranding::current()->slug;
        }

        $slug = TenantBranding::porSlugAtivo(TenantContext::slugNaRequisicao($request))?->slug;

        if ($slug) {
            return $slug;
        }

        if (TenantContext::usuarioEhAdmin()) {
            return null;
        }

        if ($request->hasSession()) {
            $slug = $request->session()->get('tenant_slug');

            return TenantBranding::porSlugAtivo(is_string($slug) ? $slug : null)?->slug;
        }

        return null;
    }

    public static function aplicarTenant(string $url, ?string $slug = null): string
    {
        if (! app()->environment('local') || TenantContext::usuarioEhAdmin()) {
            return $url;
        }

        $slug = TenantBranding::porSlugAtivo($slug ?? self::slugAtual())?->slug;

        if (! $slug) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.self::parametro().'='.$slug;
    }
}
