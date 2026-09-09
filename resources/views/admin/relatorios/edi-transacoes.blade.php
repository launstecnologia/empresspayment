@extends('layouts.app')

@section('title', 'Transações EDI')

@section('content')
@php
    $valorTotal = (float) ($totais->valor_total ?? 0);
    $valorCancelado = (float) ($totais->valor_cancelado ?? 0);
    $valorFaturavel = $valorTotal - $valorCancelado;
@endphp

<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Transações EDI</h1>
        <p class="mt-1 text-sm text-gray-500">Consulta mensal paginada direto nos movimentos EDI, incluindo faturáveis, canceladas e dados de identificação.</p>
    </div>
    <a href="{{ route('admin.configuracoes.edit', ['aba' => 'edi']) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
        <i class="fa-solid fa-arrows-rotate"></i>
        Reprocessar EDI
    </a>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ $errors->first() }}
    </div>
@endif

<form method="GET" action="{{ route('admin.relatorios.edi-transacoes') }}" class="mb-5 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
        <label class="block space-y-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Mês</span>
            <input type="month" name="mes" value="{{ $filtros['mes'] }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </label>
        <label class="block space-y-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</span>
            <select name="status" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                @foreach ($statusOptions as $valor => $label)
                    <option value="{{ $valor }}" @selected($filtros['status'] === $valor)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block space-y-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</span>
            <select name="tipo_transacao" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Todos</option>
                <option value="debito" @selected($filtros['tipo_transacao'] === 'debito')>Débito</option>
                <option value="credito" @selected($filtros['tipo_transacao'] === 'credito')>Crédito</option>
                <option value="pix" @selected($filtros['tipo_transacao'] === 'pix')>Pix</option>
            </select>
        </label>
        <label class="block space-y-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Instituição</span>
            <select name="instituicao" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Todas</option>
                @foreach ($instituicoes as $codigo)
                    <option value="{{ $codigo }}" @selected($filtros['instituicao'] === $codigo)>{{ \App\Support\InstituicaoFinanceira::nome($codigo) }}</option>
                @endforeach
            </select>
        </label>
        <label class="block space-y-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Busca</span>
            <input type="search" name="busca" value="{{ $filtros['busca'] }}" placeholder="Token, NSU, tx_id, nome..." class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </label>
        <label class="block space-y-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Por página</span>
            <select name="por_pagina" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                @foreach ([50, 100, 200] as $qtd)
                    <option value="{{ $qtd }}" @selected((int) $filtros['por_pagina'] === $qtd)>{{ $qtd }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
            <i class="fa-solid fa-magnifying-glass"></i>
            Consultar
        </button>
        <a href="{{ route('admin.relatorios.edi-transacoes') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
            <i class="fa-solid fa-rotate-left"></i>
            Limpar
        </a>
    </div>
</form>

<div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs font-medium text-gray-500">Transações</p>
        <p class="mt-1 text-xl font-bold tabular-nums text-gray-800 dark:text-gray-100">{{ number_format((int) ($totais->total_transacoes ?? 0), 0, ',', '.') }}</p>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs font-medium text-gray-500">Total EDI</p>
        <p class="mt-1 text-xl font-bold tabular-nums text-gray-800 dark:text-gray-100">R$ {{ number_format($valorTotal, 2, ',', '.') }}</p>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs font-medium text-gray-500">Faturável</p>
        <p class="mt-1 text-xl font-bold tabular-nums text-emerald-700">R$ {{ number_format($valorFaturavel, 2, ',', '.') }}</p>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs font-medium text-gray-500">Canceladas</p>
        <p class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ number_format((int) ($totais->qtd_canceladas ?? 0), 0, ',', '.') }} · R$ {{ number_format($valorCancelado, 2, ',', '.') }}</p>
    </div>
</div>

