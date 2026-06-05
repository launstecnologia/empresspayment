@extends('layouts.app')

@section('title', 'Caixa de E-mail')

@section('content')
@php
    $dominio = config('directadmin.dominio', 'localhost');
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700">Caixa de E-mail</h2>
        <p class="text-sm text-gray-500">{{ $conta->email_completo }}</p>
    </div>
    <div class="flex flex-wrap gap-2 text-sm">
        <a href="{{ route('estabelecimentos.show', $estabelecimento) }}" class="rounded border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">Voltar ao estabelecimento</a>
        <form method="POST" action="{{ route('estabelecimentos.emails.sincronizar', [$estabelecimento, $conta]) }}">
            @csrf
            <button class="rounded bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                <i class="fa-solid fa-rotate mr-1"></i> Sincronizar
            </button>
        </form>
    </div>
</div>

@if (! $imapDisponivel)
    <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        A extensão PHP IMAP não está instalada. Os e-mails exibidos vêm do cache local; instale <code>php-imap</code> no servidor para sincronizar com o servidor de e-mail.
    </div>
@endif

@if ($conta->ultimo_erro_sync)
    <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        Último erro de sincronização: {{ $conta->ultimo_erro_sync }}
    </div>
@endif

<div class="grid gap-6 xl:grid-cols-[320px_1fr]">
    <aside class="overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Entrada</p>
            @if ($conta->ultimo_sync)
                <p class="text-xs text-gray-400">Sync: {{ $conta->ultimo_sync->format('d/m/Y H:i') }}</p>
            @endif
        </div>
        <div class="max-h-[70vh] overflow-y-auto">
            @forelse ($mensagens as $item)
                <a href="{{ route('estabelecimentos.emails.caixa.show', [$estabelecimento, $conta, $item]) }}"
                   class="block border-b border-gray-100 px-4 py-3 hover:bg-blue-50 {{ ($mensagem?->id === $item->id) ? 'bg-blue-50' : '' }}">
                    <p class="truncate text-sm font-bold {{ $item->lido ? 'text-gray-600' : 'text-gray-900' }}">
                        {{ $item->de_nome ?: $item->de_email ?: 'Remetente' }}
                    </p>
                    <p class="truncate text-xs text-gray-500">{{ $item->assunto ?: '(sem assunto)' }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $item->data_email?->format('d/m/Y H:i') }}</p>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-400">Nenhum e-mail na caixa. Clique em sincronizar.</p>
            @endforelse
        </div>
        @if ($mensagens->hasPages())
            <div class="border-t border-gray-100 px-2 py-2">{{ $mensagens->links() }}</div>
        @endif
    </aside>

    <div class="space-y-6">
        @if ($mensagem)
            <article class="overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-lg font-bold text-gray-800">{{ $mensagem->assunto ?: '(sem assunto)' }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        De: {{ $mensagem->de_nome ? $mensagem->de_nome.' <'.$mensagem->de_email.'>' : ($mensagem->de_email ?: '-') }}
                    </p>
                    <p class="text-xs text-gray-400">{{ $mensagem->data_email?->format('d/m/Y H:i') }}</p>
                </div>
                <div class="prose max-w-none px-5 py-5 text-sm text-gray-800">
                    {!! $mensagem->corpoSeguro() !!}
                </div>
                <div class="border-t border-gray-100 bg-gray-50 px-5 py-4">
                    <button type="button" data-modal-open="responder" class="rounded bg-indigo-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800">
                        <i class="fa-solid fa-reply mr-1"></i> Responder
                    </button>
                </div>
            </article>

            <div data-modal="responder" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
                <div class="w-full max-w-2xl rounded bg-white p-6 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-800">Responder</h3>
                        <button type="button" data-modal-close="responder" class="text-2xl text-gray-400">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('estabelecimentos.emails.caixa.enviar', [$estabelecimento, $conta]) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="resposta_ao_id" value="{{ $mensagem->id }}">
                        <label class="block space-y-1">
                            <span class="text-sm font-bold text-gray-700">Para</span>
                            <input name="para" value="{{ $mensagem->de_email }}" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" required>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-sm font-bold text-gray-700">Assunto</span>
                            <input name="assunto" value="Re: {{ $mensagem->assunto }}" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" required>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-sm font-bold text-gray-700">Mensagem</span>
                            <textarea name="corpo" rows="8" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" required></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" data-modal-close="responder" class="rounded bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                            <button class="rounded bg-indigo-900 px-4 py-2 text-sm font-semibold text-white">Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <div class="rounded border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-gray-400">
                Selecione um e-mail na lista ou envie uma nova mensagem abaixo.
            </div>
        @endif

        <section class="rounded border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-700">Novo e-mail</h3>
            <form method="POST" action="{{ route('estabelecimentos.emails.caixa.enviar', [$estabelecimento, $conta]) }}" class="grid gap-3 md:grid-cols-2">
                @csrf
                <label class="block space-y-1 md:col-span-2">
                    <span class="text-sm font-bold text-gray-700">Para</span>
                    <input name="para" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" placeholder="destino@email.com" required>
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-bold text-gray-700">CC</span>
                    <input name="cc" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-bold text-gray-700">CCO</span>
                    <input name="cco" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                </label>
                <label class="block space-y-1 md:col-span-2">
                    <span class="text-sm font-bold text-gray-700">Assunto</span>
                    <input name="assunto" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" required>
                </label>
                <label class="block space-y-1 md:col-span-2">
                    <span class="text-sm font-bold text-gray-700">Mensagem</span>
                    <textarea name="corpo" rows="6" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" required></textarea>
                </label>
                <div class="md:col-span-2">
                    <button class="rounded bg-teal-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-600">Enviar e-mail</button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-modal-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.querySelector(`[data-modal="${button.dataset.modalOpen}"]`);
                modal?.classList.remove('hidden');
                modal?.classList.add('flex');
            });
        });
        document.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.querySelector(`[data-modal="${button.dataset.modalClose}"]`);
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
            });
        });
    });
</script>
@endsection
