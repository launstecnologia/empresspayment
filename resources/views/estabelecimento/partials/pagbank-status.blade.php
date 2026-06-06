@php
    $ehAdmin = auth()->user()?->tipo === 'admin';

    $fvStatus = $estabelecimento->fv_status;
    $fvCores = match($fvStatus) {
        'concluido'    => ['bg-emerald-100 text-emerald-800', 'fa-circle-check'],
        'em_andamento' => ['bg-blue-100 text-blue-800',    'fa-spinner fa-spin'],
        'pendente'     => ['bg-amber-100 text-amber-800',  'fa-clock'],
        'erro','erro_email','timeout' => ['bg-red-100 text-red-800', 'fa-circle-exclamation'],
        default        => ['bg-gray-100 text-gray-600',    'fa-circle-minus'],
    };

    $podeIniciar = $ehAdmin && ! in_array($fvStatus, ['em_andamento', 'concluido']);
@endphp

{{-- ── Automação Força de Vendas ── --}}
<div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Automação Força de Vendas</p>

    <div class="mt-3 space-y-2 text-sm">
        <div class="flex items-center gap-2">
            @if ($fvStatus)
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold {{ $fvCores[0] }}">
                    <i class="fa-solid {{ $fvCores[1] }}"></i>
                    {{ match($fvStatus) {
                        'concluido'    => 'Concluído',
                        'em_andamento' => 'Em andamento',
                        'pendente'     => 'Aguardando fila',
                        'erro'         => 'Erro',
                        'erro_email'   => 'Erro no e-mail',
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

    @if ($podeIniciar)
        <form method="POST" action="{{ route('admin.estabelecimentos.automacao.iniciar', $estabelecimento) }}" class="mt-3"
              onsubmit="return confirm('Iniciar a automação Força de Vendas para este estabelecimento?')">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-700 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-800">
                <i class="fa-solid fa-robot"></i>
                {{ $fvStatus === 'erro' || $fvStatus === 'timeout' ? 'Retentar Automação' : 'Iniciar Automação' }}
            </button>
        </form>
    @endif

    @if ($fvStatus === 'em_andamento')
        <p class="mt-2 text-xs text-blue-600 animate-pulse">
            <i class="fa-solid fa-spinner fa-spin mr-1"></i> Processando… atualize a página para verificar.
        </p>
    @endif
</div>
