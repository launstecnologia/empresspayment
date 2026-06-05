@extends('layouts.app')

@section('title', $segmento->exists ? 'Editar Segmento' : 'Novo Segmento')

@section('content')
<form method="POST" action="{{ $segmento->exists ? route('segmentos.update', $segmento) : route('segmentos.store') }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($segmento->exists) @method('PUT') @endif

    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700">Dados do segmento</h2>
        <p class="text-xs text-gray-400">Cadastre os segmentos usados em estabelecimentos e usuários comerciais.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-600">Nome</span>
            <input name="nome" value="{{ old('nome', $segmento->nome) }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
            @error('nome') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
        </label>
        <label class="inline-flex items-center gap-2 self-end text-sm font-medium text-gray-600">
            <input type="hidden" name="ativo" value="0">
            <input type="checkbox" name="ativo" value="1" class="h-4 w-4 rounded accent-blue-600" @checked(old('ativo', $segmento->exists ? $segmento->ativo : true))>
            Ativo
        </label>
        <label class="space-y-1 md:col-span-2">
            <span class="text-sm font-medium text-gray-600">Descrição</span>
            <textarea name="descricao" rows="4" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('descricao', $segmento->descricao) }}</textarea>
        </label>
    </div>

    <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
        <a href="{{ route('segmentos.index') }}" class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50">Cancelar</a>
        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">Salvar</button>
    </div>
</form>
@endsection
