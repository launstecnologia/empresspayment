@extends('layouts.app')

@php
    $tipoLabel = [
        'admin' => 'Administrador',
        'master' => 'Master',
        'marketplace' => 'Marketplace',
        'revenda' => 'Revenda',
    ];
    $filhos = $usuario->hierarquia?->filhos ?? collect();
    $documento = $usuario->pessoa_tipo === 'fisica' ? $usuario->cpf : $usuario->cnpj;
    $endereco = collect([$usuario->endereco, $usuario->numero, $usuario->bairro, $usuario->cidade, $usuario->uf])->filter()->join(', ');
@endphp

@section('title', 'Detalhes do '.$tipoLabel[$usuario->tipo])

@section('content')
<div class="mb-4 flex items-center justify-between gap-3">
    <div class="text-sm text-gray-500">
        <a href="{{ route('usuarios.index', in_array($usuario->tipo, ['master', 'marketplace', 'revenda'], true) ? ['tipo' => $usuario->tipo] : []) }}" class="font-semibold text-gray-700 hover:text-blue-600">{{ $tipoLabel[$usuario->tipo] }}</a>
        <span class="mx-2">›</span>
        <span>Detalhes</span>
    </div>
</div>

<section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold uppercase text-gray-800">{{ $usuario->nomeExibicao() }}</h2>
            <p class="mt-1 text-sm text-gray-400">
                @if ($usuario->tipo === 'admin')
                    Administrador da plataforma.
                @else
                    {{ $tipoLabel[$usuario->tipo] }} vinculado à hierarquia comercial.
                @endif
            </p>
        </div>
    </div>

    @if ($usuario->tipo !== 'admin')
    <div class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl bg-gray-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Documento</p>
            <p class="mt-2 font-semibold text-gray-800">{{ $documento ?: '-' }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">
                @if ($usuario->tipo === 'marketplace') Master
                @elseif ($usuario->tipo === 'revenda') Marketplace
                @else Pai Hierárquico
                @endif
            </p>
            <p class="mt-2 font-semibold text-gray-800">{{ $usuario->hierarquia?->pai?->usuario?->nomeExibicao() ?: '-' }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Usuários</p>
            <p class="mt-2 font-semibold text-gray-800">{{ $filhos->count() }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Estabelecimentos</p>
            <p class="mt-2 font-semibold text-gray-800">{{ $usuario->estabelecimentos->count() }}</p>
        </div>
    </div>
    @endif

    <div class="mt-6 grid gap-x-8 gap-y-3 border-t border-dashed border-gray-200 pt-5 text-sm text-gray-600 md:grid-cols-3">
        @if ($usuario->tipo === 'admin')
            <p><span class="font-semibold text-gray-700">Nome:</span> {{ $usuario->nome_completo ?: '-' }}</p>
            <p><span class="font-semibold text-gray-700">CPF:</span> {{ $usuario->cpf ?: '-' }}</p>
            <p><span class="font-semibold text-gray-700">E-mail:</span> {{ $usuario->email }}</p>
            <p><span class="font-semibold text-gray-700">Telefone:</span> {{ $usuario->telefone ?: '-' }}</p>
            <p><span class="font-semibold text-gray-700">Celular:</span> {{ $usuario->celular ?: '-' }}</p>
            @if ($endereco)
                <p><span class="font-semibold text-gray-700">Endereço:</span> {{ $endereco }}</p>
            @endif
        @else
            <p><span class="font-semibold text-gray-700">{{ $usuario->pessoa_tipo === 'fisica' ? 'Nome' : 'Razão social' }}:</span> {{ ($usuario->pessoa_tipo === 'fisica' ? $usuario->nome_completo : $usuario->razao_social) ?: '-' }}</p>
            @if ($usuario->percentual_retencao_pai !== null && in_array($usuario->tipo, ['marketplace', 'revenda'], true))
                <p>
                    <span class="font-semibold text-gray-700">Royalties:</span>
                    {{ number_format((float) $usuario->percentual_retencao_pai, 0, ',', '.') }}%
                </p>
            @endif
            <p><span class="font-semibold text-gray-700">E-mail:</span> {{ $usuario->email }}</p>
            <p><span class="font-semibold text-gray-700">Telefone:</span> {{ $usuario->telefone ?: '-' }}</p>
            <p><span class="font-semibold text-gray-700">Celular:</span> {{ $usuario->celular ?: '-' }}</p>
            <p><span class="font-semibold text-gray-700">Endereço:</span> {{ $endereco ?: '-' }}</p>
        @endif
    </div>

    <div class="mt-6 flex flex-wrap gap-2 border-t border-dashed border-gray-200 pt-5">
        <a href="{{ route('usuarios.edit', $usuario) }}" class="rounded-lg bg-indigo-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-800">
            <i class="fa-solid fa-pen mr-1"></i> Editar
        </a>
        @if ($usuario->tipo === 'admin')
            <form method="POST" action="{{ route('usuarios.resetar-senha', $usuario) }}" onsubmit="return confirm('Resetar a senha para 123456?')">
                @csrf
                <button type="submit" class="rounded-lg border border-orange-300 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-700 shadow-sm hover:bg-orange-100">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Resetar senha
                </button>
            </form>
        @endif
        @if ($usuario->tipo !== 'admin')
            <a href="{{ route('usuarios.subusuarios.create', $usuario) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                <i class="fa-solid fa-user-plus mr-1"></i> Cadastrar Usuário
            </a>
            @foreach ($proximosNiveis as $nivel)
                <a href="{{ route('usuarios.create', ['tipo' => $nivel, 'pai_id' => $usuario->id]) }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                    <i class="fa-solid fa-user-plus mr-1"></i> Cadastrar {{ $tipoLabel[$nivel] }}
                </a>
            @endforeach
            @if ($whitelabel)
                @include('admin.usuarios.partials.whitelabel-drawer')
            @endif
        @endif
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif
</section>

@if (in_array($usuario->tipo, ['master', 'marketplace']))
<section class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    @php
        $tituloFilhos = match($usuario->tipo) {
            'master'      => 'Marketplaces',
            'marketplace' => 'Revendas',
            default       => 'Usuários cadastrados abaixo',
        };
        $subtituloFilhos = match($usuario->tipo) {
            'master'      => 'Marketplaces vinculados a este master.',
            'marketplace' => 'Revendas vinculadas a este marketplace.',
            default       => 'Cadastros comerciais vinculados a este ' . strtolower($tipoLabel[$usuario->tipo]) . '.',
        };
        $colunaFilho = match($usuario->tipo) {
            'master'      => 'Marketplace',
            'marketplace' => 'Revenda',
            default       => 'Nível',
        };
    @endphp
    <div class="border-b border-gray-100 px-5 py-4">
        <h3 class="text-sm font-bold text-gray-800">{{ $tituloFilhos }}</h3>
        <p class="text-xs text-gray-400">{{ $subtituloFilhos }}</p>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-5 py-3">Código</th>
                <th class="px-5 py-3">Nome</th>
                <th class="px-5 py-3">{{ $colunaFilho }}</th>
                <th class="px-5 py-3">E-mail</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3 text-right">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filhos as $filho)
                @php
                    $subordinado = $filho->usuario;
                @endphp
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-4 font-semibold text-gray-800">#{{ str_pad($subordinado->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td class="px-5 py-4 font-semibold text-gray-800">{{ $subordinado->nomeExibicao() }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $tipoLabel[$subordinado->tipo] ?? ucfirst($subordinado->tipo) }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $subordinado->email }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $subordinado->ativo ? 'Ativo' : 'Inativo' }}</td>
                    <td class="px-5 py-4 text-right">
                        <a href="{{ route('usuarios.show', $subordinado) }}" class="font-semibold text-blue-600 hover:text-blue-700">Ver detalhes</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">Nenhum usuário cadastrado abaixo dele ainda.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>

<section class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Usuários operacionais</h3>
                <p class="text-xs text-gray-400">Acessos internos vinculados a este {{ strtolower($tipoLabel[$usuario->tipo]) }}.</p>
            </div>
            <a href="{{ route('usuarios.subusuarios.create', $usuario) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                + Cadastrar Usuário
            </a>
        </div>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-5 py-3">Código</th>
                <th class="px-5 py-3">Nome</th>
                <th class="px-5 py-3">E-mail</th>
                <th class="px-5 py-3">Perfil</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3 text-right">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($usuario->subUsuarios as $subUsuario)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-4 font-semibold text-gray-800">#{{ str_pad($subUsuario->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td class="px-5 py-4 font-semibold text-gray-800">{{ $subUsuario->nome }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $subUsuario->email }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $subUsuario->perfil?->nome ?: '-' }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $subUsuario->ativo ? 'Ativo' : 'Inativo' }}</td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            @if ($usuario->tipo === 'marketplace' && ($whitelabel ?? null) && ($whitelabel['urlsAcesso']['local'] ?? $whitelabel['urlsAcesso']['producao'] ?? null))
                                <a href="{{ $whitelabel['urlsAcesso']['local'] ?? $whitelabel['urlsAcesso']['producao'] }}" target="_blank" rel="noopener" class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 hover:bg-violet-100">
                                    <i class="fa-solid fa-right-to-bracket mr-1"></i> Login marketplace
                                </a>
                            @endif
                            <form method="POST" action="{{ route('usuarios.subusuarios.resetar-senha', [$usuario, $subUsuario]) }}" onsubmit="return confirm('Resetar a senha de {{ $subUsuario->nome }} para 123456?')">
                                @csrf
                                <button type="submit" class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs font-bold text-orange-700 hover:bg-orange-100">
                                    <i class="fa-solid fa-rotate-left mr-1"></i> Resetar senha
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">Nenhum usuário operacional cadastrado ainda.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>
@endif
@endsection
