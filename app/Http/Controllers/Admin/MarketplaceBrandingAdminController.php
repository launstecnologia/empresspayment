<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\MarketplaceBrandingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceBrandingAdminController extends Controller
{
    public function update(Request $request, Usuario $usuario, MarketplaceBrandingService $service)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);
        abort_unless($usuario->tipo === 'marketplace', 404);

        $branding = $usuario->marketplaceBranding ?? $service->criarPara($usuario);

        $dados = $request->validate([
            'app_name' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::unique('marketplace_brandings', 'slug')->ignore($branding->id)],
            'custom_domain' => ['nullable', 'string', 'max:255', Rule::unique('marketplace_brandings', 'custom_domain')->ignore($branding->id)],
            'whitelabel_ativo' => ['boolean'],
            'verificar_dominio' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'logo_white' => ['nullable', 'image', 'max:4096'],
            'favicon' => ['nullable', 'image', 'max:2048'],
            'remover_logo' => ['boolean'],
            'remover_logo_white' => ['boolean'],
            'remover_favicon' => ['boolean'],
        ]);

        $service->aplicarAtualizacao($branding, $usuario, $dados, $request);

        return redirect()
            ->route('usuarios.show', $usuario)
            ->with('status', 'Whitelabel do marketplace atualizado.');
    }
}
