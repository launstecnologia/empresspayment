@extends('layouts.app')

@section('title', 'KYC — Fila de análise')

@section('content')
@php
    $filtrosAtivos = collect($filtros ?? [])->filter(fn ($v) => $v !== null && $v !== '')->count();
@endphp

<div x-data="{ filtrosAberto: false }" class="space-y-6">
    <div class="flex flex-wrap gap-3">
        <span class="rounded-lg bg-yellow-50 px-3 py-2 text-xs font-semibold text-yellow-800">
            Em análise: {{ $contagens['em_analise'] ?? 0 }}
        </span>
        <span class="rounded-lg bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-800">
            Revisão manual: {{ $contagens['revisao_manual'] ?? 0 }}
        </span>
        <span class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
            Reprovados: {{ $contagens['reprovado'] ?? 0 }}
        </span>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">KYC — Fila de análise</h3>
                <p class="text-xs text-gray-400">{{ $kycs->total() }} resultado(s)</p>
            </div>
            <button
                type="button"
                @click="filtrosAberto = true"
                class="relative inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
                <i class="fa-solid fa-filter"></i>
                Filtros
                @if ($filtrosAtivos > 0)
                    <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 text-[10px] font-bold text-white">{{ $filtrosAtivos }}</span>
                @endif
            </button>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">#</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Estabelecimento</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status KYC</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Atualizado</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($kycs as $item)
                    @php
                        $estab = $item->estabelecimentoCompleto;
                        $nomeEstab = $estab
                            ? ($estab->nome_fantasia ?: $estab->razao_social ?: $estab->nome_completo ?: 'Estabelecimento #'.$item->estabelecimento_id)
                            : 'Estabelecimento removido #'.$item->estabelecimento_id;
                        $statusClass = match ($item->status) {
                            'aprovado' => 'bg-green-100 text-green-700',
                            'reprovado' => 'bg-red-100 text-red-700',
                            'em_analise', 'revisao_manual' => 'bg-yellow-100 text-yellow-700',
                            default => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-5 py-4 font-semibold text-gray-800">#{{ str_pad($item->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-5 py-4">
                            <p class="font-semibold text-gray-800">{{ $nomeEstab }}</p>
                            <p class="text-xs text-gray-400">
                                @if ($estab)
                                    {{ $estab->marketplace?->nomeExibicao() ?: '—' }}
                                    @if (! $estab->ativo)
                                        <span class="ml-1 rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-600">Inativo</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </p>
                        </td>
                        <td class="px-5 py-4 text-gray-600">{{ $estab?->pessoa_tipo === 'fisica' ? 'PF' : ($estab ? 'PJ' : '—') }}</td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">{{ str_replace('_', ' ', ucfirst($item->status)) }}</span>
                        </td>
                        <td class="px-5 py-4 text-gray-600">{{ $item->updated_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.kyc.show', $item) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                                <i class="fa-solid fa-circle-info text-blue-600"></i>
                                Detalhes
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">Nenhum KYC na fila.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $kycs->links() }}</div>

    <div x-show="filtrosAberto" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="filtrosAberto = false">
        <div class="absolute inset-0 bg-gray-900/50" @click="filtrosAberto = false"></div>
        <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl" @click.stop>
            <div class="flex items-center justify-between border-b px-5 py-4">
                <h3 class="text-lg font-semibold text-gray-800">Filtros</h3>
                <button type="button" @click="filtrosAberto = false"><i class="fa-solid fa-xmark text-gray-400"></i></button>
            </div>
            <form method="GET" action="{{ route('admin.kyc.index') }}" class="flex flex-1 flex-col">
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Busca</label>
                        <input type="text" name="busca" value="{{ $filtros['busca'] ?? '' }}" placeholder="Nome ou documento..." class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Status</label>
                        <select name="status" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
                            <option value="">Todos</option>
                            @foreach (['pendente', 'em_analise', 'revisao_manual', 'aprovado', 'reprovado'] as $st)
                                <option value="{{ $st }}" @selected(($filtros['status'] ?? '') === $st)>{{ str_replace('_', ' ', ucfirst($st)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 border-t px-5 py-4">
                    <a href="{{ route('admin.kyc.index') }}" class="flex-1 rounded-lg border px-4 py-2.5 text-center text-sm font-semibold text-gray-600">Limpar</a>
                    <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Aplicar</button>
                </div>
            </form>
        </aside>
    </div>
</div>
@endsection
