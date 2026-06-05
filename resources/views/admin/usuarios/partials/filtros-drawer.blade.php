@php
    $indexParams = $tipoAtual ? ['tipo' => $tipoAtual] : [];
    $subtitulos = [
        'master' => 'Refine a listagem de masters',
        'marketplace' => 'Refine a listagem de marketplaces',
        'revenda' => 'Refine a listagem de revendas',
    ];
    $subtituloDrawer = $tipoAtual ? ($subtitulos[$tipoAtual] ?? 'Refine a listagem') : 'Refine a listagem de administradores';
@endphp

<div x-show="filtrosAberto" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="filtrosAberto = false">
    <div class="absolute inset-0 bg-gray-900/50" @click="filtrosAberto = false"></div>
    <aside
        class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl"
        x-show="filtrosAberto"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @click.stop
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Filtros</h3>
                <p class="text-xs text-gray-500">{{ $subtituloDrawer }}</p>
            </div>
            <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100" @click="filtrosAberto = false">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="GET" action="{{ route('usuarios.index', $indexParams) }}" class="flex min-h-0 flex-1 flex-col">
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                @if ($tipoAtual === 'marketplace' && $masters->isNotEmpty())
                    <div>
                        <label for="filtro-master" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Master</label>
                        <select id="filtro-master" name="master_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            @foreach ($masters as $master)
                                <option value="{{ $master['id'] }}" @selected(($filtros['master_id'] ?? '') == $master['id'])>{{ $master['nome'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($tipoAtual === 'revenda')
                    @if ($masters->isNotEmpty())
                        <div>
                            <label for="filtro-master-revenda" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Master</label>
                            <select id="filtro-master-revenda" name="master_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todos</option>
                                @foreach ($masters as $master)
                                    <option value="{{ $master['id'] }}" @selected(($filtros['master_id'] ?? '') == $master['id'])>{{ $master['nome'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($marketplaces->isNotEmpty())
                        <div>
                            <label for="filtro-marketplace" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Marketplace</label>
                            <select id="filtro-marketplace" name="marketplace_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todos</option>
                                @foreach ($marketplaces as $marketplace)
                                    <option value="{{ $marketplace['id'] }}" @selected(($filtros['marketplace_id'] ?? '') == $marketplace['id'])>{{ $marketplace['nome'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($revendas->isNotEmpty() && auth()->user()?->tipo === 'admin')
                        <div>
                            <label for="filtro-revenda" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Revenda</label>
                            <select id="filtro-revenda" name="revenda_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todas</option>
                                @foreach ($revendas as $revenda)
                                    <option value="{{ $revenda['id'] }}" @selected(($filtros['revenda_id'] ?? '') == $revenda['id'])>{{ $revenda['nome'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endif

                <div>
                    <label for="filtro-ativo" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Cadastro ativo</label>
                    <select id="filtro-ativo" name="ativo" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filtros['ativo'] ?? '') === '1' || ($filtros['ativo'] ?? '') === 1 || ($filtros['ativo'] ?? '') === true)>Sim</option>
                        <option value="0" @selected(($filtros['ativo'] ?? '') === '0' || ($filtros['ativo'] ?? '') === 0 || ($filtros['ativo'] ?? '') === false)>Não</option>
                    </select>
                </div>

                <div>
                    <label for="filtro-pessoa" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo de pessoa</label>
                    <select id="filtro-pessoa" name="pessoa_tipo" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="juridica" @selected(($filtros['pessoa_tipo'] ?? '') === 'juridica')>Pessoa jurídica</option>
                        <option value="fisica" @selected(($filtros['pessoa_tipo'] ?? '') === 'fisica')>Pessoa física</option>
                    </select>
                </div>

                <div>
                    <label for="filtro-segmento" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Segmento</label>
                    <select id="filtro-segmento" name="segmento" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        @foreach ($segmentos as $segmento)
                            <option value="{{ $segmento->nome }}" @selected(($filtros['segmento'] ?? '') === $segmento->nome)>{{ $segmento->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="filtro-data-inicio" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Data inicial</label>
                        <input
                            id="filtro-data-inicio"
                            name="data_inicio"
                            type="date"
                            value="{{ $filtros['data_inicio'] ?? '' }}"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                    </div>
                    <div>
                        <label for="filtro-data-fim" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Data final</label>
                        <input
                            id="filtro-data-fim"
                            name="data_fim"
                            type="date"
                            value="{{ $filtros['data_fim'] ?? '' }}"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                    </div>
                </div>
                <p class="text-xs text-gray-400">Período com base na data de cadastro do usuário.</p>
            </div>

            <div class="flex gap-2 border-t border-gray-100 px-5 py-4">
                <a
                    href="{{ route('usuarios.index', $indexParams) }}"
                    class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm font-semibold text-gray-600 transition hover:bg-gray-50"
                >
                    Limpar
                </a>
                <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Aplicar filtros
                </button>
            </div>
        </form>
    </aside>
</div>
