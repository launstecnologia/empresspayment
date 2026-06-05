@extends('layouts.app')

@section('title', 'Comissões')

@section('content')
@php
    $totalFaturamento = $linhas->sum('total_faturamento');
    $totalComissao = $linhas->sum('total_comissao');
@endphp

<div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <p class="mb-1 text-xs font-medium text-gray-500">Faturamento (página)</p>
        <span class="text-2xl font-bold text-green-600">R$ {{ number_format($totalFaturamento, 2, ',', '.') }}</span>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <p class="mb-1 text-xs font-medium text-gray-500">Comissões (página)</p>
        <span class="text-2xl font-bold text-sky-600">R$ {{ number_format($totalComissao, 2, ',', '.') }}</span>
    </div>
</div>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-700">Extrato de comissões</h3>
            <p class="text-xs text-gray-400">{{ $linhas->total() }} resultado(s) · resumo por marketplace e período</p>
        </div>
        <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Exportar</button>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Marketplace</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Período</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Faturamento</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Comissão</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $linha)
                <tr class="border-b border-gray-50 transition-colors hover:bg-gray-50">
                    <td class="px-5 py-4">
                        <p class="font-semibold text-gray-800">{{ $linha->marketplace_nome }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $linha->periodo }}</span>
                    </td>
                    <td class="px-5 py-4 font-semibold text-green-600">R$ {{ number_format($linha->total_faturamento, 2, ',', '.') }}</td>
                    <td class="px-5 py-4 font-semibold text-sky-600">R$ {{ number_format($linha->total_comissao, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500">Nenhuma comissão encontrada no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $linhas->links() }}</div>
@endsection
