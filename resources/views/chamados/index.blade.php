@extends('layouts.app')

@section('title', 'Meus Chamados')

@section('content')
@php
    $statusLabel = [
        'aberto' => 'Aberto',
        'em_atendimento' => 'Em atendimento',
        'aguardando_cliente' => 'Aguardando cliente',
        'resolvido' => 'Resolvido',
        'fechado' => 'Fechado',
    ];
@endphp

<div class="mb-5 flex flex-wrap items-center gap-3">
    <form class="flex flex-1 flex-wrap gap-2">
        <select name="status" class="rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700">
            <option value="">Todos os status</option>
            @foreach ($statusLabel as $status => $label)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="categoria" class="rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700">
            <option value="">Todas categorias</option>
            @foreach (['financeiro', 'tecnico', 'comercial', 'cadastro', 'integracao', 'outro'] as $categoria)
                <option value="{{ $categoria }}" @selected(request('categoria') === $categoria)>{{ ucfirst($categoria) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Filtrar</button>
    </form>
    <a href="{{ route('chamados.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">+ Novo Chamado</a>
</div>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-5 py-3">Número</th>
                <th class="px-5 py-3">Título</th>
                <th class="px-5 py-3">Categoria</th>
                <th class="px-5 py-3">Prioridade</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Atualizado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($chamados as $chamado)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-4 font-bold text-blue-600"><a href="{{ route('chamados.show', $chamado->numero) }}">{{ $chamado->numero }}</a></td>
                    <td class="px-5 py-4 font-semibold text-gray-800">{{ $chamado->titulo }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ ucfirst($chamado->categoria) }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ ucfirst($chamado->prioridade) }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $statusLabel[$chamado->status] ?? $chamado->status }}</td>
                    <td class="px-5 py-4 text-gray-500">{{ $chamado->updated_at->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-gray-400">Nenhum chamado encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $chamados->links() }}</div>
@endsection
