@extends('layouts.app')

@section('title', 'Configurar Comissão')

@section('content')
<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-sm font-semibold text-gray-700">Repasses configurados</h2>
        <p class="text-xs text-gray-400">Percentual que cada nível repassa para o nível imediatamente abaixo.</p>
    </div>
    <a href="{{ route('comissoes.configuracoes.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">+ Nova Configuração</a>
</div>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Plano</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Taxa</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Responsável</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nível</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Repassa</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($configuracoes as $configuracao)
                <tr class="border-b border-gray-50 transition-colors hover:bg-gray-50">
                    <td class="px-5 py-4 font-semibold text-gray-800">{{ $configuracao->taxa?->plano?->nome }}</td>
                    <td class="px-5 py-4 text-gray-600">
                        {{ $configuracao->taxa?->instituicao }} · {{ ucfirst($configuracao->taxa?->tipo_transacao) }} · {{ $configuracao->taxa?->parcelas }}x
                    </td>
                    <td class="px-5 py-4">
                        <p class="font-semibold text-gray-800">{{ $configuracao->usuario?->nomeExibicao() }}</p>
                        <p class="text-xs text-gray-400">{{ $configuracao->usuario?->email }}</p>
                    </td>
                    <td class="px-5 py-4"><span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ strtoupper($configuracao->nivel) }}</span></td>
                    <td class="px-5 py-4 font-semibold text-blue-600">{{ number_format($configuracao->percentual, 2, ',', '.') }}%</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('comissoes.configuracoes.edit', $configuracao) }}" class="text-blue-500 hover:text-blue-700" title="Editar">✎</a>
                            <form method="POST" action="{{ route('comissoes.configuracoes.destroy', $configuracao) }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-500 hover:text-red-600" title="Excluir">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">Nenhuma configuração de comissão cadastrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $configuracoes->links() }}</div>
@endsection
