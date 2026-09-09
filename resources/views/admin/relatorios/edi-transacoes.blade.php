@extends('layouts.app')

@section('title', 'Transações EDI')

@section('content')
@php
    $valorTotal = (float) ($totais->valor_total ?? 0);
    $valorCancelado = (float) ($totais->valor_cancelado ?? 0);
    $valorFaturavel = $valorTotal - $valorCancelado;
    $qtdCanceladas = (int) ($totais->qtd_canceladas ?? 0);
    $qtdTransacoes = (int) ($totais->total_transacoes ?? 0);
    $pctCanceladas = $qtdTransacoes > 0 ? round(($qtdCanceladas / $qtdTransacoes) * 100, 2) : 0;
@endphp

<div class="mb-5 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Transações EDI</h1>
        <p class="mt-1 text-sm text-gray-500">Consulta mensal paginada dos movimentos EDI, com leitura de faturamento, cancelamentos e identificadores.</p>
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
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-12">
        <div class="space-y-1 xl:col-span-2">
            <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Competência</span>
            <div class="grid grid-cols-[1fr_88px] gap-2">
                <select name="mes_numero" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    @foreach ([1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'] as $numero => $nomeMes)
                        <option value="{{ $numero }}" @selected((int) $filtros['mes_numero'] === $numero)>{{ $nomeMes }}</option>
                    @endforeach
                </select>
                <input type="number" name="ano" value="{{ $filtros['ano'] }}" min="2020" max="2100" inputmode="numeric" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
            </div>
        </div>
        <label class="block space-y-1 xl:col-span-2">
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
        <label class="block space-y-1 xl:col-span-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Instituição</span>
            <select name="instituicao" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Todas</option>
                @foreach ($instituicoes as $codigo)
                    <option value="{{ $codigo }}" @selected($filtros['instituicao'] === $codigo)>{{ \App\Support\InstituicaoFinanceira::nome($codigo) }}</option>
                @endforeach
            </select>
        </label>
        <label class="block space-y-1 xl:col-span-4">
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

<div class="mb-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Transações</p>
            <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $transacoes->perPage() }}/pág.</span>
        </div>
        <p class="mt-3 text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($qtdTransacoes, 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-gray-500">Registros encontrados no filtro atual</p>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total EDI</p>
        <p class="mt-3 text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">R$ {{ number_format($valorTotal, 2, ',', '.') }}</p>
        <p class="mt-1 text-xs text-gray-500">Soma bruta dos movimentos exibidos</p>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Faturável</p>
        <p class="mt-3 text-2xl font-bold tabular-nums text-emerald-700">R$ {{ number_format($valorFaturavel, 2, ',', '.') }}</p>
        <p class="mt-1 text-xs text-gray-500">Total EDI menos cancelamentos</p>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Canceladas</p>
            <span class="rounded-full bg-red-50 px-2 py-1 text-xs font-bold text-red-700">{{ number_format($pctCanceladas, 2, ',', '.') }}%</span>
        </div>
        <p class="mt-3 text-2xl font-bold tabular-nums text-red-600">R$ {{ number_format($valorCancelado, 2, ',', '.') }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ number_format($qtdCanceladas, 0, ',', '.') }} transação(ões)</p>
    </div>
</div>

