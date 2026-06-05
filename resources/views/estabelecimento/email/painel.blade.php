@extends('layouts.app')

@section('title', 'E-mail — '.$nomeEstabelecimento)

@section('content')
@php
    $contaEmailPrincipal = $contaAtiva ?? $estabelecimento->emails->first();
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="text-sm text-gray-500">
            <a href="{{ route('estabelecimentos.index') }}" class="hover:text-blue-600">Estabelecimentos</a>
            <span class="mx-2 text-gray-400">›</span>
            <a href="{{ route('estabelecimentos.show', $estabelecimento) }}" class="hover:text-blue-600">Detalhes</a>
            <span class="mx-2 text-gray-400">›</span>
            <span class="text-gray-700">E-mail</span>
        </div>
        <p class="mt-1 text-lg font-bold text-gray-800">{{ $nomeEstabelecimento }}</p>
        @if ($contaAtiva)
            <p class="text-sm text-gray-500">{{ $contaAtiva->email_completo }}</p>
        @endif
    </div>
    <a href="{{ route('estabelecimentos.show', $estabelecimento) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
        <i class="fa-solid fa-arrow-left"></i> Voltar aos detalhes
    </a>
</div>

@if ($modoDemo ?? false)
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <i class="fa-solid fa-flask mr-2"></i>
        <strong>Modo demonstração:</strong> mensagens fixas para visualizar o layout. Desative com <code class="rounded bg-amber-100 px-1">EMAIL_DEMO_LAYOUT=false</code> no .env.
    </div>
@endif

@include('estabelecimento.email.partials.leitor', ['telaCheia' => true])
@endsection

@push('modals')
    @foreach ($estabelecimento->emails as $contaEmail)
        <div data-modal="senha-email-{{ $contaEmail->id }}" class="modal-overlay fixed inset-0 z-[100] items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-lg rounded bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-700">Alterar senha</h3>
                    <button type="button" data-modal-close="senha-email-{{ $contaEmail->id }}" class="text-2xl text-gray-400">&times;</button>
                </div>
                <form method="POST" action="{{ route('estabelecimentos.emails.senha', [$estabelecimento, $contaEmail]) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm text-gray-500">{{ $contaEmail->email_completo }}</p>
                    <input type="password" name="senha" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" required minlength="8">
                    <div class="flex justify-end gap-2">
                        <button type="button" data-modal-close="senha-email-{{ $contaEmail->id }}" class="rounded bg-gray-100 px-4 py-2 text-sm font-semibold">Fechar</button>
                        <button class="rounded bg-indigo-900 px-4 py-2 text-sm font-semibold text-white">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
        <div data-modal="redirecionar-email-{{ $contaEmail->id }}" class="modal-overlay fixed inset-0 z-[100] items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-lg rounded bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-700">Redirecionar</h3>
                    <button type="button" data-modal-close="redirecionar-email-{{ $contaEmail->id }}" class="text-2xl text-gray-400">&times;</button>
                </div>
                <form method="POST" action="{{ route('estabelecimentos.emails.redirecionar', [$estabelecimento, $contaEmail]) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm text-gray-500">{{ $contaEmail->email_completo }}</p>
                    <input type="email" name="destino" value="{{ $contaEmail->redirecionamento_para }}" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" required>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-modal-close="redirecionar-email-{{ $contaEmail->id }}" class="rounded bg-gray-100 px-4 py-2 text-sm font-semibold">Fechar</button>
                        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    @if ($estabelecimento->subdominio)
        <div data-modal="criar-email" class="modal-overlay fixed inset-0 z-[100] items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-lg rounded bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-700">Nova conta de e-mail</h3>
                    <button type="button" data-modal-close="criar-email" class="text-2xl text-gray-400">&times;</button>
                </div>
                <form method="POST" action="{{ route('estabelecimentos.emails.store', $estabelecimento) }}" class="space-y-4">
                    @csrf
                    <label class="block space-y-1">
                        <span class="text-sm font-bold text-gray-800">Prefixo</span>
                        <input name="prefixo" value="contato" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" required>
                    </label>
                    <label class="block space-y-1">
                        <span class="text-sm font-bold text-gray-800">Senha (opcional)</span>
                        <input type="password" name="senha" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    </label>
                    <div class="flex justify-end gap-3">
                        <button type="button" data-modal-close="criar-email" class="rounded bg-gray-100 px-5 py-2 text-sm font-semibold">Fechar</button>
                        <button class="rounded bg-blue-600 px-5 py-2 text-sm font-semibold text-white">Criar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endpush

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-modal-open]').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelector(`[data-modal="${button.dataset.modalOpen}"]`)?.classList.add('is-open');
            });
        });
        const closeModal = (name) => document.querySelector(`[data-modal="${name}"]`)?.classList.remove('is-open');
        document.querySelectorAll('[data-modal-close]').forEach((b) => b.addEventListener('click', () => closeModal(b.dataset.modalClose)));
        document.querySelectorAll('.modal-overlay').forEach((modal) => {
            modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('is-open'); });
        });
    });
</script>
@endsection
