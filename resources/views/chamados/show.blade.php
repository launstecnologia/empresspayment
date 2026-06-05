@extends('layouts.app')

@section('title', $chamado->numero)

@section('content')
@php
    $statusLabel = [
        'aberto' => 'Aberto',
        'em_atendimento' => 'Em atendimento',
        'aguardando_cliente' => 'Aguardando cliente',
        'resolvido' => 'Resolvido',
        'fechado' => 'Fechado',
    ];
@endphp

<section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap justify-between gap-4">
        <div>
            <p class="text-sm font-bold text-blue-600">{{ $chamado->numero }}</p>
            <h2 class="mt-1 text-xl font-bold text-gray-800">{{ $chamado->titulo }}</h2>
            <p class="mt-1 text-sm text-gray-400">{{ ucfirst($chamado->categoria) }} · {{ ucfirst($chamado->prioridade) }}</p>
        </div>
        <span class="h-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $statusLabel[$chamado->status] }}</span>
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="space-y-4">
        @foreach ($chamado->mensagens->where('interno', false) as $mensagem)
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="font-bold text-gray-800">{{ $mensagem->autor_nome }}</p>
                        <p class="text-xs text-gray-400">{{ $mensagem->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">{{ $mensagem->autor_tipo === 'admin' ? 'Admin' : 'Solicitante' }}</span>
                </div>
                <div class="whitespace-pre-line text-sm text-gray-700">{{ $mensagem->mensagem }}</div>
                @if ($mensagem->anexos->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($mensagem->anexos as $anexo)
                            <a href="{{ route('chamados.anexos.download', $anexo) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-blue-50">
                                <i class="fa-solid fa-paperclip mr-1"></i>{{ $anexo->nome_original }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    <aside class="space-y-4">
        @if (! in_array($chamado->status, ['fechado'], true))
            <form method="POST" action="{{ route('chamados.responder', $chamado->numero) }}" enctype="multipart/form-data" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                @csrf
                <h3 class="mb-3 text-sm font-bold text-gray-800">Responder chamado</h3>
                <textarea name="mensagem" rows="5" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                <input type="file" name="anexos[]" multiple class="mt-3 w-full rounded-lg border border-dashed border-blue-200 bg-blue-50 px-3 py-3 text-sm text-gray-600">
                <button class="mt-3 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Enviar mensagem</button>
            </form>
        @endif

        @if ($chamado->status === 'resolvido' && $chamado->updated_at->gte(now()->subDays(7)))
            <form method="POST" action="{{ route('chamados.reabrir', $chamado->numero) }}" class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                @csrf
                <p class="text-sm font-semibold text-amber-800">Esse chamado foi resolvido. Você pode reabrir em até 7 dias.</p>
                <button class="mt-3 rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-white">Reabrir chamado</button>
            </form>
        @endif

        @if ($chamado->status === 'fechado' && ! $chamado->avaliacao)
            <form method="POST" action="{{ route('chamados.avaliar', $chamado->numero) }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                @csrf
                <h3 class="mb-3 text-sm font-bold text-gray-800">Avaliar atendimento</h3>
                <select name="avaliacao" class="w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                    @for ($nota = 5; $nota >= 1; $nota--)
                        <option value="{{ $nota }}">{{ $nota }} estrela(s)</option>
                    @endfor
                </select>
                <textarea name="avaliacao_comentario" rows="3" placeholder="Comentário opcional" class="mt-3 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></textarea>
                <button class="mt-3 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Enviar avaliação</button>
            </form>
        @endif
    </aside>
</section>
@endsection
