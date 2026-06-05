@extends('layouts.app')

@section('title', 'Novo Chamado')

@section('content')
<form method="POST" action="{{ route('chamados.store') }}" enctype="multipart/form-data" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    @csrf

    @if ($errors->any())
        <div class="m-5 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <p class="font-semibold">Revise os campos antes de abrir o chamado.</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="border-b border-gray-100 px-5 py-4">
        <h2 class="text-base font-bold text-gray-800">Dados do chamado</h2>
        <p class="text-xs text-gray-400">Descreva o problema com detalhes para o Admin analisar.</p>
    </div>

    <div class="grid gap-4 p-5 md:grid-cols-12">
        <label class="space-y-1 md:col-span-12">
            <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Título</span>
            <input name="titulo" value="{{ old('titulo') }}" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
        </label>
        <label class="space-y-1 md:col-span-4">
            <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Categoria</span>
            <select name="categoria" class="w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                <option value="">Selecione</option>
                @foreach (['financeiro', 'tecnico', 'comercial', 'cadastro', 'integracao', 'outro'] as $categoria)
                    <option value="{{ $categoria }}" @selected(old('categoria') === $categoria)>{{ ucfirst($categoria) }}</option>
                @endforeach
            </select>
        </label>
        <label class="space-y-1 md:col-span-4">
            <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Prioridade</span>
            <select name="prioridade" class="w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                @foreach (['baixa', 'media', 'alta', 'urgente'] as $prioridade)
                    <option value="{{ $prioridade }}" @selected(old('prioridade', 'media') === $prioridade)>{{ ucfirst($prioridade) }}</option>
                @endforeach
            </select>
        </label>
        <label class="space-y-1 md:col-span-12">
            <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Mensagem inicial</span>
            <textarea name="mensagem" rows="7" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">{{ old('mensagem') }}</textarea>
        </label>
        <label class="space-y-1 md:col-span-12">
            <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Anexos</span>
            <input type="file" name="anexos[]" multiple class="w-full rounded-lg border border-dashed border-blue-200 bg-blue-50 px-3 py-4 text-sm text-gray-600">
            <span class="text-xs text-gray-400">Até 5 arquivos. 10MB por arquivo e 30MB no total.</span>
        </label>
    </div>

    <div class="flex justify-end gap-3 border-t border-gray-100 px-5 py-4">
        <a href="{{ route('chamados.index') }}" class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600">Cancelar</a>
        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Abrir Chamado</button>
    </div>
</form>
@endsection
