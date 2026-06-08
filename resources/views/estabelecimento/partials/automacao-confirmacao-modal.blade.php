@if ($automacaoPreview ?? null)
<div data-modal="automacao-confirmar" class="modal-overlay fixed inset-0 z-[100] items-center justify-center bg-black/40 px-4">
    <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-xl">
        <div class="flex items-start justify-between border-b border-gray-100 px-6 py-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Confirmar automação</h3>
                <p class="mt-0.5 text-xs text-gray-500">Revise os dados que serão enviados ao portal PagBank Força de Vendas.</p>
            </div>
            <button type="button" data-modal-close="automacao-confirmar" class="text-2xl leading-none text-gray-400 hover:text-gray-600">&times;</button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            @if (! empty($automacaoPreview['avisos']))
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-700">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Atenção
                    </p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-xs text-amber-800">
                        @foreach ($automacaoPreview['avisos'] as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="space-y-5">
                @foreach ($automacaoPreview['secoes'] as $secao)
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">{{ $secao['titulo'] }}</p>
                        <dl class="divide-y divide-gray-100 rounded-lg border border-gray-100 bg-gray-50/50">
                            @foreach ($secao['campos'] as $campo)
                                <div class="flex flex-col gap-0.5 px-3 py-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                    <dt class="shrink-0 text-xs font-medium text-gray-500">{{ $campo['label'] }}</dt>
                                    <dd @class([
                                        'text-sm break-all text-right sm:max-w-[60%]',
                                        'font-semibold text-indigo-700' => ! empty($campo['destaque']),
                                        'font-medium text-gray-800' => empty($campo['destaque']),
                                    ])>{{ $campo['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
            <button type="button" data-modal-close="automacao-confirmar"
                    class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                Cancelar
            </button>
            <form id="form-automacao-confirmar" method="POST"
                  action="{{ route('admin.estabelecimentos.automacao.iniciar', $estabelecimento) }}">
                @csrf
                <button type="submit"
                        @disabled(! ($automacaoPreview['valido'] ?? false))
                        class="inline-flex items-center gap-2 rounded-lg px-5 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50 {{ ($automacaoPreview['valido'] ?? false) ? 'bg-indigo-700 hover:bg-indigo-800' : 'bg-gray-400' }}">
                    <i class="fa-solid fa-robot"></i>
                    <span id="automacao-confirmar-label">Confirmar e iniciar</span>
                </button>
            </form>
        </div>
    </div>
</div>
@endif
