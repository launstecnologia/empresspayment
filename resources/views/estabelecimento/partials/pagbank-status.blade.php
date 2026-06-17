@php
    use App\Support\UsuarioComercial;

    $ehAdmin = auth()->user()?->tipo === 'admin';
    $podeGerenciarAutomacao = UsuarioComercial::podeGerenciarAutomacaoEstabelecimento($estabelecimento);

    $fvStatus = $estabelecimento->fv_status;
    $fvCores = match($fvStatus) {
        'concluido'              => ['bg-emerald-100 text-emerald-800', 'fa-circle-check'],
        'em_andamento'           => ['bg-blue-100 text-blue-800',       'fa-spinner fa-spin'],
        'pendente'               => ['bg-amber-100 text-amber-800',     'fa-clock'],
        'erro', 'timeout'        => ['bg-red-100 text-red-800',         'fa-circle-exclamation'],
        'erro_email'             => ['bg-orange-100 text-orange-800',   'fa-circle-exclamation'],
        default                  => ['bg-gray-100 text-gray-600',       'fa-circle-minus'],
    };

    $podeIniciar = $podeGerenciarAutomacao && ! in_array($fvStatus, ['em_andamento', 'concluido', 'erro_email']);
@endphp

{{-- ── Automação Força de Vendas ── --}}
<div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Automação Força de Vendas</p>

    <div class="mt-3 space-y-2 text-sm">

        {{-- Status: quando é erro_email, mostra as duas etapas separadas --}}
        @if ($fvStatus === 'erro_email')
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                    <i class="fa-solid fa-circle-check"></i> Cadastro PagBank: OK
                </span>
                <span class="inline-flex items-center gap-1 rounded-full border border-orange-300 bg-orange-50 px-2.5 py-1 text-xs font-bold text-orange-700">
                    <i class="fa-solid fa-circle-exclamation"></i> E-mail / Senha: Erro
                </span>
            </div>
        @else
            <div class="flex items-center gap-2">
                @if ($fvStatus)
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold {{ $fvCores[0] }}">
                        <i class="fa-solid {{ $fvCores[1] }}"></i>
                        {{ match($fvStatus) {
                            'concluido'    => 'Concluído',
                            'em_andamento' => 'Em andamento',
                            'pendente'     => 'Aguardando fila',
                            'erro'         => 'Erro',
                            'timeout'      => 'Timeout',
                            default        => ucfirst($fvStatus),
                        } }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">
                        <i class="fa-solid fa-circle-minus"></i> Não iniciada
                    </span>
                @endif
            </div>
        @endif

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
    </div>

    {{-- Botões de ação --}}
    <div class="mt-3 flex flex-wrap gap-2">
        @if ($fvStatus === 'erro_email' && $podeGerenciarAutomacao)
            {{-- Retentar só o e-mail --}}
            <form method="POST"
                  action="{{ route('admin.estabelecimentos.automacao.retentar-email', $estabelecimento) }}"
                  onsubmit="return confirm('Retentar apenas a etapa de e-mail/senha?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-3 py-2 text-xs font-bold text-white hover:bg-orange-700">
                    <i class="fa-solid fa-envelope-circle-check"></i> Retentar E-mail
                </button>
            </form>
            {{-- Refazer tudo --}}
            <form method="POST"
                  action="{{ route('admin.estabelecimentos.automacao.iniciar', $estabelecimento) }}"
                  onsubmit="return confirm('Refazer TODA a automação (cadastro FV + e-mail)?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                    <i class="fa-solid fa-rotate-right"></i> Refazer Tudo
                </button>
            </form>
        @elseif ($podeIniciar)
            <form method="POST"
                  action="{{ route('admin.estabelecimentos.automacao.iniciar', $estabelecimento) }}"
                  onsubmit="return confirm('Iniciar a automação Força de Vendas para este estabelecimento?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-700 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-800">
                    <i class="fa-solid fa-robot"></i>
                    {{ in_array($fvStatus, ['erro', 'timeout']) ? 'Retentar Automação' : 'Iniciar Automação' }}
                </button>
            </form>
        @elseif ($fvStatus === 'concluido' && $podeGerenciarAutomacao)
            <form method="POST"
                  action="{{ route('admin.estabelecimentos.automacao.iniciar', $estabelecimento) }}"
                  onsubmit="return confirm('A automação já foi concluída. Deseja reexecutar?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                    <i class="fa-solid fa-rotate-right"></i> Reexecutar
                </button>
            </form>
        @endif
    </div>

    @if ($fvStatus === 'em_andamento')
        <p class="mt-2 text-xs text-blue-600 animate-pulse">
            <i class="fa-solid fa-spinner fa-spin mr-1"></i> Processando… atualize a página para verificar.
        </p>
    @endif
</div>
