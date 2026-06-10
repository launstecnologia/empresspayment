<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceBranding;
use App\Models\SubUsuario;
use App\Services\MarketplaceTenantAccessService;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use App\Support\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request)
    {
        $tenantAccess = app(MarketplaceTenantAccessService::class);

        if (auth()->check() && ! TenantContext::slugNaRequisicao($request)) {
            if ($tenantAccess->sessaoAutenticadaCompativelComHost()) {
                return redirect()->to(
                    TenantUrl::aplicarTenant(route('dashboard'), TenantUrl::slugAtual($request))
                );
            }

            $tenantAccess->encerrarSessaoIncompativel($request);
        }

        $slugLogin = $this->tenantSlugAtual($request);

        if (auth()->check() && $slugLogin) {
            return response()
                ->view('auth.login', [
                    'tenantSlug' => $slugLogin,
                    'tenantParam' => TenantUrl::parametro(),
                    'sessaoAtiva' => true,
                    'usuarioLogado' => auth()->user(),
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        $tenantSlug = TenantBranding::porSlugAtivo(
            TenantContext::slugNaRequisicao($request)
                ?? (TenantContext::usuarioEhAdmin() ? null : session('tenant_slug'))
        )?->slug;

        return response()
            ->view('auth.login', [
                'tenantSlug' => $tenantSlug,
                'tenantParam' => TenantUrl::parametro(),
                'sessaoAtiva' => false,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function store(Request $request)
    {
        if (auth()->check()) {
            Auth::logout();
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $tenantSlug = $this->tenantSlugAtual($request);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return $this->erroLogin($request, 'E-mail ou senha incorretos.', $tenantSlug);
        }

        $user = Auth::user();

        $tenantSlug = $tenantSlug ?? $this->resolverTenantAutomatico($user);

        if ($tenantSlug) {
            $branding = TenantBranding::porSlugAtivo($tenantSlug);

            if ($branding) {
                TenantBranding::set($branding, TenantBranding::scope() === 'host' ? 'host' : 'preview');
            } else {
                $tenantSlug = null;
            }
        }

        $tenantId = TenantBranding::marketplaceId();

        if ($tenantId && ! app(MarketplaceTenantAccessService::class)->usuarioPodeAcessarTenant($user, $tenantId)) {
            Auth::logout();

            return $this->erroLogin($request, 'Este usuário não pertence a este marketplace.', $tenantSlug);
        }

        $request->session()->regenerate();

        app(MarketplaceTenantAccessService::class)->gravarEscopoAutenticacao($request);

        if (TenantContext::usuarioEhAdmin($user)) {
            TenantContext::limparSessaoLocal($request);

            return $this->redirectPosLogin($request, null);
        }

        if ($tenantSlug) {
            $request->session()->put('tenant_slug', $tenantSlug);

            return $this->redirectPosLogin($request, $tenantSlug);
        }

        TenantContext::limparSessaoLocal($request);

        return $this->redirectPosLogin($request, null);
    }

    public function destroy(Request $request)
    {
        $tenantSlug = TenantBranding::porSlugAtivo(
            TenantContext::slugNaRequisicao($request)
                ?? $request->input(TenantContext::parametro())
                ?? session('tenant_slug')
        )?->slug;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(TenantUrl::aplicarTenant(route('login'), is_string($tenantSlug) ? $tenantSlug : null));
    }

    private function redirectPosLogin(Request $request, ?string $tenantSlug): RedirectResponse
    {
        $destino = $tenantSlug
            ? TenantUrl::aplicarTenant(route('dashboard'), $tenantSlug)
            : route('dashboard');

        $intended = $request->session()->pull('url.intended');

        if ($intended && ! $this->urlEhLogin($intended)) {
            return redirect()->to(
                $tenantSlug ? TenantUrl::aplicarTenant($intended, $tenantSlug) : $intended
            );
        }

        return redirect()->to($destino);
    }

    private function urlEhLogin(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return str_ends_with(rtrim($path, '/'), '/login');
    }

    private function erroLogin(Request $request, string $mensagem, ?string $tenantSlug): RedirectResponse
    {
        $param = TenantContext::parametro();

        return redirect()
            ->to(TenantUrl::aplicarTenant(route('login'), $tenantSlug))
            ->withErrors(['email' => $mensagem])
            ->withInput($request->only('email', $param));
    }

    private function resolverTenantAutomatico(object $user): ?string
    {
        $marketplaceId = match (true) {
            $user instanceof SubUsuario && $user->dono_tipo === 'marketplace' => (int) $user->dono_id,
            $user instanceof Usuario && $user->tipo === 'marketplace' => (int) $user->id,
            default => null,
        };

        if (! $marketplaceId) {
            return null;
        }

        return MarketplaceBranding::query()
            ->where('marketplace_id', $marketplaceId)
            ->where('whitelabel_ativo', true)
            ->value('slug');
    }

    private function tenantSlugAtual(Request $request): ?string
    {
        if (TenantBranding::scope() === 'host' && TenantBranding::current()) {
            return TenantBranding::current()->slug;
        }

        return TenantBranding::porSlugAtivo(TenantContext::slugNaRequisicao($request))?->slug;
    }
}
