@php
    $dominioEmail = config('directadmin.dominio', 'localhost');
    $pastaAtiva = $pastaAtiva ?? 'INBOX';
    $telaCheia = $telaCheia ?? false;
    $urlBase = route('estabelecimentos.email.painel', $estabelecimento);
    $queryConta = fn (array $extra = []) => $urlBase.'?'.http_build_query(array_filter([
        'conta' => $contaAtiva?->id,
        'pasta' => $extra['pasta'] ?? $pastaAtiva,
        'mensagem' => $extra['mensagem'] ?? null,
        'compor' => $extra['compor'] ?? null,
    ]));
    $linkPasta = fn (string $pasta) => $urlBase.'?'.http_build_query(array_filter(['conta' => $contaAtiva?->id, 'pasta' => $pasta]));
    $alturaCliente = $telaCheia ? 'h-[calc(100vh-12rem)] min-h-[420px]' : 'max-h-[min(70vh,560px)] min-h-[280px]';
@endphp

<div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex flex-shrink-0 flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gradient-to-r from-slate-50 to-blue-50/50 px-5 py-4">
        <div>
            <h3 class="text-base font-bold text-gray-800">
                @if ($contaAtiva)
                    <i class="fa-solid fa-envelope mr-2 text-blue-600"></i>{{ $contaAtiva->email_completo }}
                @else
                    <i class="fa-solid fa-envelope mr-2 text-blue-600"></i>Configurar e-mail
                @endif
            </h3>
            @if ($contaAtiva)
                <p class="text-xs text-gray-500">Caixa de entrada · IMAP/SMTP</p>
            @else
                <p class="text-xs text-gray-500">Defina o subdomínio e crie a conta para abrir o cliente de e-mail</p>
            @endif
        </div>
        @if ($contaAtiva)
            <div class="flex flex-wrap items-center gap-2">
                @if ($estabelecimento->emails->count() > 1)
                    <select
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700"
                        onchange="window.location = '{{ $urlBase }}?conta=' + this.value + '&pasta={{ $pastaAtiva }}'"
                    >
                        @foreach ($estabelecimento->emails as $opcao)
                            <option value="{{ $opcao->id }}" @selected($contaAtiva->id === $opcao->id)>{{ $opcao->email_completo }}</option>
                        @endforeach
                    </select>
                @endif
                <a href="{{ $queryConta(['compor' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-900 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-800">
                    <i class="fa-solid fa-pen"></i> Novo e-mail
                </a>
                <form method="POST" action="{{ route('estabelecimentos.emails.sincronizar', [$estabelecimento, $contaAtiva]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-bold text-blue-700 hover:bg-blue-50">
                        <i class="fa-solid fa-rotate"></i> Sincronizar
                    </button>
                </form>
                @if ($estabelecimento->subdominio)
                    <button type="button" data-modal-open="criar-email" class="inline-flex h-[38px] w-[38px] items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50" title="Nova conta">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                    <button type="button" data-modal-open="senha-email-{{ $contaAtiva->id }}" class="inline-flex h-[38px] w-[38px] items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50" title="Senha">
                        <i class="fa-solid fa-lock"></i>
                    </button>
                    <button type="button" data-modal-open="redirecionar-email-{{ $contaAtiva->id }}" class="inline-flex h-[38px] w-[38px] items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50" title="Redirecionar">
                        <i class="fa-solid fa-share"></i>
                    </button>
                @endif
            </div>
        @endif
    </div>

    @error('email')
        <div class="mx-5 mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @if ($contaAtiva && ! $imapDisponivel)
        <div class="mx-5 mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Extensão PHP IMAP não instalada — exibindo cache local.
        </div>
    @endif

    @if ($contaAtiva && $contaAtiva->ultimo_erro_sync)
        <div class="mx-5 mt-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            Último erro de sincronização: {{ $contaAtiva->ultimo_erro_sync }}
        </div>
    @endif

    @if (! $contaAtiva)
        <div class="grid gap-0 lg:grid-cols-2">
            <div class="border-b border-gray-100 p-6 lg:border-b-0 lg:border-r">
                <p class="text-sm font-bold text-gray-800">1. Subdomínio do e-mail</p>
                <p class="mt-1 text-xs text-gray-500">Endereço: <strong>prefixo@sub.{{ $dominioEmail }}</strong></p>
                <form method="POST" action="{{ route('estabelecimentos.subdominio.update', $estabelecimento) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase text-gray-500">Subdomínio</span>
                        <div class="flex">
                            <input name="subdominio" value="{{ old('subdominio', $estabelecimento->subdominio) }}" placeholder="padariadojoao" class="w-full rounded-l-lg border border-gray-300 px-3 py-2 text-sm" required>
                            <span class="flex items-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3 text-xs text-gray-600">.{{ $dominioEmail }}</span>
                        </div>
                    </label>
                    <button class="w-full rounded-lg bg-indigo-900 py-2.5 text-sm font-bold text-white hover:bg-indigo-800">Salvar e continuar</button>
                </form>
            </div>
            <div class="p-6">
                <p class="text-sm font-bold text-gray-800">2. Criar conta</p>
                @if (blank($estabelecimento->subdominio))
                    <p class="mt-2 text-sm text-gray-500">Salve o subdomínio primeiro.</p>
                @else
                    <form method="POST" action="{{ route('estabelecimentos.emails.store', $estabelecimento) }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="block space-y-1">
                            <span class="text-xs font-bold uppercase text-gray-500">Prefixo</span>
                            <div class="flex">
                                <input name="prefixo" value="{{ old('prefixo', 'contato') }}" class="w-full rounded-l-lg border border-gray-300 px-3 py-2 text-sm" required>
                                <span class="flex items-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-2 text-xs text-gray-600">@{{ $estabelecimento->subdominio }}.{{ $dominioEmail }}</span>
                            </div>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-bold uppercase text-gray-500">Senha (opcional)</span>
                            <input type="password" name="senha" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </label>
                        <button class="w-full rounded-lg bg-blue-600 py-2.5 text-sm font-bold text-white hover:bg-blue-700">
                            <i class="fa-solid fa-inbox mr-1"></i> Criar e abrir caixa
                        </button>
                    </form>
                    @if ($estabelecimento->status === 'habilitado')
                        <form method="POST" action="{{ route('estabelecimentos.emails.provisionar', $estabelecimento) }}" class="mt-2">
                            @csrf
                            <button class="w-full rounded-lg border border-indigo-200 py-2 text-sm font-semibold text-indigo-800 hover:bg-indigo-50">Criar via DirectAdmin</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    @else
        <div class="grid {{ $alturaCliente }} min-h-0 flex-1 grid-cols-1 lg:grid-cols-[220px_minmax(280px,340px)_1fr]">
            <nav class="flex min-h-0 flex-col overflow-y-auto border-b border-gray-200 bg-slate-50 p-3 lg:border-b-0 lg:border-r">
                <p class="mb-3 px-2 text-[10px] font-bold uppercase tracking-wider text-gray-400">Pastas</p>
                <a href="{{ $linkPasta('INBOX') }}" class="mb-1 flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold {{ $pastaAtiva === 'INBOX' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-700 hover:bg-white' }}">
                    <span><i class="fa-solid fa-inbox mr-2 w-4"></i> Entrada</span>
                    @if (($contagemPastas['inbox_nao_lidos'] ?? 0) > 0)
                        <span class="rounded-full {{ $pastaAtiva === 'INBOX' ? 'bg-white/25 text-white' : 'bg-blue-600 text-white' }} px-2 py-0.5 text-xs">{{ $contagemPastas['inbox_nao_lidos'] }}</span>
                    @endif
                </a>
                <a href="{{ $linkPasta('enviados') }}" class="mb-1 flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold {{ $pastaAtiva === 'enviados' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-700 hover:bg-white' }}">
                    <i class="fa-solid fa-paper-plane mr-2 w-4"></i> Enviados
                </a>
                <a href="{{ $linkPasta('favoritos') }}" class="mb-1 flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold {{ $pastaAtiva === 'favoritos' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-700 hover:bg-white' }}">
                    <span><i class="fa-solid fa-star mr-2 w-4"></i> Favoritos</span>
                    @if (($contagemPastas['favoritos'] ?? 0) > 0)
                        <span class="text-xs opacity-80">{{ $contagemPastas['favoritos'] }}</span>
                    @endif
                </a>
                <a href="{{ $linkPasta('spam') }}" class="mb-1 flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold {{ $pastaAtiva === 'spam' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-700 hover:bg-white' }}">
                    <i class="fa-solid fa-ban mr-2 w-4"></i> Spam
                </a>
                <a href="{{ $linkPasta('lixeira') }}" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold {{ $pastaAtiva === 'lixeira' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-700 hover:bg-white' }}">
                    <i class="fa-solid fa-trash mr-2 w-4"></i> Lixeira
                </a>
                @if ($contaAtiva->ultimo_sync)
                    <p class="mt-auto px-2 pt-4 text-xs text-gray-400">Sync {{ $contaAtiva->ultimo_sync->format('d/m H:i') }}</p>
                @endif
            </nav>

            <div class="flex min-h-0 flex-col overflow-hidden border-b border-gray-200 bg-gray-50/50 lg:border-b-0 lg:border-r">
                <div class="flex-shrink-0 border-b border-gray-200 bg-white px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">
                        {{ $pastaAtiva === 'enviados' ? 'Enviados' : ($pastaAtiva === 'INBOX' ? 'Mensagens' : ucfirst($pastaAtiva)) }}
                    </p>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto bg-white">
                    @if ($pastaAtiva === 'enviados')
                        @forelse ($enviados ?? [] as $enviado)
                            <div class="border-b border-gray-100 px-4 py-3 text-sm hover:bg-gray-50">
                                <p class="truncate font-semibold text-gray-800">{{ $enviado->assunto }}</p>
                                <p class="truncate text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($enviado->para, 36) }}</p>
                                <p class="mt-1 text-xs text-gray-400">{{ $enviado->created_at?->format('d/m/Y H:i') }}</p>
                            </div>
                        @empty
                            <p class="px-4 py-12 text-center text-sm text-gray-400">Nenhum envio registrado.</p>
                        @endforelse
                    @else
                        @forelse ($mensagens ?? [] as $item)
                            <a href="{{ $queryConta(['mensagem' => $item->id, 'pasta' => $pastaAtiva]) }}"
                               class="block border-b border-gray-100 px-4 py-3 transition-colors hover:bg-blue-50/80 {{ ($mensagemAtiva?->id === $item->id) ? 'border-l-4 border-l-blue-600 bg-blue-50' : '' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate text-sm {{ $item->lido ? 'font-medium text-gray-600' : 'font-bold text-gray-900' }}">
                                        {{ $item->de_nome ?: $item->de_email ?: 'Remetente' }}
                                    </p>
                                    <span class="flex-shrink-0 text-xs text-gray-400">{{ $item->data_email?->format('d/m') }}</span>
                                </div>
                                <p class="truncate text-xs text-gray-600">{{ $item->assunto ?: '(sem assunto)' }}</p>
                            </a>
                        @empty
                            <p class="px-4 py-12 text-center text-sm text-gray-400">
                                <i class="fa-solid fa-inbox mb-2 block text-2xl text-gray-300"></i>
                                Caixa vazia. Sincronize o IMAP.
                            </p>
                        @endforelse
                    @endif
                </div>
                @if (isset($mensagens) && $mensagens->hasPages())
                    <div class="flex-shrink-0 border-t border-gray-200 bg-white px-2 py-2">{{ $mensagens->withQueryString()->links() }}</div>
                @endif
                @if (isset($enviados) && $enviados->hasPages())
                    <div class="flex-shrink-0 border-t border-gray-200 bg-white px-2 py-2">{{ $enviados->withQueryString()->links() }}</div>
                @endif
            </div>

            <div class="flex min-h-0 flex-col overflow-hidden bg-white">
                @if ($compondo ?? false)
                    <div class="flex-shrink-0 border-b border-gray-200 bg-gray-50 px-5 py-3">
                        <h4 class="font-bold text-gray-800"><i class="fa-solid fa-pen mr-2 text-indigo-700"></i>Novo e-mail</h4>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-5">
                        <form method="POST" action="{{ route('estabelecimentos.emails.caixa.enviar', [$estabelecimento, $contaAtiva]) }}" class="space-y-3">
                            @csrf
                            @if ($mensagemAtiva)
                                <input type="hidden" name="resposta_ao_id" value="{{ $mensagemAtiva->id }}">
                            @endif
                            <input name="para" value="{{ old('para', $mensagemAtiva?->de_email) }}" placeholder="Para" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input name="cc" value="{{ old('cc') }}" placeholder="CC" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <input name="cco" value="{{ old('cco') }}" placeholder="CCO" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            </div>
                            <input name="assunto" value="{{ old('assunto', $mensagemAtiva ? 'Re: '.$mensagemAtiva->assunto : '') }}" placeholder="Assunto" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                            <textarea name="corpo" rows="12" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required placeholder="Escreva sua mensagem...">{{ old('corpo') }}</textarea>
                            <div class="flex gap-2">
                                <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-700">Enviar</button>
                                <a href="{{ $queryConta(['pasta' => $pastaAtiva]) }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancelar</a>
                            </div>
                        </form>
                    </div>
                @elseif ($mensagemAtiva)
                    <div class="flex-shrink-0 border-b border-gray-200 bg-gray-50 px-5 py-4">
                        <h4 class="text-lg font-bold text-gray-900">{{ $mensagemAtiva->assunto ?: '(sem assunto)' }}</h4>
                        <p class="mt-1 text-sm text-gray-600">
                            <span class="font-semibold">De:</span> {{ $mensagemAtiva->de_nome ?: $mensagemAtiva->de_email ?: '-' }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $mensagemAtiva->data_email?->format('d/m/Y H:i') }}</p>
                        <a href="{{ $queryConta(['compor' => 1, 'mensagem' => $mensagemAtiva->id, 'pasta' => $pastaAtiva]) }}" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-indigo-900 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-800">
                            <i class="fa-solid fa-reply"></i> Responder
                        </a>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 text-sm leading-relaxed text-gray-800">
                        {!! $mensagemAtiva->corpoSeguro() !!}
                    </div>
                @else
                    <div class="flex flex-1 flex-col items-center justify-center bg-gray-50/30 p-8 text-center text-gray-400">
                        <i class="fa-solid fa-envelope-open mb-4 text-5xl text-gray-300"></i>
                        <p class="text-sm font-medium text-gray-500">Selecione uma mensagem na lista</p>
                        <p class="mt-1 text-xs">ou clique em <strong>Novo e-mail</strong> para redigir</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
