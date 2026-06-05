@extends('layouts.app')

@section('title', 'Segmentos')

@section('content')
<div class="mb-5 flex items-center justify-between gap-3">
    <div>
        <h2 class="text-sm font-semibold text-gray-700">Segmentos cadastrados</h2>
        <p class="text-xs text-gray-400">{{ $segmentos->total() }} resultado(s)</p>
    </div>
    <a href="{{ route('segmentos.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">+ Novo Segmento</a>
</div>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Código</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nome</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Descrição</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($segmentos as $segmento)
                <tr class="border-b border-gray-50 transition-colors hover:bg-gray-50">
                    <td class="px-5 py-4 font-semibold text-gray-800">#{{ str_pad($segmento->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td class="px-5 py-4 font-semibold text-gray-800">{{ $segmento->nome }}</td>
                    <td class="px-5 py-4 text-gray-500">{{ $segmento->descricao ?: '-' }}</td>
                    <td class="px-5 py-4">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $segmento->ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $segmento->ativo ? 'Ativo' : 'Inativo' }}</span>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('segmentos.edit', $segmento) }}" class="text-blue-500 hover:text-blue-700" title="Editar">✎</a>
                            <form method="POST" action="{{ route('segmentos.destroy', $segmento) }}" onsubmit="return confirm('Remover este segmento?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-500 hover:text-red-600" title="Excluir">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">Nenhum segmento cadastrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $segmentos->links() }}</div>
@endsection