<div class="mb-5 grid gap-3 lg:grid-cols-2">
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Por status</h2>
        <div class="mt-3 space-y-2">
            @forelse ($porStatus as $item)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">{{ $item['label'] }}</span>
                    <span class="text-right tabular-nums font-semibold text-gray-800 dark:text-gray-100">{{ number_format($item['quantidade'], 0, ',', '.') }} · R$ {{ number_format($item['total'], 2, ',', '.') }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sem movimentos no período.</p>
            @endforelse
        </div>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Por tipo</h2>
        <div class="mt-3 space-y-2">
            @forelse ($porTipo as $item)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="capitalize text-gray-600 dark:text-gray-300">{{ $item['tipo'] }}</span>
                    <span class="text-right tabular-nums font-semibold text-gray-800 dark:text-gray-100">{{ number_format($item['quantidade'], 0, ',', '.') }} · R$ {{ number_format($item['total'], 2, ',', '.') }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sem movimentos no período.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <p class="text-sm text-gray-500">Período: {{ \Carbon\Carbon::parse($filtros['inicio'])->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($filtros['fim'])->format('d/m/Y') }}</p>
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ number_format($transacoes->total(), 0, ',', '.') }} resultado(s)</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1700px] text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3">Estabelecimento</th>
                    <th class="px-4 py-3">Token</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Instituição</th>
                    <th class="px-4 py-3 text-right">Valor</th>
                    <th class="px-4 py-3 text-right">Líquido</th>
                    <th class="px-4 py-3">Parcela</th>
                    <th class="px-4 py-3">NSU</th>
                    <th class="px-4 py-3">Tx ID</th>
                    <th class="px-4 py-3">Movimento API</th>
                    <th class="px-4 py-3">Autorização</th>
                    <th class="px-4 py-3">Terminal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($transacoes as $tx)
                    @php
                        $nome = $tx->nome_fantasia ?: $tx->razao_social ?: $tx->nome_completo ?: 'Sem cadastro vinculado';
                        $statusLabel = $statusOptions[$tx->status_pagamento] ?? match ((string) $tx->status_pagamento) {
                            '1' => 'Novo (1)',
                            '2' => 'Agendado (2)',
                            '3' => 'Concluído (3)',
                            '4' => 'Cancelado (4)',
                            '', null => 'Sem status',
                            default => 'Status '.$tx->status_pagamento,
                        };
                        $statusClass = in_array((string) $tx->status_pagamento, ['04', '4'], true)
                            ? 'bg-red-100 text-red-700'
                            : 'bg-emerald-100 text-emerald-700';
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800 dark:text-gray-100">{{ $tx->data_inicial_transacao ? \Carbon\Carbon::parse($tx->data_inicial_transacao)->format('d/m/Y') : '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $tx->hora_inicial_transacao ?: '—' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="max-w-64 truncate font-semibold text-gray-800 dark:text-gray-100" title="{{ $nome }}">{{ $nome }}</div>
                            <div class="text-xs text-gray-500">{{ $tx->cnpj ?: $tx->cpf ?: '—' }}</div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $tx->estabelecimento ?: '—' }}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td class="px-4 py-3 capitalize text-gray-700 dark:text-gray-200">{{ $tx->tipo_transacao ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $tx->instituicao_financeira ? \App\Support\InstituicaoFinanceira::nome($tx->instituicao_financeira) : '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-800 dark:text-gray-100">R$ {{ number_format((float) $tx->valor_total_transacao, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">R$ {{ number_format((float) $tx->valor_liquido_transacao, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $tx->parcela ?: '—' }}/{{ $tx->quantidade_parcela ?: '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $tx->nsu ?: '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $tx->tx_id ?: '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $tx->movimento_api_codigo }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $tx->codigo_autorizacao ?: '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $tx->num_logico ?: $tx->numero_serie_leitor ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="px-4 py-10 text-center text-gray-500">Nenhuma transação EDI nesse filtro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
        {{ $transacoes->links() }}
    </div>
</div>
@endsection
