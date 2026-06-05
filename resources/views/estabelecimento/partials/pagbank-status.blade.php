@php
    use App\Support\PagBankEstabelecimentoStatus;
    use App\Support\PlatformSettings;

    [$pagbankBadgeClass, $pagbankBadgeLabel] = PagBankEstabelecimentoStatus::badge($estabelecimento);
    $ehAdmin = auth()->user()?->tipo === 'admin';
    $podeReenviar = $ehAdmin && PagBankEstabelecimentoStatus::podeEnfileirarCadastro($estabelecimento);
    $podeConfigurarEdi = $ehAdmin && ($estabelecimento->pagbank_account_id || filled($estabelecimento->token_pagseguro));
@endphp

<div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">PagBank</p>
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($ehAdmin)
                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold {{ PlatformSettings::pagbankAmbiente() === 'sandbox' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                    {{ PlatformSettings::pagbankAmbienteRotulo() }}
                </span>
            @endif
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $pagbankBadgeClass }}">{{ $pagbankBadgeLabel }}</span>
        </div>
    </div>

    <dl class="mt-3 space-y-2 text-sm">
        <div>
            <dt class="text-gray-500">Account ID</dt>
            <dd class="break-all font-mono text-xs font-medium text-gray-800">{{ $estabelecimento->pagbank_account_id ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Cadastrado em</dt>
            <dd class="font-medium text-gray-800">{{ $estabelecimento->pagbank_cadastrado_em?->format('d/m/Y H:i') ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">IP cadastro</dt>
            <dd class="font-medium text-gray-800">{{ $estabelecimento->ip_cadastro ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Token expira em</dt>
            <dd class="font-medium text-gray-800">
                @if ($estabelecimento->pagbank_token_expira)
                    {{ $estabelecimento->pagbank_token_expira->format('d/m/Y H:i') }}
                    @if ($estabelecimento->pagbank_token_expira->isPast())
                        <span class="text-red-600">(expirado)</span>
                    @elseif ($estabelecimento->pagbank_token_expira->lte(now()->addDays(7)))
                        <span class="text-amber-600">(renova em breve)</span>
                    @endif
                @else
                    —
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-gray-500">Token EDI (USER)</dt>
            <dd class="break-all font-mono text-xs text-gray-800">{{ $estabelecimento->token_pagseguro ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">EDI</dt>
            <dd class="font-medium text-gray-800">{{ $estabelecimento->pagbank_edi_ativo ? 'Ativo — job diário busca transações' : 'Inativo' }}</dd>
        </div>
    </dl>

    @if ($estabelecimento->pagbank_account_id && ! $estabelecimento->pagbank_edi_ativo)
        <details class="mt-4 rounded-lg border border-amber-100 bg-amber-50/60 p-3 text-xs text-amber-950">
            <summary class="cursor-pointer font-bold">Ativar EDI via Pipefy</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-4">
                <li>Abra o chamado no Pipefy (link abaixo).</li>
                <li>Selecione <strong>Novas Ativações — EDI</strong>.</li>
                <li>Tipo: <strong>Geração de token API EDI</strong>, modelo <strong>1xN</strong>.</li>
                <li>Informe o Account ID: <code class="rounded bg-white px-1">{{ $estabelecimento->pagbank_account_id }}</code></li>
                <li>Após confirmação do PagBank, cole o código USER abaixo e marque EDI como ativo.</li>
            </ol>
            <a href="{{ config('pagbank.pipefy_edi_url') }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center gap-1 font-semibold text-blue-700 hover:underline">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir Pipefy
            </a>
        </details>
    @endif

    @if ($podeConfigurarEdi)
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

    @if ($podeReenviar)
        <form method="POST" action="{{ route('admin.estabelecimentos.pagbank.reenviar', $estabelecimento) }}" class="mt-4">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-900 hover:bg-indigo-100">
                <i class="fa-solid fa-rotate"></i> Enviar cadastro PagBank
            </button>
        </form>
    @endif

    <div class="mt-4 border-t border-gray-200 pt-3">
        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Log API PagBank (cadastro / token)</p>
        @if ($estabelecimento->relationLoaded('pagbankLogs') && $estabelecimento->pagbankLogs->isNotEmpty())
            <ul class="mt-2 space-y-2">
                @foreach ($estabelecimento->pagbankLogs as $logPagbank)
                    <li class="rounded border border-gray-200 bg-white px-2 py-2 text-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-gray-700">{{ $logPagbank->tipo }}</span>
                            <span class="font-mono text-[10px] text-gray-500">{{ $logPagbank->metodo }} {{ $logPagbank->endpoint }}</span>
                            <span class="{{ $logPagbank->sucesso ? 'font-bold text-emerald-600' : 'font-bold text-red-600' }}">
                                {{ $logPagbank->sucesso ? 'Sucesso' : 'Falhou' }}
                                @if ($logPagbank->response_status)
                                    (HTTP {{ $logPagbank->response_status }})
                                @endif
                            </span>
                        </div>
                        <p class="mt-0.5 text-gray-500">{{ $logPagbank->created_at?->format('d/m/Y H:i:s') }}
                            @if ($logPagbank->duracao_ms)
                                · {{ $logPagbank->duracao_ms }} ms
                            @endif
                        </p>
                        @if ($logPagbank->erro)
                            <p class="mt-1 break-words text-red-600">{{ $logPagbank->erro }}</p>
                        @elseif ($logPagbank->response_body)
                            <p class="mt-1 break-all font-mono text-[10px] text-gray-600">{{ json_encode($logPagbank->response_body, JSON_UNESCAPED_UNICODE) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mt-2 rounded-lg border border-dashed border-gray-200 bg-white px-3 py-3 text-xs text-gray-600">
                @if ($estabelecimento->pagbank_account_id)
                    <p>Nenhuma chamada recente registrada. Conta já criada: <code class="text-[10px]">{{ $estabelecimento->pagbank_account_id }}</code></p>
                @else
                    <p class="font-semibold text-gray-700">Ainda sem registro de chamada à API.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-gray-500">
                        <li>Confirme se o <strong>worker da fila</strong> está rodando (<code>pagseguro-queue</code>).</li>
                        <li>KYC precisa estar <strong>aprovado</strong> (atual: {{ $estabelecimento->kycAnalise?->status ? str_replace('_', ' ', $estabelecimento->kycAnalise->status) : 'sem análise' }}).</li>
                        <li>Token parceiro em <strong>Admin → Configurações → PagBank</strong>.</li>
                        <li>Atualize esta página após alguns segundos do “enfileirado”.</li>
                    </ul>
                @endif
            </div>
        @endif
    </div>
</div>
