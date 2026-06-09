@extends('layouts.app')

@section('title', 'Planos')

@section('content')
<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-sm font-semibold text-gray-700">Planos e taxas originais</h2>
        <p class="text-xs text-gray-400">Consulta das taxas cadastradas na plataforma.</p>
    </div>
    @if ($podeGerirPlanos)
        <a href="{{ route('planos.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">+ Novo Plano</a>
    @endif
</div>

<div class="grid gap-4">
    @foreach ($planos as $plano)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">◫</div>
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ $plano->nome }}</h3>
                        <p class="text-sm text-gray-400">{{ $plano->descricao ?: 'Sem descrição' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $plano->ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $plano->ativo ? 'ATIVO' : 'INATIVO' }}</span>
                    <a href="{{ route('planos.show', $plano) }}" class="text-sm font-semibold text-sky-600 hover:text-sky-700">Taxas</a>
                    @if ($podeGerirPlanos)
                        <a href="{{ route('planos.edit', $plano) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Editar</a>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="mt-6">{{ $planos->links() }}</div>
@endsection
