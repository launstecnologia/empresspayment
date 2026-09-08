<div x-show="aba === 'edi'" x-cloak class="space-y-6 px-6 py-6" data-edi-monitor="{{ $reprocessamentos->whereIn('status', ['pendente', 'processando'])->isNotEmpty() ? '1' : '0' }}">
    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->has('periodo_de') || $errors->has('periodo_ate') || $errors->has('entrada_tipo') || $errors->has('entradas'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
            <p class="font-semibold">Não foi possível iniciar o reprocessamento:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach (['periodo_de', 'periodo_ate', 'entrada_tipo', 'entradas'] as $campo)
                    @error($campo)
                        <li>{{ $message }}</li>
                    @enderror
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Reprocessar EDI</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informe o período e cole a lista de estabelecimentos para rodar em segundo plano. Transações existentes não são duplicadas; canceladas no EDI têm o status atualizado.</p>
            </div>
            <a href="{{ route('admin.configuracoes.edit', ['aba' => 'edi']) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                <i class="fa-solid fa-rotate"></i> Atualizar
            </a>
        </div>

        <form method="POST" action="{{ route('admin.configuracoes.edi.reprocessar') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Data inicial</span>
                    <input type="date" name="periodo_de" value="{{ old('periodo_de', now()->subMonthsNoOverflow(3)->toDateString()) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Data final</span>
                    <input type="date" name="periodo_ate" value="{{ old('periodo_ate', now()->toDateString()) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Lista por</span>
                    <select name="entrada_tipo" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="id" @selected(old('entrada_tipo', 'id') === 'id')>ID interno</option>
                        <option value="token" @selected(old('entrada_tipo') === 'token')>token_pagseguro</option>
                    </select>
                </label>
            </div>

            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Estabelecimentos</span>
                <textarea name="entradas" rows="7" required placeholder="Um por linha, ou separados por vírgula/espaço" class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">{{ old('entradas') }}</textarea>
            </label>

            <div class="flex justify-end border-t border-gray-100 pt-4 dark:border-gray-700">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                    <i class="fa-solid fa-play"></i> Enfileirar reprocessamento
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
            <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Monitoramento</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Últimos reprocessamentos EDI disparados em Configurações, com novas transações e cancelamentos atualizados.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <th class="px-5 py-3">Lote</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Período</th>
                        <th class="px-5 py-3">Progresso</th>
                        <th class="px-5 py-3">Novas transações</th>
                        <th class="px-5 py-3">Cancelamentos</th>
                        <th class="px-5 py-3">Erros</th>
                        <th class="px-5 py-3">Iniciado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reprocessamentos as $lote)
                        @php
                            $statusClass = match ($lote->status) {
                                'concluido' => 'bg-emerald-100 text-emerald-700',
                                'erro' => 'bg-red-100 text-red-700',
                                'processando' => 'bg-blue-100 text-blue-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                            $percentual = $lote->total_itens > 0 ? min(100, round($lote->processados * 100 / $lote->total_itens)) : 0;
                        @endphp
                        <tr class="border-t border-gray-50 align-top dark:border-gray-800">
                            <td class="px-5 py-4 font-semibold text-gray-800 dark:text-gray-100">#{{ $lote->id }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ ucfirst($lote->status) }}</span>
                                @if ($lote->erro)
                                    <p class="mt-1 max-w-xs text-xs text-red-600">{{ $lote->erro }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                {{ $lote->periodo_de?->format('d/m/Y') }} → {{ $lote->periodo_ate?->format('d/m/Y') }}
                                <p class="mt-1 text-xs text-gray-400">{{ $lote->total_dias }} dia(s), lista por {{ $lote->entrada_tipo === 'token' ? 'token_pagseguro' : 'ID interno' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <div class="min-w-36">
                                    <div class="mb-1 flex justify-between text-xs text-gray-500">
                                        <span>{{ $lote->processados }}/{{ $lote->total_itens }}</span>
                                        <span>{{ $percentual }}%</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-2 rounded-full bg-blue-600" style="width: {{ $percentual }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-200">{{ number_format($lote->total_movimentos, 0, ',', '.') }}</td>
                            <td class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-200">{{ number_format($lote->total_cancelamentos, 0, ',', '.') }}</td>
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ number_format($lote->total_erros, 0, ',', '.') }}</td>
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                {{ $lote->created_at?->format('d/m/Y H:i') }}
                                @if ($lote->iniciado_por_nome)
                                    <p class="mt-1 text-xs text-gray-400">{{ $lote->iniciado_por_nome }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-sm text-gray-400">Nenhum reprocessamento EDI iniciado ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
