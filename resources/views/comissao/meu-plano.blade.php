@extends('layouts.app')

@section('title', 'Planos e Taxas')

@section('content')
@php
    $fmt = fn ($valor) => $valor !== null && $valor !== '' ? number_format((float) $valor, 2, ',', '.').'%' : '—';
    $cellClass = 'px-3 py-2.5 text-right tabular-nums text-gray-700';
    $headClass = 'px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500';
@endphp

<div class="mb-5">
    <h2 class="text-sm font-semibold text-gray-700">Planos e taxas disponíveis</h2>
    <p class="text-xs text-gray-400">Visualização das taxas do plano e da sua comissão. Somente leitura.</p>
</div>

@if ($planos->isEmpty())
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-8 text-center text-sm text-amber-800">
        Nenhum plano foi habilitado para o seu marketplace. Entre em contato com o administrador.
    </div>
@else
    <form method="GET" action="{{ route('comissoes.meu-plano') }}" class="mb-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <label class="block space-y-1">
            <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Selecione o plano</span>
            <select name="plano" onchange="this.form.submit()" class="w-full max-w-md rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                @foreach ($planos as $item)
                    <option value="{{ $item->id }}" @selected($plano && (int) $plano->id === (int) $item->id)>{{ $item->nome }}</option>
                @endforeach
            </select>
        </label>
        @if ($plano?->descricao)
            <p class="mt-3 text-sm text-gray-500">{{ $plano->descricao }}</p>
        @endif
    </form>

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div class="flex gap-2">
                <button type="button" data-tab-button="debito" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white">Débito</button>
                <button type="button" data-tab-button="credito" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-bold text-gray-600">Crédito</button>
                <button type="button" data-tab-button="pix" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-bold text-gray-600">PIX</button>
            </div>
            <p class="text-xs text-gray-400">Taxa = cobrada do estabelecimento · Minha comissão = valor que fica com você</p>
        </div>

        <div data-tab-panel="debito" class="p-5">
            @php
                $debitoAtivo = collect($grade['debito'])->contains(fn ($linha) => ($linha['ativo'] ?? false) && ($linha['existe'] ?? false));
            @endphp
            <h3 class="mb-4 text-sm font-bold text-gray-700">Débito · Recebimento D+1</h3>
            @if (! $debitoAtivo)
                <p class="text-sm text-gray-400">Nenhuma taxa de débito ativa neste plano.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left">
                                <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Parcelas</th>
                                @foreach ($debitoGrupos as $config)
                                    <th class="{{ $headClass }}">{{ $config['label'] }} · Taxa</th>
                                @endforeach
                                <th class="{{ $headClass }}">Minha comissão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2.5 font-semibold text-gray-800">1x</td>
                                @php
                                    $debitoMinhaComissao = collect($grade['debito'])->first(fn ($linha) => filled($linha['minha_comissao'] ?? null))['minha_comissao'] ?? null;
                                @endphp
                                @foreach ($debitoGrupos as $grupo => $config)
                                    @php $linha = $grade['debito'][$grupo]; @endphp
                                    <td class="{{ $cellClass }}">{{ ($linha['ativo'] ?? false) ? $fmt($linha['taxa']) : '—' }}</td>
                                @endforeach
                                <td class="{{ $cellClass }} font-semibold text-blue-600">{{ $debitoMinhaComissao !== null ? $fmt($debitoMinhaComissao) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div data-tab-panel="credito" class="hidden p-5">
            <h3 class="mb-4 text-sm font-bold text-gray-700">Crédito · 1x até 18x</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Parcelas</th>
                            @foreach ($creditoGrupos as $config)
                                <th class="{{ $headClass }}">{{ $config['label'] }} · Taxa</th>
                            @endforeach
                            <th class="{{ $headClass }}">Minha comissão</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($parcelas = 1; $parcelas <= 18; $parcelas++)
                            @php
                                $parcelaAtiva = collect($grade['credito'][$parcelas])->contains(fn ($linha) => ($linha['ativo'] ?? false) && ($linha['existe'] ?? false));
                                $creditoMinhaComissao = collect($grade['credito'][$parcelas])->first(fn ($linha) => filled($linha['minha_comissao'] ?? null))['minha_comissao'] ?? null;
                            @endphp
                            @continue(! $parcelaAtiva)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2.5 font-semibold text-gray-800">{{ $parcelas }}x</td>
                                @foreach ($creditoGrupos as $grupo => $config)
                                    @php $linha = $grade['credito'][$parcelas][$grupo]; @endphp
                                    <td class="{{ $cellClass }}">{{ ($linha['ativo'] ?? false) ? $fmt($linha['taxa']) : '—' }}</td>
                                @endforeach
                                <td class="{{ $cellClass }} font-semibold text-blue-600">{{ $creditoMinhaComissao !== null ? $fmt($creditoMinhaComissao) : '—' }}</td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>

        <div data-tab-panel="pix" class="hidden p-5">
            @php $linha = $grade['pix']['bacen']; @endphp
            <h3 class="mb-4 text-sm font-bold text-gray-700">PIX · BACEN</h3>
            @if (! (($linha['ativo'] ?? false) && ($linha['existe'] ?? false)))
                <p class="text-sm text-gray-400">Nenhuma taxa PIX ativa neste plano.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left">
                                <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Instituição</th>
                                <th class="{{ $headClass }}">Taxa</th>
                                <th class="{{ $headClass }}">Minha comissão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2.5 font-semibold text-gray-800">BACEN · 1x</td>
                                <td class="{{ $cellClass }}">{{ $fmt($linha['taxa']) }}</td>
                                <td class="{{ $cellClass }} font-semibold text-blue-600">{{ $fmt($linha['minha_comissao'] ?? null) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Sua comissão é calculada sobre a taxa repassada pelo nível acima, descontando o que você repassa para revendas.
    </div>
@endif
@endsection

@section('scripts')
<script>
    (() => {
        const buttons = document.querySelectorAll('[data-tab-button]');
        const panels = document.querySelectorAll('[data-tab-panel]');

        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                buttons.forEach((item) => {
                    const active = item.dataset.tabButton === button.dataset.tabButton;
                    item.classList.toggle('bg-blue-600', active);
                    item.classList.toggle('text-white', active);
                    item.classList.toggle('bg-gray-100', !active);
                    item.classList.toggle('text-gray-600', !active);
                });

                panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.tabPanel !== button.dataset.tabButton));
            });
        });
    })();
</script>
@endsection
