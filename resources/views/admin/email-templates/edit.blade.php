@extends('layouts.app')

@section('title', 'Editar template — '.$template->nome)

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.email-templates.index') }}" class="text-sm text-blue-600 hover:underline">← Templates</a>
            <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $template->nome }}</h2>
            <p class="text-xs text-gray-400">{{ $template->slug }}</p>
        </div>
        <form method="POST" action="{{ route('admin.email-templates.teste', $template) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                Enviar teste para mim
            </button>
        </form>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.email-templates.update', $template) }}" class="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block space-y-1 sm:col-span-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nome interno</span>
                <input name="nome" value="{{ old('nome', $template->nome) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
            </label>
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Categoria</span>
                <select name="categoria" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @foreach ($categorias as $key => $label)
                        <option value="{{ $key }}" @selected(old('categoria', $template->categoria) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-end gap-2 pb-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" class="rounded" @checked(old('ativo', $template->ativo))>
                Template ativo
            </label>
        </div>

        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Assunto</span>
            <input name="assunto" value="{{ old('assunto', $template->assunto) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
        </label>

        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Corpo da mensagem</span>
            <textarea name="corpo" rows="12" class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-800">{{ old('corpo', $template->corpo) }}</textarea>
        </label>

        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Texto do botão (opcional)</span>
            <input name="botao_texto" value="{{ old('botao_texto', $template->botao_texto) }}" placeholder="Ex.: Ver detalhes" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
        </label>

        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Referência de placeholders</span>
            <input name="placeholders_ajuda" value="{{ old('placeholders_ajuda', $template->placeholders_ajuda) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
        </label>

        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
            <p class="font-semibold">Variáveis disponíveis</p>
            <p class="mt-1 text-blue-800 dark:text-blue-300">{{ $template->placeholders_ajuda ?: '{app_name}, {nome}, {link}, {data}, {ano}' }}</p>
            <p class="mt-2 text-xs text-blue-700 dark:text-blue-400">Use <code>{link}</code> no fluxo — o sistema preenche automaticamente com a URL correta (estabelecimento, KYC, chamado, etc.).</p>
        </div>

        <div class="flex justify-end gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
            <a href="{{ route('admin.email-templates.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600">Cancelar</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Salvar template</button>
        </div>
    </form>
</div>
@endsection
