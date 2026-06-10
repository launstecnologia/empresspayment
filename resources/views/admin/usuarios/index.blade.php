@extends('layouts.app')

@php
    $tipoLabel = [
        'master' => 'Masters',
        'marketplace' => 'Marketplaces',
        'revenda' => 'Revendas',
    ];
    $tipoSingular = [
        'master' => 'Master',
        'marketplace' => 'Marketplace',
        'revenda' => 'Revenda',
    ];
    $tituloLista = ($listaTodosUsuarios ?? false)
        ? 'Todos os Usuários'
        : ($tipoAtual ? ($tipoLabel[$tipoAtual] ?? 'Usuários') : 'Usuários Admin');
    $rotuloNovo = $tipoAtual ? 'Novo '.($tipoSingular[$tipoAtual] ?? 'Usuário') : 'Novo Admin';
    $filtrosAtivos = collect($filtros ?? [])->filter(fn ($v) => $v !== null && $v !== '')->count();
    $indexParams = $tipoAtual ? ['tipo' => $tipoAtual] : [];
    $tipoBadge = [
        'admin' => ['label' => 'Admin', 'class' => 'bg-sky-100 text-sky-700'],
        'master' => ['label' => 'Master', 'class' => 'bg-purple-100 text-purple-700'],
        'marketplace' => ['label' => 'Marketplace', 'class' => 'bg-indigo-100 text-indigo-700'],
        'revenda' => ['label' => 'Revenda', 'class' => 'bg-emerald-100 text-emerald-700'],
    ];

    $subtituloNome = function ($usuario) use ($tipoAtual, $listaTodosUsuarios) {
        if ($listaTodosUsuarios ?? false) {
            return match ($usuario->tipo) {
                'admin' => 'Administrador da plataforma',
                'master' => $usuario->segmento ?: 'Master',
                'marketplace' => $usuario->hierarquia?->pai?->usuario?->nomeExibicao() ?: 'Sem master',
                'revenda' => ($pai = $usuario->hierarquia?->pai?->usuario) && $pai->tipo === 'marketplace'
                    ? $pai->nomeExibicao()
                    : 'Sem marketplace',
                default => $usuario->email,
            };
        }

        if ($tipoAtual === 'marketplace') {
            return $usuario->hierarquia?->pai?->usuario?->nomeExibicao() ?: 'Sem master';
        }
        if ($tipoAtual === 'revenda') {
            $pai = $usuario->hierarquia?->pai?->usuario;

            return ($pai && $pai->tipo === 'marketplace') ? $pai->nomeExibicao() : 'Sem marketplace';
        }
        if ($tipoAtual === 'master') {
            return $usuario->segmento ?: 'Sem segmento';
        }

        return $usuario->email;
    };
@endphp

@section('title', $tituloLista)

@section('content')
<div x-data="{ filtrosAberto: false }" class="space-y-6">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">{{ $tituloLista }} encontrados</h3>
                <p class="text-xs text-gray-400">{{ $usuarios->total() }} resultado(s)</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($tipoAtual || filled($filtros['nivel'] ?? null))
                    @php
                        $tipoCreate = $tipoAtual ?: ($filtros['nivel'] ?? null);
                        $paramsCreate = in_array($tipoCreate, ['master', 'marketplace', 'revenda'], true) ? ['tipo' => $tipoCreate] : [];
                        $rotuloCreate = $tipoCreate === 'admin' || $tipoCreate === null
                            ? 'Novo Admin'
                            : 'Novo '.($tipoSingular[$tipoCreate] ?? 'Usuário');
                    @endphp
                    <a href="{{ route('usuarios.create', $paramsCreate) }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">
                        <i class="fa-solid fa-plus text-xs"></i>
                        {{ $rotuloCreate }}
                    </a>
                @endif
                <button
                    type="button"
                    @click="filtrosAberto = true"
                    class="relative inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                >
                    <i class="fa-solid fa-filter"></i>
                    Filtros
                    @if ($filtrosAtivos > 0)
                        <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 text-[10px] font-bold text-white">{{ $filtrosAtivos }}</span>
                    @endif
                </button>
            </div>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Código</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nome</th>
                    @if ($listaTodosUsuarios ?? false || ! $tipoAtual)
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">E-mail</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nível</th>
                    @else
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Documento</th>
                    @endif
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $usuario)
                    <tr class="border-b border-gray-50 transition-colors hover:bg-gray-50">
                        <td class="px-5 py-4 font-semibold text-gray-800">#{{ str_pad($usuario->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-5 py-4">
                            <p class="font-semibold text-gray-800">{{ $usuario->nomeExibicao() }}</p>
                            <p class="text-xs text-gray-400">{{ $subtituloNome($usuario) }}</p>
                        </td>
                        @if ($listaTodosUsuarios ?? false || ! $tipoAtual)
                            <td class="px-5 py-4 text-gray-600">{{ $usuario->email }}</td>
                            <td class="px-5 py-4">
                                @if ($listaTodosUsuarios ?? false)
                                    @php $badge = $tipoBadge[$usuario->tipo] ?? ['label' => strtoupper($usuario->tipo), 'class' => 'bg-gray-100 text-gray-700']; @endphp
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                @else
                                    <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">ADMIN</span>
                                @endif
                            </td>
                        @else
                            <td class="px-5 py-4 text-gray-600">
                                {{ $usuario->tipo === 'admin' ? '—' : ($usuario->cnpj ?: $usuario->cpf ?: '—') }}
                            </td>
                        @endif
                        <td class="px-5 py-4">
                            @if ($usuario->ativo)
                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">Ativo</span>
                            @else
                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-600">Inativo</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a
                                href="{{ route('usuarios.show', $usuario) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"
                            >
                                <i class="fa-solid fa-circle-info text-blue-600"></i>
                                Detalhes
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($listaTodosUsuarios ?? false) || ! $tipoAtual ? 6 : 5 }}" class="px-5 py-10 text-center text-sm text-gray-500">Nenhum usuário encontrado para o filtro atual.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $usuarios->links() }}</div>

    @include('admin.usuarios.partials.filtros-drawer')
</div>
@endsection
