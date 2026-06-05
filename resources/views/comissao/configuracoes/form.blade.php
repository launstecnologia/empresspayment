@extends('layouts.app')

@section('title', $configuracao->exists ? 'Editar Comissão' : 'Nova Comissão')

@section('content')
<form method="POST" action="{{ $configuracao->exists ? route('comissoes.configuracoes.update', $configuracao) : route('comissoes.configuracoes.store') }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($configuracao->exists) @method('PUT') @endif

    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700">Configuração de repasse (taxa MDR)</h2>
        <p class="text-xs text-gray-400">Define quanto da taxa do plano é repassado na cadeia. A retenção comercial (% sobre comissão) é configurada no cadastro de Marketplace e Revenda.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <label class="space-y-1 md:col-span-2">
            <span class="text-sm font-medium text-gray-600">Taxa do plano</span>
            <select name="plano_taxa_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Selecione uma taxa</option>
                @foreach ($taxas as $taxa)
                    <option value="{{ $taxa->id }}" @selected((int) old('plano_taxa_id', $configuracao->plano_taxa_id) === $taxa->id)>
                        {{ $taxa->plano?->nome }} · {{ $taxa->instituicao }} · {{ ucfirst($taxa->tipo_transacao) }} · {{ $taxa->parcelas }}x · taxa original {{ number_format($taxa->taxa_percentual, 2, ',', '.') }}%
                    </option>
                @endforeach
            </select>
        </label>

        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-600">Responsável pelo repasse</span>
            <select name="usuario_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Selecione um usuário</option>
                @foreach ($usuarios as $usuario)
                    <option value="{{ $usuario->id }}" @selected((int) old('usuario_id', $configuracao->usuario_id) === $usuario->id)>
                        {{ strtoupper($usuario->tipo) }} · {{ $usuario->nomeExibicao() }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-600">Percentual que repassa (%)</span>
            <input name="percentual" type="number" step="0.01" min="0" max="100" value="{{ old('percentual', $configuracao->percentual) }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
        </label>
    </div>

    <div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700">
        Exemplo: se a taxa original é 2,00% e o Admin repassa 1,50%, a comissão do Admin será 0,50%.
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
        <a href="{{ route('comissoes.configuracoes.index') }}" class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50">Cancelar</a>
        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">Salvar comissão</button>
    </div>
</form>
@endsection