<div class="mb-5 grid gap-3 lg:grid-cols-2">
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Por status</h2>
            <span class="text-xs text-gray-400">Qtd. · Valor</span>
        </div>
        <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($porStatus as $item)
                <div class="flex items-center justify-between gap-3 py-2 text-sm">
                    <span class="font-medium text-gray-600 dark:text-gray-300">{{ $item['label'] }}</span>
                    <span class="text-right tabular-nums font-semibold text-gray-800 dark:text-gray-100">{{ number_format($item['quantidade'], 0, ',', '.') }} · R$ {{ number_format($item['total'], 2, ',', '.') }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sem movimentos no período.</p>
            @endforelse
        </div>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Por tipo</h2>
            <span class="text-xs text-gray-400">Qtd. · Valor</span>
        </div>
        <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($porTipo as $item)
                <div class="flex items-center justify-between gap-3 py-2 text-sm">
                    <span class="capitalize font-medium text-gray-600 dark:text-gray-300">{{ $item['tipo'] }}</span>
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
        <div>
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Movimentos</h2>
            <p class="mt-0.5 text-xs text-gray-500">{{ \Carbon\Carbon::parse($filtros['inicio'])->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($filtros['fim'])->format('d/m/Y') }}</p>
        </div>
        <p class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ number_format($transacoes->total(), 0, ',', '.') }} resultado(s)</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1760px] text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Quando</th>
                    <th class="px-4 py-3">Estabelecimento</th>
                    <th class="px-4 py-3">Token</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Pagamento</th>
                    <th class="px-4 py-3 text-right">Valores</th>
                    <th class="px-4 py-3">Identificadores</th>
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
                        $statusClass = match ((string) $tx->status_pagamento) {
                            '04', '4' => 'bg-red-100 text-red-700 ring-red-200',
                            '03', '3' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                            '02', '2' => 'bg-amber-100 text-amber-700 ring-amber-200',
                            '01', '1' => 'bg-blue-100 text-blue-700 ring-blue-200',
                            default => 'bg-gray-100 text-gray-700 ring-gray-200',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $tx->data_inicial_transacao ? \Carbon\Carbon::parse($tx->data_inicial_transacao)->format('d/m/Y') : '—' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ $tx->hora_inicial_transacao ?: 'sem hora' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="max-w-80 truncate font-semibold text-gray-900 dark:text-gray-100" title="{{ $nome }}">{{ $nome }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ $tx->cnpj ?: $tx->cpf ?: 'sem documento' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-600">{{ $tx->estabelecimento ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $statusClass }}">{{ $statusLabel }}</span>
                            <div class="mt-1 font-mono text-xs text-gray-400">{{ $tx->status_pagamento ?: 'sem código' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="capitalize font-semibold text-gray-800 dark:text-gray-100">{{ $tx->tipo_transacao ?: 'Sem tipo' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ $tx->instituicao_financeira ? \App\Support\InstituicaoFinanceira::nome($tx->instituicao_financeira) : 'Sem instituição' }}</div>
                            <div class="mt-0.5 text-xs text-gray-400">Parcela {{ $tx->parcela ?: '—' }}/{{ $tx->quantidade_parcela ?: '—' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <div class="tabular-nums font-bold text-gray-900 dark:text-gray-100">R$ {{ number_format((float) $tx->valor_total_transacao, 2, ',', '.') }}</div>
                            <div class="mt-0.5 tabular-nums text-xs text-gray-500">Líquido R$ {{ number_format((float) $tx->valor_liquido_transacao, 2, ',', '.') }}</div>
                            <div class="mt-0.5 tabular-nums text-xs text-gray-400">Original R$ {{ number_format((float) $tx->valor_original_transacao, 2, ',', '.') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="grid grid-cols-[88px_1fr] gap-x-2 gap-y-1 text-xs">
                                <span class="text-gray-400">NSU</span>
                                <span class="font-mono text-gray-700 dark:text-gray-200">{{ $tx->nsu ?: '—' }}</span>
                                <span class="text-gray-400">tx_id</span>
                                <span class="max-w-64 truncate font-mono text-gray-700 dark:text-gray-200" title="{{ $tx->tx_id }}">{{ $tx->tx_id ?: '—' }}</span>
                                <span class="text-gray-400">API</span>
                                <span class="max-w-64 truncate font-mono text-gray-700 dark:text-gray-200" title="{{ $tx->movimento_api_codigo }}">{{ $tx->movimento_api_codigo }}</span>
                                <span class="text-gray-400">Venda</span>
                                <span class="max-w-64 truncate font-mono text-gray-700 dark:text-gray-200" title="{{ $tx->codigo_venda }}">{{ $tx->codigo_venda ?: '—' }}</span>
                                <span class="text-gray-400">Autoriz.</span>
                                <span class="font-mono text-gray-700 dark:text-gray-200">{{ $tx->codigo_autorizacao ?: '—' }}</span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-600">{{ $tx->num_logico ?: $tx->numero_serie_leitor ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-500">Nenhuma transação EDI nesse filtro.</td>
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
