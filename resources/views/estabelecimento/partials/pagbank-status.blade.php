@php
    $ehAdmin = auth()->user()?->tipo === 'admin';
    $podeConfigurarEdi = $ehAdmin && filled($estabelecimento->token_pagseguro);

    $fvStatus = $estabelecimento->fv_status;
    $fvCores = match($fvStatus) {
        'concluido'   => ['bg-emerald-100 text-emerald-800', 'fa-circle-check'],
        'processando' => ['bg-blue-100 text-blue-800',    'fa-spinner fa-spin'],
        'erro'        => ['bg-red-100 text-red-800',      'fa-circle-exclamation'],
        'pendente'    => ['bg-amber-100 text-amber-800',  'fa-clock'],
        default       => ['bg-gray-100 text-gray-600',    'fa-circle-minus'],
    };
@endphp

{{-- ── Automação Força de Vendas ── --}}
<div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Automação Força de Vendas</p>

    <div class="mt-3 space-y-2 text-sm">
        <div class="flex items-center gap-2">
            @if ($fvStatus)
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold {{ $fvCores[0] }}">
                    <i class="fa-solid {{ $fvCores[1] }}"></i>
                    {{ ucfirst($fvStatus) }}
                </span>
            @else
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">
                    <i class="fa-solid fa-circle-minus"></i> Não iniciada
                </span>
            @endif
        </div>

        @if ($estabelecimento->fv_iniciado_em)
            <div>
                <dt class="text-xs text-gray-500">Iniciado em</dt>
                <dd class="font-medium text-gray-800">{{ $estabelecimento->fv_iniciado_em->format('d/m/Y H:i') }}</dd>
            </div>
        @endif

        @if ($estabelecimento->fv_concluido_em)
            <div>
                <dt class="text-xs text-gray-500">Concluído em</dt>
                <dd class="font-medium text-gray-800">{{ $estabelecimento->fv_concluido_em->format('d/m/Y H:i') }}</dd>
            </div>
        @endif

        @if ($estabelecimento->fv_erro)
            <div class="rounded-lg border border-red-100 bg-red-50 p-2 text-xs text-red-700">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $estabelecimento->fv_erro }}
            </div>
        @endif

        @if (blank($fvStatus) || $fvStatus === 'erro')
            <p class="text-xs text-gray-400">A automação é disparada automaticamente após aprovação do KYC.</p>
        @endif
    </div>
</div>

{{-- ── EDI PagBank ── --}}
<div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">EDI PagBank</p>

    <dl class="mt-3 space-y-2 text-sm">
        <div>
            <dt class="text-gray-500">Status EDI</dt>
            <dd class="font-medium text-gray-800">
                @if ($estabelecimento->pagbank_edi_ativo)
                    <span class="inline-flex items-center gap-1 text-emerald-700"><i class="fa-solid fa-circle-check"></i> Ativo — job diário busca transações</span>
                @else
                    <span class="text-gray-500">Inativo</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-gray-500">Token EDI (USER)</dt>
            <dd class="break-all font-mono text-xs text-gray-800">{{ $estabelecimento->token_pagseguro ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">IP cadastro</dt>
            <dd class="font-medium text-gray-800">{{ $estabelecimento->ip_cadastro ?: '—' }}</dd>
        </div>
    </dl>

    @if ($estabelecimento->pagbank_edi_ativo === false && blank($estabelecimento->token_pagseguro))
        <details class="mt-4 rounded-lg border border-amber-100 bg-amber-50/60 p-3 text-xs text-amber-950">
            <summary class="cursor-pointer font-bold">Como ativar o EDI via Pipefy</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-4">
                <li>Abra o chamado no Pipefy (link abaixo).</li>
                <li>Selecione <strong>Novas Ativações — EDI</strong>.</li>
                <li>Tipo: <strong>Geração de token API EDI</strong>, modelo <strong>1xN</strong>.</li>
                <li>Após confirmação do PagBank, cole o código USER abaixo e marque EDI como ativo.</li>
            </ol>
            <a href="{{ config('pagbank.pipefy_edi_url') }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center gap-1 font-semibold text-blue-700 hover:underline">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir Pipefy
            </a>
        </details>
    @endif

    @if ($ehAdmin)
        <form method="POST" action="{{ route('admin.estabelecimentos.pagbank.edi', $estabelecimento) }}" class="mt-4 space-y-3 rounded-lg border border-indigo-100 bg-white p-3">
            @csrf
            @method('PATCH')
            <p class="text-xs font-bold uppercase tracking-wide text-indigo-800">Configuração EDI (admin)</p>
            <label class="block space-y-1">
                <span class="text-xs font-semibold text-gray-600">Código USER do EDI</span>
                <input
                    type="text"
                    name="token_pagseguro"
                    value="{{ old('token_pagseguro', $estabelecimento->token_pagseguro) }}"
                    placeholder="Código retornado pelo PagBank"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs text-gray-800"
                >
                @error('token_pagseguro')
                    <span class="text-xs text-red-600">{{ $message }}</span>
                @enderror
            </label>
            <label class="block space-y-1">
                <span class="text-xs font-semibold text-gray-600">Status EDI</span>
                <select name="pagbank_edi_ativo" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800">
                    <option value="0" @selected(! old('pagbank_edi_ativo', $estabelecimento->pagbank_edi_ativo))>Inativo</option>
                    <option value="1" @selected(old('pagbank_edi_ativo', $estabelecimento->pagbank_edi_ativo))>Ativo</option>
                </select>
            </label>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-900 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-800">
                <i class="fa-solid fa-floppy-disk"></i> Salvar EDI
            </button>
        </form>
    @endif
</div>
