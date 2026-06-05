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
    $responsavel = $chamado->revenda?->nomeExibicao() ?: $chamado->marketplace?->nomeExibicao() ?: $chamado->master?->nomeExibicao() ?: '-';
@endphp

<section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap justify-between gap-4">
        <div>
            <p class="text-sm font-bold text-blue-600">{{ $chamado->numero }}</p>
            <h2 class="mt-1 text-xl font-bold text-gray-800">{{ $chamado->titulo }}</h2>
            <p class="mt-1 text-sm text-gray-400">{{ ucfirst($chamado->categoria) }} · {{ ucfirst($chamado->aberto_por_nivel) }} · {{ $responsavel }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.chamados.status', $chamado->numero) }}">
                @csrf
                <select name="status" onchange="this.form.submit()" class="rounded-lg border border-gray-200 bg-white px-3 text-sm">
                    @foreach ($statusLabel as $status => $label)
                        <option value="{{ $status }}" @selected($chamado->status === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            <form method="POST" action="{{ route('admin.chamados.prioridade', $chamado->numero) }}">
                @csrf
                <select name="prioridade" onchange="this.form.submit()" class="rounded-lg border border-gray-200 bg-white px-3 text-sm">
                    @foreach (['baixa', 'media', 'alta', 'urgente'] as $prioridade)
                        <option value="{{ $prioridade }}" @selected($chamado->prioridade === $prioridade)>{{ ucfirst($prioridade) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="space-y-4">
        @foreach ($chamado->mensagens as $mensagem)
            <article class="rounded-xl border {{ $mensagem->interno ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-white' }} p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="font-bold text-gray-800">{{ $mensagem->autor_nome }}</p>
                        <p class="text-xs text-gray-400">{{ $mensagem->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="rounded-full {{ $mensagem->interno ? 'bg-amber-200 text-amber-800' : 'bg-gray-100 text-gray-600' }} px-2.5 py-1 text-xs font-bold">
                        {{ $mensagem->interno ? 'Nota interna' : ($mensagem->autor_tipo === 'admin' ? 'Admin' : 'Solicitante') }}
                    </span>
                </div>
                <div class="whitespace-pre-line text-sm text-gray-700">{{ $mensagem->mensagem }}</div>
                @if ($mensagem->anexos->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($mensagem->anexos as $anexo)
                            <a href="{{ route('chamados.anexos.download', $anexo) }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-blue-50">
                                <i class="fa-solid fa-paperclip mr-1"></i>{{ $anexo->nome_original }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    <aside class="space-y-4">
        <form method="POST" action="{{ route('admin.chamados.responder', $chamado->numero) }}" enctype="multipart/form-data" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            @csrf
            <h3 class="mb-3 text-sm font-bold text-gray-800">Responder chamado</h3>
            <textarea name="mensagem" rows="6" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
            <label class="mt-3 flex items-center gap-2 text-sm font-semibold text-gray-600">
                <input type="hidden" name="interno" value="0">
                <input type="checkbox" name="interno" value="1" class="h-4 w-4 rounded accent-amber-500">
                Nota interna
            </label>
            <input type="file" name="anexos[]" multiple class="mt-3 w-full rounded-lg border border-dashed border-blue-200 bg-blue-50 px-3 py-3 text-sm text-gray-600">
            <button class="mt-3 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Enviar resposta</button>
        </form>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-sm font-bold text-gray-800">Histórico</h3>
            <div class="space-y-3">
                @foreach ($chamado->historicos as $historico)
                    <div class="border-l-2 border-blue-100 pl-3 text-sm">
                        <p class="font-semibold text-gray-700">{{ ucfirst(str_replace('_', ' ', $historico->acao)) }}</p>
                        <p class="text-xs text-gray-400">{{ $historico->autor_nome }} · {{ $historico->created_at->format('d/m/Y H:i') }}</p>
                        @if ($historico->valor_novo)
                            <p class="text-xs text-gray-500">{{ $historico->valor_anterior ?: '-' }} → {{ $historico->valor_novo }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </aside>
</section>
@endsection
