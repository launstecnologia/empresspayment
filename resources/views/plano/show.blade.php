@extends('layouts.app')

@section('title', 'Grade de Taxas do Plano')

@section('content')
@php
    $inputClass = 'w-full rounded border border-gray-200 bg-white px-2 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
    $taxaInput = $inputClass.' text-right';
    $percentInput = 'w-24 rounded border border-gray-200 bg-white px-2 py-2 text-right text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
@endphp

<form method="POST" action="{{ route('planos.grade-taxas.salvar', $plano) }}" data-grade-form class="space-y-5">
    @csrf

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 xl:grid-cols-[1fr_2fr_auto] xl:items-end">
            <label class="space-y-1">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Plano</span>
                <input value="{{ $plano->nome }}" readonly class="{{ $inputClass }} bg-gray-50 font-semibold">
            </label>
            <label class="space-y-1">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Descrição</span>
                <input value="{{ $plano->descricao }}" readonly class="{{ $inputClass }} bg-gray-50">
            </label>
            <a href="{{ route('planos.edit', $plano) }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50">Editar Plano</a>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div class="flex gap-2">
                <button type="button" data-tab-button="debito" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white">Débito</button>
                <button type="button" data-tab-button="credito" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-bold text-gray-600">Crédito</button>
                <button type="button" data-tab-button="pix" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-bold text-gray-600">PIX</button>
            </div>
            <div class="text-xs text-gray-400">Arranjo UR e meio de pagamento são gerados automaticamente.</div>
        </div>

        <div data-tab-panel="debito" class="p-5">
            @php
                $debitoComissao = collect($grade['debito'])->pluck('comissao')->filter(fn ($valor) => $valor !== null && $valor !== '')->first();
                $debitoAtivo = collect($grade['debito'])->contains(fn ($linha) => ($linha['ativo'] ?? false) && ($linha['existe'] ?? false));
            @endphp
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-gray-700">Débito · Recebimento D+1</h3>
                <p class="text-xs text-gray-400">Outros engloba Banricompras e Cabal.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[920px] text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Parcelas</th>
                            @foreach ($debitoGrupos as $config)
                                <th class="px-3 py-3">{{ $config['label'] }} (%)</th>
                            @endforeach
                            <th class="px-3 py-3">Comissão Admin (%)</th>
                            <th class="px-3 py-3 text-center">Ativo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-3 font-semibold text-gray-800">1x</td>
                            @foreach ($debitoGrupos as $grupo => $config)
                                @php
                                    $linha = $grade['debito'][$grupo];
                                @endphp
                                <td class="px-3 py-3">
                                    <input data-taxa-input data-debito-taxa="{{ $grupo }}" name="grade[debito][{{ $grupo }}][taxa]" value="{{ $linha['taxa'] }}" type="number" step="0.01" min="0" max="100" class="{{ $percentInput }}" title="{{ $linha['arranjo'] }}">
                                </td>
                            @endforeach
                            <td class="px-3 py-3"><input data-comissao-input name="grade[debito][comissao]" value="{{ $debitoComissao }}" type="number" step="0.01" min="0" max="100" class="{{ $percentInput }}"></td>
                            <td class="px-3 py-3 text-center">
                                <input type="hidden" name="grade[debito][ativo]" value="0">
                                <input type="checkbox" name="grade[debito][ativo]" value="1" @checked($debitoAtivo) class="h-5 w-5 rounded accent-blue-600">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div data-tab-panel="credito" class="hidden p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-gray-700">Crédito · 1x até 18x</h3>
                <p class="text-xs text-gray-400">Use “Replicar” para preencher as bandeiras vazias à direita na mesma parcela.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Parcelas</th>
                            @foreach ($creditoGrupos as $grupo => $config)
                                <th class="px-3 py-3">{{ $config['label'] }} (%)</th>
                            @endforeach
                            <th class="px-3 py-3">Comissão</th>
                            <th class="px-3 py-3 text-center">Ativo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($parcelas = 1; $parcelas <= 18; $parcelas++)
                            @php
                                $creditoComissao = collect($grade['credito'][$parcelas])->pluck('comissao')->filter(fn ($valor) => $valor !== null && $valor !== '')->first();
                                $creditoAtivo = collect($grade['credito'][$parcelas])->contains(fn ($linha) => ($linha['ativo'] ?? false) && ($linha['existe'] ?? false));
                            @endphp
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 font-semibold text-gray-800">
                                    {{ $parcelas }}x
                                    <button type="button" data-replicar-parcela="{{ $parcelas }}" class="ml-2 rounded bg-gray-100 px-2 py-1 text-[11px] font-bold text-gray-500 hover:bg-blue-50 hover:text-blue-600">Replicar</button>
                                </td>
                                @foreach ($creditoGrupos as $grupo => $config)
                                    @php
                                        $linha = $grade['credito'][$parcelas][$grupo];
                                    @endphp
                                    <td class="px-3 py-2">
                                        <input data-taxa-input data-credito-taxa="{{ $grupo }}" data-parcela="{{ $parcelas }}" name="grade[credito][{{ $parcelas }}][{{ $grupo }}][taxa]" value="{{ $linha['taxa'] }}" type="number" step="0.01" min="0" max="100" class="{{ $percentInput }}" title="{{ $linha['arranjo'] }}">
                                    </td>
                                @endforeach
                                <td class="px-3 py-2"><input data-comissao-input data-credito-comissao data-parcela="{{ $parcelas }}" name="grade[credito][{{ $parcelas }}][comissao]" value="{{ $creditoComissao }}" type="number" step="0.01" min="0" max="100" class="{{ $percentInput }}"></td>
                                <td class="px-3 py-2 text-center">
                                    <input type="hidden" name="grade[credito][{{ $parcelas }}][ativo]" value="0">
                                    <input data-credito-ativo data-parcela="{{ $parcelas }}" type="checkbox" name="grade[credito][{{ $parcelas }}][ativo]" value="1" @checked($creditoAtivo) class="h-5 w-5 rounded accent-blue-600">
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>

        <div data-tab-panel="pix" class="hidden p-5">
            @php
                $linha = $grade['pix']['bacen'];
            @endphp
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-gray-700">PIX · BACEN</h3>
                <p class="text-xs text-gray-400">Arranjo PIX com parcela única.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Instituição</th>
                            <th class="px-3 py-3">Parcelas</th>
                            <th class="px-3 py-3">Taxa (%)</th>
                            <th class="px-3 py-3">Comissão Admin (%)</th>
                            <th class="px-3 py-3 text-center">Ativo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-3 font-semibold text-gray-800">BACEN</td>
                            <td class="px-3 py-3 text-gray-600">1x</td>
                            <td class="px-3 py-3"><input data-taxa-input name="grade[pix][bacen][taxa]" value="{{ $linha['taxa'] }}" type="number" step="0.01" min="0" max="100" class="{{ $percentInput }}"></td>
                            <td class="px-3 py-3"><input data-comissao-input name="grade[pix][bacen][comissao]" value="{{ $linha['comissao'] }}" type="number" step="0.01" min="0" max="100" class="{{ $percentInput }}"></td>
                            <td class="px-3 py-3 text-center">
                                <input type="hidden" name="grade[pix][bacen][ativo]" value="0">
                                <input type="checkbox" name="grade[pix][bacen][ativo]" value="1" @checked($linha['ativo'] && $linha['existe']) class="h-5 w-5 rounded accent-blue-600">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
            <h3 class="font-bold">Preview de comissão</h3>
            <p class="mt-1">Simulação sobre uma venda de R$ 1.000,00. Preencha uma taxa e comissão para visualizar o impacto aproximado.</p>
            <p class="mt-3 font-semibold" data-preview-royalty>Preencha a grade para simular.</p>
        </div>
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('planos.index') }}" class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50">Voltar</a>
            <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Salvar Grade de Taxas</button>
        </div>
    </section>
