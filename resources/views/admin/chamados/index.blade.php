@extends('layouts.app')

@section('title', 'Chamados')

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

<div class="mb-5 grid gap-3 md:grid-cols-5">
    @foreach ($statusLabel as $status => $label)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold text-gray-800">{{ $contadores[$status] ?? 0 }}</p>
        </div>
    @endforeach
</div>

<form class="mb-5 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-6">
    <input name="busca" value="{{ request('busca') }}" placeholder="Número ou título..." class="rounded-lg border border-gray-200 px-3 py-2 text-sm md:col-span-2">
    <select name="status" class="rounded-lg border border-gray-200 bg-white px-3 text-sm">
        <option value="">Status</option>
        @foreach ($statusLabel as $status => $label)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="prioridade" class="rounded-lg border border-gray-200 bg-white px-3 text-sm">
        <option value="">Prioridade</option>
        @foreach (['baixa', 'media', 'alta', 'urgente'] as $prioridade)
            <option value="{{ $prioridade }}" @selected(request('prioridade') === $prioridade)>{{ ucfirst($prioridade) }}</option>
        @endforeach
    </select>
    <select name="nivel" class="rounded-lg border border-gray-200 bg-white px-3 text-sm">
        <option value="">Nível</option>
        @foreach (['master', 'marketplace', 'revenda'] as $nivel)
            <option value="{{ $nivel }}" @selected(request('nivel') === $nivel)>{{ ucfirst($nivel) }}</option>
        @endforeach
    </select>
    <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Filtrar</button>
</form>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-5 py-3">Número</th>
                <th class="px-5 py-3">Título</th>
                <th class="px-5 py-3">Nível</th>
                <th class="px-5 py-3">Prioridade</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Atualizado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($chamados as $chamado)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-4 font-bold text-blue-600"><a href="{{ route('admin.chamados.show', $chamado->numero) }}">{{ $chamado->numero }}</a></td>
                    <td class="px-5 py-4">
                        <p class="font-semibold text-gray-800">{{ $chamado->titulo }}</p>
                        <p class="text-xs text-gray-400">{{ ucfirst($chamado->categoria) }}</p>
                    </td>
                    <td class="px-5 py-4 text-gray-600">{{ ucfirst($chamado->aberto_por_nivel) }}</td>
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
