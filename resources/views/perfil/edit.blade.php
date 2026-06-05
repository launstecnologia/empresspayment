@extends('layouts.app')

@section('title', 'Meu perfil')

@section('content')
@php
    $isSub = $perfil instanceof \App\Models\SubUsuario;
    $avatarUrl = \App\Support\AvatarUsuario::url($perfil);
    $iniciais = \App\Support\AvatarUsuario::iniciais($perfil);
@endphp

<div class="mx-auto max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Meu perfil</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Atualize seus dados e foto de perfil.</p>
    </div>

    <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data" class="space-y-6 px-6 py-6">
        @csrf
        @method('PUT')

        <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
            <div class="relative">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="Foto de perfil" class="h-24 w-24 rounded-full border-2 border-gray-200 object-cover dark:border-gray-600">
                @else
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-blue-600 text-3xl font-bold text-white">
                        {{ $iniciais }}
                    </div>
                @endif
            </div>
            <div class="flex-1 space-y-2 text-center sm:text-left">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Foto de perfil</label>
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-blue-700 dark:text-gray-300 dark:file:bg-blue-900/40 dark:file:text-blue-300">
                <p class="text-xs text-gray-400">JPG, PNG ou WEBP · máximo 2MB</p>
                @if ($avatarUrl)
                    <label class="inline-flex items-center gap-2 text-xs text-red-600">
                        <input type="checkbox" name="remover_avatar" value="1" class="rounded border-gray-300">
                        Remover foto atual
                    </label>
                @endif
            </div>
        </div>

        @if ($isSub)
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nome</span>
                <input type="text" name="nome" value="{{ old('nome', $perfil->nome) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
            </label>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nome fantasia</span>
                    <input type="text" name="nome_fantasia" value="{{ old('nome_fantasia', $perfil->nome_fantasia) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nome completo</span>
                    <input type="text" name="nome_completo" value="{{ old('nome_completo', $perfil->nome_completo) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                </label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Telefone</span>
                    <input type="text" name="telefone" value="{{ old('telefone', $perfil->telefone) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Celular</span>
                    <input type="text" name="celular" value="{{ old('celular', $perfil->celular) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                </label>
            </div>
        @endif

        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</span>
            <input type="email" name="email" value="{{ old('email', $perfil->email) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nova senha</span>
                <input type="password" name="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
            </label>
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Confirmar senha</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
            </label>
        </div>
        <p class="text-xs text-gray-400">Deixe em branco para manter a senha atual.</p>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-700">
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">Cancelar</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Salvar perfil</button>
        </div>
    </form>
</div>
@endsection
