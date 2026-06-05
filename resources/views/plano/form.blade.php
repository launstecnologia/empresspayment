@extends('layouts.app')

@section('title', $plano->exists ? 'Editar Plano' : 'Novo Plano')

@section('content')
<form method="POST" action="{{ $plano->exists ? route('planos.update', $plano) : route('planos.store') }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($plano->exists) @method('PUT') @endif

    <label class="block space-y-1">
        <span class="text-sm font-medium text-gray-600">Nome</span>
        <input name="nome" value="{{ old('nome', $plano->nome) }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
    </label>
    <label class="block space-y-1">
        <span class="text-sm font-medium text-gray-600">Descrição</span>
        <textarea name="descricao" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('descricao', $plano->descricao) }}</textarea>
    </label>
    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-600">
        <input type="hidden" name="ativo" value="0">
        <input type="checkbox" name="ativo" value="1" class="h-4 w-4 rounded accent-blue-600" @checked(old('ativo', $plano->ativo ?? true))>
        Ativo
    </label>
    <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">
        <a href="{{ route('planos.index') }}" class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50">Cancelar</a>
        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">Salvar</button>
    </div>
</form>
@endsection
