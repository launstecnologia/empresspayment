@extends('layouts.app')

@section('title', 'Alterar Senha')

@section('content')
<form method="POST" action="{{ route('usuarios.subusuarios.password.update', [$usuario, $subUsuario]) }}" class="max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    @csrf
    @method('PUT')

    <div class="border-b border-gray-100 px-5 py-4">
        <h2 class="text-base font-bold text-gray-800">Alterar senha do usuário operacional</h2>
        <p class="text-sm text-gray-400">{{ $subUsuario->nome }} · {{ $subUsuario->email }}</p>
    </div>

    @if ($errors->any())
        <div class="m-5 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <p class="font-semibold">Revise os campos.</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 p-5 md:grid-cols-2">
        <label class="space-y-1">
            <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Nova senha</span>
            <input name="password" type="password" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
        </label>
        <label class="space-y-1">
            <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Confirmar senha</span>
            <input name="password_confirmation" type="password" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
        </label>
    </div>

    <div class="flex justify-end gap-3 border-t border-gray-100 px-5 py-4">
        <a href="{{ route('usuarios.show', $usuario) }}" class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600">Cancelar</a>
        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Salvar senha</button>
    </div>
</form>
@endsection
