@extends('layouts.app')

@section('title', $taxa->exists ? 'Editar Taxa' : 'Nova Taxa')

@section('content')
<form method="POST" action="{{ $taxa->exists ? route('planos.taxas.update', [$plano, $taxa]) : route('planos.taxas.store', $plano) }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($taxa->exists) @method('PUT') @endif

    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700">{{ $plano->nome }}</h2>
        <p class="text-xs text-gray-400">Cadastre a taxa original por bandeira/instituição, tipo de transação e parcelamento.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-600">Instituição financeira</span>
            <input name="instituicao" value="{{ old('instituicao', $taxa->instituicao) }}" placeholder="VISA, MASTERCARD, ELO..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
        </label>

        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-600">Tipo de transação</span>
            <select name="tipo_transacao" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                @foreach (['debito' => 'Débito', 'credito' => 'Crédito', 'pix' => 'Pix', 'voucher' => 'Voucher'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('tipo_transacao', $taxa->tipo_transacao) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-600">Parcelas</span>
            <input name="parcelas" type="number" min="1" max="24" value="{{ old('parcelas', $taxa->parcelas ?: 1) }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
        </label>

        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-600">Taxa original (%)</span>
            <input name="taxa_percentual" type="number" step="0.01" min="0" max="100" value="{{ old('taxa_percentual', $taxa->taxa_percentual) }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
        </label>
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
        <a href="{{ route('planos.show', $plano) }}" class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50">Cancelar</a>
        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">Salvar taxa</button>
    </div>
</form>
@endsection
