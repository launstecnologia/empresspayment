<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use App\Support\PlatformMail;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use App\Support\TenantUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

class PasswordResetController extends Controller
{
    public function create(Request $request)
    {
        $tenantSlug = TenantBranding::porSlugAtivo(
            TenantContext::slugNaRequisicao($request) ?? session('tenant_slug')
        )?->slug;

        return view('auth.forgot-password', [
            'tenantSlug' => $tenantSlug,
            'tenantParam' => TenantUrl::parametro(),
        ]);
    }

    public function store(Request $request, PasswordResetService $service)
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $config = PlatformMail::configuracaoRecuperacaoSenha();

        if (! $config['ativo']) {
            return back()->withErrors([
                'email' => 'A recuperação de senha por e-mail está desativada. Contate o administrador.',
            ])->withInput();
        }

        try {
            $link = $service->solicitar($dados['email']);
        } catch (Throwable) {
            return back()->withErrors([
                'email' => 'Não foi possível enviar o e-mail. Verifique as configurações de SMTP em Configurações → E-mail.',
            ])->withInput();
        }

        $flash = ['status' => 'Se o e-mail estiver cadastrado, você receberá as instruções para redefinir a senha.'];

        if ($link && app()->environment('local')) {
            $flash['reset_link_dev'] = $link;
        }

        return back()->with($flash)->withInput();
    }

    public function edit(Request $request, string $token)
    {
        $tenantSlug = TenantBranding::porSlugAtivo(
            TenantContext::slugNaRequisicao($request) ?? session('tenant_slug')
        )?->slug;

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
            'tenantSlug' => $tenantSlug,
            'tenantParam' => TenantUrl::parametro(),
        ]);
    }

    public function update(Request $request, PasswordResetService $service)
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if (! $service->redefinir($dados['email'], $dados['token'], $dados['password'])) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Link inválido ou expirado. Solicite uma nova redefinição de senha.']);
        }

        $tenantSlug = TenantBranding::porSlugAtivo(
            TenantContext::slugNaRequisicao($request) ?? session('tenant_slug')
        )?->slug;

        return redirect()
            ->to(TenantUrl::aplicarTenant(route('login'), $tenantSlug))
            ->with('status', 'Senha alterada com sucesso. Faça login com a nova senha.');
    }
}