</form>
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

        document.querySelectorAll('[data-replicar-parcela]').forEach((button) => {
            button.addEventListener('click', () => {
                const parcela = Number(button.dataset.replicarParcela);
                let ultimaTaxa = '';

                ['visa', 'master', 'elo', 'hiper', 'amex', 'outros'].forEach((grupo) => {
                    const input = document.querySelector(`[data-credito-taxa="${grupo}"][data-parcela="${parcela}"]`);
                    if (!input) return;

                    if (input.value !== '') {
                        ultimaTaxa = input.value;
                        return;
                    }

                    if (ultimaTaxa !== '') {
                        input.value = ultimaTaxa;
                    }
                });

                atualizarPreview();
            });
        });

        document.querySelectorAll('[data-credito-taxa="visa"]').forEach((input) => {
            input.addEventListener('change', (event) => {
                const parcela = event.target.dataset.parcela;
                const master = document.querySelector(`[data-credito-taxa="master"][data-parcela="${parcela}"]`);
                if (master && master.value === '' && event.target.value !== '' && confirm('Aplicar mesma taxa da VISA para MASTER?')) {
                    master.value = event.target.value;
                }
                atualizarPreview();
            });
        });

        const atualizarPreview = () => {
            const taxa = Array.from(document.querySelectorAll('[data-taxa-input]')).find((input) => input.value && Number(input.value) > 0);
            const comissao = Array.from(document.querySelectorAll('[data-comissao-input]')).find((input) => input.value && Number(input.value) > 0);
            if (!taxa && !comissao) return;

            const valorTaxa = taxa ? 1000 * (Number(taxa.value) / 100) : 0;
            const valorComissao = comissao ? 1000 * (Number(comissao.value) / 100) : 0;
            document.querySelector('[data-preview-royalty]').textContent = `Taxa: R$ ${valorTaxa.toFixed(2).replace('.', ',')} · Comissão admin: R$ ${valorComissao.toFixed(2).replace('.', ',')} sobre R$ 1.000,00.`;
        };

        document.querySelectorAll('[data-taxa-input]').forEach((input) => input.addEventListener('input', atualizarPreview));
        document.querySelectorAll('[data-comissao-input]').forEach((input) => input.addEventListener('input', atualizarPreview));

        document.querySelector('[data-grade-form]').addEventListener('submit', (event) => {
            const zeros = Array.from(document.querySelectorAll('[data-taxa-input]')).filter((input) => input.value !== '' && Number(input.value) === 0);
            if (zeros.length && !confirm('Existem taxas 0,00 preenchidas. Deseja salvar mesmo assim?')) {
                event.preventDefault();
            }
        });
    })();
</script>
@endsection
