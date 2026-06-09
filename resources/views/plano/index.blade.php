@extends('layouts.app')

@section('title', 'Planos')

@section('content')
@if (session('status'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
@endif

<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-sm font-semibold text-gray-700">Planos e taxas originais</h2>
        <p class="text-xs text-gray-400">Consulta das taxas cadastradas na plataforma.</p>
    </div>
    @if ($podeGerirPlanos)
        <a href="{{ route('planos.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">+ Novo Plano</a>
    @endif
</div>

<div class="grid gap-4">
    @foreach ($planos as $plano)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold text-gray-800">{{ $plano->nome }}</h3>
                        <p class="truncate text-sm text-gray-400">{{ $plano->descricao ?: 'Sem descrição' }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold bg-green-100 text-green-700">ATIVO</span>
                    <a href="{{ route('planos.show', $plano) }}" title="Taxas" class="flex h-9 w-9 items-center justify-center rounded-lg text-sky-600 transition-colors hover:bg-sky-50">
                        <i class="fa-solid fa-table-list text-sm"></i>
                    </a>
                    @if ($podeGerirPlanos)
                        <a href="{{ route('planos.edit', $plano) }}" title="Editar" class="flex h-9 w-9 items-center justify-center rounded-lg text-blue-600 transition-colors hover:bg-blue-50">
                            <i class="fa-solid fa-pen text-sm"></i>
                        </a>
                        <button
                            type="button"
                            title="Ocultar plano"
                            data-modal-open="inativar-plano"
                            data-action="{{ route('planos.inativar', $plano) }}"
                            data-plano-nome="{{ $plano->nome }}"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-red-500 transition-colors hover:bg-red-50"
                        >
                            <i class="fa-solid fa-trash text-sm"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="mt-6">{{ $planos->links() }}</div>

@if ($podeGerirPlanos)
    <div data-modal="inativar-plano" class="modal-overlay fixed inset-0 z-[100] items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Ocultar plano</h3>
                    <p class="mt-1 text-sm text-gray-500">O plano não será excluído do banco — apenas deixará de aparecer nas listagens.</p>
                </div>
                <button type="button" data-modal-close="inativar-plano" class="text-2xl leading-none text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <form id="form-inativar-plano" method="POST" action="" class="space-y-4">
                @csrf
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Plano: <strong id="inativar-plano-nome">—</strong>
                </p>

                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Senha de administrador</span>
                    <div class="relative">
                        <input type="password" name="senha_admin" id="senha-admin-inativar-plano" autocomplete="current-password" required
                               class="w-full rounded-lg border border-gray-300 py-2 pl-3 pr-10 text-sm @error('senha_admin') border-red-500 @enderror">
                        <button type="button" id="toggle-senha-admin-inativar-plano"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fa-regular fa-eye" id="toggle-senha-admin-inativar-plano-icon"></i>
                        </button>
                    </div>
                    @error('senha_admin')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="hidden" name="confirmacao" value="0">
                    <input type="checkbox" name="confirmacao" value="1" required class="mt-0.5 h-4 w-4 rounded accent-red-600">
                    <span>Confirmo que desejo ocultar este plano da plataforma.</span>
                </label>
                @error('confirmacao')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" data-modal-close="inativar-plano" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Ocultar plano
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@section('scripts')
@if ($podeGerirPlanos)
<script>
    (() => {
        const modal = document.querySelector('[data-modal="inativar-plano"]');
        const form = document.getElementById('form-inativar-plano');
        const nomeEl = document.getElementById('inativar-plano-nome');

        const abrirModal = (action, planoNome) => {
            if (!modal || !form) return;
            form.action = action;
            if (nomeEl) nomeEl.textContent = planoNome || '—';
            modal.classList.add('is-open');
        };

        document.querySelectorAll('[data-modal-open="inativar-plano"]').forEach((button) => {
            button.addEventListener('click', () => {
                abrirModal(button.dataset.action, button.dataset.planoNome);
            });
        });

        document.querySelectorAll('[data-modal-close="inativar-plano"]').forEach((button) => {
            button.addEventListener('click', () => modal?.classList.remove('is-open'));
        });

        modal?.addEventListener('click', (event) => {
            if (event.target === modal) modal.classList.remove('is-open');
        });

        document.getElementById('toggle-senha-admin-inativar-plano')?.addEventListener('click', () => {
            const input = document.getElementById('senha-admin-inativar-plano');
            const icon = document.getElementById('toggle-senha-admin-inativar-plano-icon');
            if (!input || !icon) return;
            const oculta = input.type === 'password';
            input.type = oculta ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !oculta);
            icon.classList.toggle('fa-eye-slash', oculta);
        });

        @if (session('abrir_modal_inativar_plano'))
            @php $planoReabrir = $planos->firstWhere('id', (int) session('abrir_modal_inativar_plano')); @endphp
            @if ($planoReabrir)
                abrirModal(@json(route('planos.inativar', $planoReabrir)), @json($planoReabrir->nome));
            @endif
        @endif
    })();
</script>
@endif
@endsection
