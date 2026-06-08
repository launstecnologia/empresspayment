@php
    use App\Support\KycDivergenciaHelper;

    $divergencias = $documento->cruzamento_divergencias ?? [];
    $provavelOcr = KycDivergenciaHelper::provavelErroLeitura($divergencias);
    $detalhes = KycDivergenciaHelper::detalhesLegiveis($divergencias);
    $mensagem = $documento->openai_motivo_reprovacao
        ?: KycDivergenciaHelper::mensagemReenvio($divergencias);
@endphp

@if ($documento->cruzamento_status === 'divergencia' && ! empty($divergencias))
    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
        <p class="text-xs font-bold text-amber-900">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            {{ $provavelOcr ? 'Possível erro na leitura do documento' : 'Divergência nos dados do documento' }}
        </p>
        <p class="mt-1.5 text-xs leading-relaxed text-amber-800">{{ $mensagem }}</p>

        @if ($detalhes)
            <ul class="mt-2 space-y-1 text-xs text-amber-900/90">
                @foreach ($detalhes as $linha)
                    <li class="flex gap-1.5">
                        <span class="text-amber-600">•</span>
                        <span>{{ $linha }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($provavelOcr)
            <p class="mt-2 text-xs text-amber-700">
                Dica: fotografe em ambiente iluminado, documento plano, sem flash e com o CPF/nome totalmente visíveis.
            </p>
        @endif

        @if (($mostrarBotaoReenvio ?? true) && $estabelecimento)
            <a href="{{ route('estabelecimentos.show', $estabelecimento) }}#documentos"
               data-tab-link-target="documentos"
               class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700">
                <i class="fa-solid fa-camera"></i> Enviar nova foto
            </a>
        @endif
    </div>
@endif
