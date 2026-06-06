@extends('layouts.app')

@section('title', 'Detalhes do Estabelecimento')

@section('content')
@php
    $nome = $estabelecimento->nome_fantasia ?: $estabelecimento->razao_social ?: $estabelecimento->nome_completo ?: 'Estabelecimento';
    $documento = $estabelecimento->cnpj ?: $estabelecimento->cpf ?: '-';
    $statusClass = match ($estabelecimento->status) {
        'habilitado' => 'bg-emerald-500 text-white',
        'desabilitado' => 'bg-red-500 text-white',
        'em_analise', 'qualidade' => 'bg-amber-500 text-white',
        'em_cadastro' => 'bg-sky-500 text-white',
        default => 'bg-blue-500 text-white',
    };
    $riscoClass = match ($estabelecimento->risco) {
        'bloqueado' => 'bg-red-500 text-white',
        'atencao' => 'bg-amber-500 text-white',
        default => 'bg-emerald-500 text-white',
    };
    $navTabClass = 'inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold transition-colors';
    $navActionClass = 'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition-colors';
    $partesEndereco = array_filter([
        trim(($estabelecimento->endereco ?: '').($estabelecimento->numero ? ', '.$estabelecimento->numero : '')),
        $estabelecimento->complemento,
        $estabelecimento->bairro,
        trim(($estabelecimento->cidade ?: '').($estabelecimento->uf ? ' - '.$estabelecimento->uf : '')),
        $estabelecimento->cep ? 'CEP '.$estabelecimento->cep : null,
    ]);
    $enderecoCompleto = $partesEndereco !== [] ? implode(' · ', $partesEndereco) : '-';
@endphp

<div class="mb-4 flex items-center justify-between">
    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700">Detalhes do Estabelecimento</h2>
    <div class="text-sm text-gray-500">
        <a href="{{ route('estabelecimentos.index') }}" class="text-gray-800 hover:text-blue-600">Estabelecimento</a>
        <span class="mx-2 text-gray-400">›</span>
        <span>Detalhes</span>
    </div>
</div>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3">
    <nav class="flex flex-wrap items-center gap-2" aria-label="Seções do estabelecimento" data-tab-nav>
        <a href="#resumo" data-tab-link="resumo" class="{{ $navTabClass }} border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:text-blue-700">
            <i class="fa-solid fa-store"></i> Resumo
        </a>
        <a href="#email-plataforma" data-tab-link="email-plataforma" class="{{ $navTabClass }} border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:text-blue-700">
            <i class="fa-solid fa-envelope"></i> E-mail
            @if ($estabelecimento->webmail_email)
                <span class="rounded-full bg-blue-600 px-2 py-0.5 text-xs text-white"><i class="fa-solid fa-check text-[9px]"></i></span>
            @endif
        </a>
        <a href="{{ route('estabelecimentos.kyc.show', $estabelecimento) }}" class="{{ $navTabClass }} border-gray-200 bg-white text-gray-700 hover:border-indigo-300 hover:text-indigo-700">
            <i class="fa-solid fa-shield-halved"></i> KYC
            @if ($estabelecimento->kycAnalise)
                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ str_replace('_', ' ', $estabelecimento->kycAnalise->status) }}</span>
            @endif
        </a>
        <a href="#documentos" data-tab-link="documentos" class="{{ $navTabClass }} border-gray-200 bg-white text-gray-700 hover:border-teal-300 hover:text-teal-700">
            <i class="fa-solid fa-file-lines"></i> Documentos
        </a>
        <a href="#logs" data-tab-link="logs" class="{{ $navTabClass }} border-gray-200 bg-white text-gray-700 hover:border-gray-400">
            <i class="fa-solid fa-clock-rotate-left"></i> Logs
        </a>
    </nav>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('estabelecimentos.edit', $estabelecimento) }}" class="{{ $navActionClass }} bg-indigo-900 text-white hover:bg-indigo-800">
            <i class="fa-solid fa-pen"></i> Editar
        </a>
        <button type="button" data-modal-open="status" class="{{ $navActionClass }} bg-sky-500 text-white hover:bg-sky-600">
            <i class="fa-solid fa-sliders"></i> Alterar status
        </button>
    </div>
</div>

<section id="resumo" data-tab-panel="resumo" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
        <h3 class="text-lg font-bold text-gray-900">{{ $nome }}</h3>
        <p class="mt-0.5 text-sm text-gray-500">{{ $estabelecimento->pessoa_tipo === 'fisica' ? 'CPF' : 'CNPJ' }} {{ $documento }}</p>
    </div>

    <div class="grid gap-6 p-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Dados cadastrais</p>
                <dl class="mt-2 space-y-2 text-sm">
                    <div class="flex flex-wrap gap-x-2">
                        <dt class="font-semibold text-gray-600">Nome fantasia</dt>
                        <dd class="text-gray-800">{{ $estabelecimento->nome_fantasia ?: '-' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2">
                        <dt class="font-semibold text-gray-600">Razão social</dt>
                        <dd class="text-gray-800">{{ $estabelecimento->razao_social ?: '-' }}</dd>
                    </div>
                </dl>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Endereço</p>
                <p class="mt-2 text-sm leading-relaxed text-gray-800">{{ $enderecoCompleto }}</p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Contato</p>
                <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-semibold text-gray-600">Telefone</dt>
                        <dd class="text-gray-800">{{ $estabelecimento->telefone ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-gray-600">Celular</dt>
                        <dd class="text-gray-800">{{ $estabelecimento->celular ?: '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="font-semibold text-gray-600">E-mail</dt>
                        <dd class="text-gray-800">
                            @if ($estabelecimento->email)
                                <a href="mailto:{{ $estabelecimento->email }}" class="font-medium text-blue-600 hover:underline">{{ $estabelecimento->email }}</a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Hierarquia</p>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div>
                        <dt class="text-gray-500">Master</dt>
                        <dd class="font-medium text-gray-800">{{ $estabelecimento->master?->id ? $estabelecimento->master->id.' · '.$estabelecimento->master->nomeExibicao() : '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Marketplace</dt>
                        <dd class="font-medium text-gray-800">{{ $estabelecimento->marketplace?->id ? $estabelecimento->marketplace->id.' · '.$estabelecimento->marketplace->nomeExibicao() : '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Revenda</dt>
                        <dd class="font-medium text-gray-800">{{ $estabelecimento->revenda?->id ? $estabelecimento->revenda->id.' · '.$estabelecimento->revenda->nomeExibicao() : '-' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Operação</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $estabelecimento->status ?: 'pendente')) }}</span>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $riscoClass }}">{{ ucfirst($estabelecimento->risco ?: 'confiavel') }}</span>
                </div>
                <p class="mt-3 text-sm text-gray-800"><span class="font-semibold text-gray-600">Plano:</span> {{ $estabelecimento->plano?->nome ?: '-' }}</p>
            </div>
            @include('estabelecimento.partials.pagbank-status')
        </div>
    </div>
</section>

{{-- ── Aba: E-mail Plataforma ── --}}
<section id="email-plataforma" data-tab-panel="email-plataforma" class="mt-8 hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
        <h3 class="text-base font-bold text-gray-900"><i class="fa-solid fa-envelope mr-2 text-blue-500"></i>E-mail Plataforma</h3>
        <p class="mt-0.5 text-xs text-gray-500">Caixa de e-mail do estabelecimento em <strong>{{ config('directadmin.dominio') }}</strong> com redirecionamento automático.</p>
    </div>

    <div class="p-6">
        @if ($estabelecimento->webmail_email)
            @php $dominioPlataforma = config('directadmin.dominio'); @endphp
            <div class="max-w-lg space-y-5">
                {{-- Info do e-mail --}}
                <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-5">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-white">
                            <i class="fa-solid fa-at text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="break-all text-base font-bold text-gray-900">{{ $estabelecimento->webmail_email }}</p>
                            @if ($estabelecimento->email)
                                <p class="mt-1 flex items-center gap-1 text-xs text-gray-500">
                                    <i class="fa-solid fa-share text-blue-400"></i>
                                    Redireciona para <span class="font-semibold text-gray-700">{{ $estabelecimento->email }}</span>
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <form action="{{ route('estabelecimentos.webmail.sso', $estabelecimento) }}" method="POST" target="_blank">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                                <i class="fa-solid fa-envelope-open-text"></i> Acessar Webmail
                            </button>
                        </form>
                        <button type="button" data-modal-open="webmail-senha" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50">
                            <i class="fa-solid fa-key"></i> Trocar Senha
                        </button>
                    </div>
                    @error('senha_webmail')
                        <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Configurações IMAP/SMTP --}}
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Configuração para cliente de e-mail</p>
                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-gray-600">Servidor IMAP</dt>
                            <dd class="font-mono text-xs text-gray-800">{{ config('directadmin.imap_host') ?: 'mail.'.config('directadmin.dominio') }}</dd>
                            <dd class="text-xs text-gray-500">Porta {{ config('directadmin.imap_porta', 993) }} · SSL</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-600">Servidor SMTP</dt>
                            <dd class="font-mono text-xs text-gray-800">{{ config('directadmin.smtp_host') ?: 'mail.'.config('directadmin.dominio') }}</dd>
                            <dd class="text-xs text-gray-500">Porta {{ config('directadmin.smtp_porta', 587) }} · TLS</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="font-semibold text-gray-600">Usuário</dt>
                            <dd class="font-mono text-xs text-gray-800">{{ $estabelecimento->webmail_email }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        @elseif ($estabelecimento->email)
            @php $dominioPlataforma = config('directadmin.dominio'); @endphp
            <div class="max-w-lg">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                        <i class="fa-solid fa-envelope-circle-check text-2xl"></i>
                    </div>
                    <h4 class="mt-3 font-bold text-gray-800">Nenhum e-mail criado</h4>
                    @if (session('aviso'))
                        <p class="mt-1 text-sm text-amber-800">{{ session('aviso') }}</p>
                    @else
                        <p class="mt-1 text-sm text-gray-600">Crie um e-mail <strong>&#64;{{ $dominioPlataforma }}</strong> para este estabelecimento. O e-mail redirecionará automaticamente para <strong>{{ $estabelecimento->email }}</strong>.</p>
                    @endif
                    <button type="button" data-modal-open="webmail-criar" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-amber-700">
                        <i class="fa-solid fa-plus"></i> Criar E-mail Plataforma
                    </button>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-400">Cadastre um e-mail de contato no estabelecimento para criar uma caixa na plataforma.</p>
        @endif
    </div>
</section>

<section id="documentos" data-tab-panel="documentos" class="mt-8 hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    @php
        $tiposDocumento = [
            'RG/CNH - Frente',
            'RG/CNH - Verso',
            'Selfie Segurando Documento',
            'Comprovante da Atividade',
            'Comprovante de Endereço',
            'Contrato Social (Se for MEI - CCMEI)',
        ];
        $documentosPorTipo = $estabelecimento->documentos->keyBy('tipo_documento');
        $totalDocumentos = $estabelecimento->documentos->count();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
        <div>
            <h3 class="text-base font-bold text-gray-800">Documentos</h3>
            <p class="text-xs text-gray-400">{{ $totalDocumentos }} documento(s) anexado(s). A análise KYC (OpenAI) inicia automaticamente ao enviar aqui.</p>
        </div>
        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
            {{ $documentosPorTipo->count() }}/{{ count($tiposDocumento) }} tipos enviados
        </span>
    </div>

    <div class="grid gap-6 p-5 xl:grid-cols-[1fr_420px]">
        <div class="space-y-4">
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($tiposDocumento as $tipo)
                    @php
                        $documentoTipo = $documentosPorTipo->get($tipo);
                    @endphp
                    <div class="rounded-xl border {{ $documentoTipo ? 'border-emerald-100 bg-emerald-50/40' : 'border-gray-100 bg-gray-50' }} p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full {{ $documentoTipo ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500' }}">
                                <i class="fa-solid {{ $documentoTipo ? 'fa-check' : 'fa-file-arrow-up' }}"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-gray-800">{{ $tipo }}</p>
                                @if ($documentoTipo)
                                    <p class="mt-1 truncate text-xs text-gray-500">{{ $documentoTipo->arquivo_nome ?: basename($documentoTipo->arquivo_path) }}</p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <a href="{{ route('documentos.download', $documentoTipo) }}" class="rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50">
                                            <i class="fa-solid fa-eye mr-1"></i> Visualizar
                                        </a>
                                        <form method="POST" action="{{ route('estabelecimentos.documentos.destroy', [$estabelecimento, $documentoTipo]) }}" onsubmit="return confirm('Remover este documento?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-red-100 bg-white px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50">
                                                <i class="fa-solid fa-trash mr-1"></i> Remover
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <p class="mt-1 text-xs text-gray-400">Pendente de envio.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('estabelecimentos.documentos.store', $estabelecimento) }}" enctype="multipart/form-data" class="rounded-xl border border-gray-100 bg-gray-50 p-4">
            @csrf
            <div class="mb-4">
                <h4 class="text-sm font-bold text-gray-800">Adicionar documento</h4>
                <p class="text-xs text-gray-400">PDF, imagem ou Word até 25MB.</p>
            </div>

            <label class="block space-y-1">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Tipo de Documento</span>
                <select name="tipo_documento" class="w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700">
                    <option value="">Selecione</option>
                    @foreach ($tiposDocumento as $tipo)
                        <option>{{ $tipo }}</option>
                    @endforeach
                </select>
            </label>

            <label data-dropzone class="mt-4 flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-blue-300 bg-blue-50 px-4 py-6 text-center transition-colors hover:border-blue-500 hover:bg-blue-100">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl"></i>
                </span>
                <span class="mt-3 text-sm font-bold text-gray-800">Arraste o documento aqui</span>
                <span data-file-name class="mt-1 text-xs text-gray-500">ou clique para escolher um arquivo</span>
                <input data-file-input type="file" name="documento" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
            </label>

            <button class="mt-4 w-full rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-teal-600">
                <i class="fa-solid fa-plus mr-1"></i> Adicionar Documento
            </button>
        </form>
    </div>

    <div class="border-t border-gray-100 bg-gray-50 p-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-3 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-700">
                    <i class="fa-solid fa-link"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-800">Link para envio de documentos</p>
                    <p class="text-xs text-gray-400">Compartilhe este link para o estabelecimento anexar arquivos.</p>
                </div>
            </div>
            <div class="flex">
                <input data-public-link readonly value="{{ route('documentos.public.create', $estabelecimento->documento_token_publico) }}" class="w-full rounded-l-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-700">
                <button type="button" data-copy-public-link class="rounded-r-lg border border-l-0 border-indigo-900 px-5 text-sm font-bold text-indigo-900 hover:bg-indigo-50">Copiar</button>
            </div>
        </div>
    </div>
</section>

<section id="logs" data-tab-panel="logs" class="mt-8 hidden overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
    @php
        $acaoLabel = [
            'documento_inserido' => ['Documento enviado', 'bg-emerald-100 text-emerald-700', 'fa-file-circle-plus'],
            'documento_removido' => ['Documento removido', 'bg-red-100 text-red-700', 'fa-file-circle-minus'],
            'update_status' => ['Status alterado', 'bg-blue-100 text-blue-700', 'fa-rotate'],
            'insert' => ['Cadastro criado', 'bg-emerald-100 text-emerald-700', 'fa-plus'],
            'update' => ['Cadastro atualizado', 'bg-blue-100 text-blue-700', 'fa-pen'],
            'email_criado' => ['E-mail criado', 'bg-blue-100 text-blue-700', 'fa-envelope'],
            'email_senha_alterada' => ['Senha do e-mail', 'bg-blue-100 text-blue-700', 'fa-lock'],
            'email_redirecionado' => ['Redirecionamento', 'bg-blue-100 text-blue-700', 'fa-share'],
            'email_removido' => ['E-mail removido', 'bg-red-100 text-red-700', 'fa-trash'],
            'pagbank_edi_atualizado' => ['EDI PagBank', 'bg-indigo-100 text-indigo-700', 'fa-building-columns'],
        ];
    @endphp
    <div class="border-b border-gray-200 bg-gray-50 px-4 py-4">
        <h3 class="text-sm font-bold text-gray-800">Logs do Estabelecimento</h3>
        <p class="mt-0.5 text-xs text-gray-500">Ações internas do sistema.</p>
    </div>

    <div class="p-4">
        <table class="w-full border border-gray-200 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-200 px-2 py-2 text-left font-bold text-gray-900">#</th>
                    <th class="border border-gray-200 px-2 py-2 text-left font-bold text-gray-900">Ação</th>
                    <th class="border border-gray-200 px-2 py-2 text-left font-bold text-gray-900">Usuário</th>
                    <th class="border border-gray-200 px-2 py-2 text-left font-bold text-gray-900">Mensagem</th>
                    <th class="border border-gray-200 px-2 py-2 text-left font-bold text-gray-900">Data</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $acao = $acaoLabel[$log->acao] ?? [ucfirst(str_replace('_', ' ', $log->acao)), 'bg-gray-100 text-gray-700', 'fa-circle-info'];
                    @endphp
                    <tr>
                        <td class="border border-gray-200 px-2 py-2">{{ $log->id }}</td>
                        <td class="border border-gray-200 px-2 py-2">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold {{ $acao[1] }}">
                                <i class="fa-solid {{ $acao[2] }}"></i>
                                {{ $acao[0] }}
                            </span>
                        </td>
                        <td class="border border-gray-200 px-2 py-2">{{ $log->usuario_nome ?: '-' }}</td>
                        <td class="border border-gray-200 px-2 py-2">{{ $log->mensagem ?: '-' }}</td>
                        <td class="border border-gray-200 px-2 py-2">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="border border-gray-200 px-2 py-8 text-center text-gray-400">Nenhum log encontrado para este estabelecimento.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('modals')
    {{-- ── Modal: Criar E-mail Plataforma ── --}}
    @if ($estabelecimento->email && blank($estabelecimento->webmail_email))
    @php $usernameSugerido = preg_replace('/[^a-z0-9._-]/i', '', strtolower(str($estabelecimento->email)->before('@')->value())); @endphp
    <div data-modal="webmail-criar" class="modal-overlay fixed inset-0 z-[100] items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Criar E-mail Plataforma</h3>
                    @php $dominioPlataforma = config('directadmin.dominio'); @endphp
                    <p class="mt-0.5 text-xs text-gray-500">Será criado <strong>username&#64;{{ $dominioPlataforma }}</strong></p>
                </div>
                <button type="button" data-modal-close="webmail-criar" class="text-2xl text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form method="POST" action="{{ route('estabelecimentos.webmail.criar', $estabelecimento) }}">
                @csrf
                <label class="block space-y-1">
                    <span class="text-sm font-bold text-gray-700">Nome do e-mail</span>
                    <div class="flex items-center overflow-hidden rounded-lg border border-gray-300 bg-white focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100">
                        <input
                            type="text"
                            name="username"
                            value="{{ old('username', $usernameSugerido) }}"
                            placeholder="nome.sobrenome"
                            class="flex-1 border-0 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none"
                            pattern="[a-zA-Z0-9._-]+"
                            title="Apenas letras, números, ponto, hífen ou sublinhado"
                            required
                        >
                        <span class="select-none bg-gray-50 px-3 py-2.5 text-sm text-gray-500 border-l border-gray-200">&#64;{{ $dominioPlataforma }}</span>
                    </div>
                    @error('username')
                        <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </label>
                <p class="mt-2 text-xs text-gray-400">
                    O e-mail criado redirecionará automaticamente para <strong>{{ $estabelecimento->email }}</strong>.
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" data-modal-close="webmail-criar" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700">
                        <i class="fa-solid fa-plus mr-1"></i> Criar E-mail
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ── Modal: Trocar Senha do E-mail Plataforma ── --}}
    @if ($estabelecimento->webmail_email)
    <div data-modal="webmail-senha" class="modal-overlay fixed inset-0 z-[100] items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Trocar Senha do E-mail</h3>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $estabelecimento->webmail_email }}</p>
                </div>
                <button type="button" data-modal-close="webmail-senha" class="text-2xl text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form method="POST" action="{{ route('estabelecimentos.webmail.senha', $estabelecimento) }}">
                @csrf
                @method('PATCH')
                <div class="space-y-3">
                    <label class="block space-y-1">
                        <span class="text-sm font-bold text-gray-700">Nova senha</span>
                        <input type="password" name="senha" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" placeholder="Mínimo 8 caracteres" required minlength="8" autocomplete="new-password">
                    </label>
                    <label class="block space-y-1">
                        <span class="text-sm font-bold text-gray-700">Confirmar senha</span>
                        <input type="password" name="senha_confirmation" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" placeholder="Repita a nova senha" required autocomplete="new-password">
                    </label>
                    @error('senha_webmail')
                        <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" data-modal-close="webmail-senha" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700">
                        <i class="fa-solid fa-key mr-1"></i> Alterar Senha
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div data-modal="status" class="modal-overlay fixed inset-0 z-[100] items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-xl rounded bg-white p-6 shadow-xl">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-700">Alteração de Status</h3>
                <button type="button" data-modal-close="status" class="text-2xl text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form method="POST" action="{{ route('estabelecimentos.status.update', $estabelecimento) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <label class="block space-y-1">
                    <span class="text-sm font-bold text-gray-800">Novo Status</span>
                    <select name="status" class="w-full rounded border border-gray-300 bg-white px-3 text-sm text-gray-700">
                        <option value="">Selecione</option>
                        <option value="habilitado" @selected($estabelecimento->status === 'habilitado')>Habilitado</option>
                        <option value="desabilitado" @selected($estabelecimento->status === 'desabilitado')>Desabilitado</option>
                        <option value="em_analise" @selected($estabelecimento->status === 'em_analise')>Em Análise</option>
                        <option value="pendente" @selected($estabelecimento->status === 'pendente')>Pendente</option>
                        <option value="qualidade" @selected($estabelecimento->status === 'qualidade')>Qualidade</option>
                    </select>
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-bold text-gray-800">Observação</span>
                    <textarea name="observacao" rows="4" placeholder="Observação de status..." class="w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700"></textarea>
                </label>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" data-modal-close="status" class="rounded bg-gray-100 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Fechar</button>
                    <button class="rounded bg-indigo-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-800">Alterar</button>
                </div>
            </form>
        </div>
    </div>
@endpush

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tabLinks = document.querySelectorAll('[data-tab-link]');
        const tabPanels = document.querySelectorAll('[data-tab-panel]');
        const tabActive = ['border-blue-200', 'bg-blue-50', 'text-blue-800'];
        const tabIdle = ['border-gray-200', 'bg-white', 'text-gray-700'];

        const setTabStyles = (link, active) => {
            tabActive.forEach((c) => link.classList.toggle(c, active));
            tabIdle.forEach((c) => link.classList.toggle(c, !active));
        };

        const showTab = (name) => {
            const tab = name || 'resumo';
            tabPanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab);
            });
            tabLinks.forEach((link) => setTabStyles(link, link.dataset.tabLink === tab));
            if (tab !== 'resumo') {
                history.replaceState(null, '', `#${tab}`);
            } else {
                history.replaceState(null, '', window.location.pathname + window.location.search);
            }
        };

        tabLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                showTab(link.dataset.tabLink);
            });
        });

        const hashTab = window.location.hash.replace('#', '');
        const validTabs = ['resumo', 'email-plataforma', 'documentos', 'logs'];
        if (validTabs.includes(hashTab)) {
            showTab(hashTab);
        } else {
            showTab('resumo');
        }

        // Reabrir modal de webmail se houver erros de validação
        @if ($errors->has('username'))
            document.querySelector('[data-modal="webmail-criar"]')?.classList.add('is-open');
            showTab('email-plataforma');
        @endif
        @if ($errors->has('senha_webmail') || $errors->has('senha'))
            document.querySelector('[data-modal="webmail-senha"]')?.classList.add('is-open');
            showTab('email-plataforma');
        @endif

        document.querySelectorAll('[data-modal-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.querySelector(`[data-modal="${button.dataset.modalOpen}"]`);
                modal?.classList.add('is-open');
            });
        });

        const closeModal = (name) => {
            const modal = document.querySelector(`[data-modal="${name}"]`);
            modal?.classList.remove('is-open');
        };

        document.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(button.dataset.modalClose));
        });

        document.querySelectorAll('.modal-overlay').forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    modal.classList.remove('is-open');
                }
            });
        });

        const dropzone = document.querySelector('[data-dropzone]');
        const fileInput = document.querySelector('[data-file-input]');
        const fileName = document.querySelector('[data-file-name]');

        if (dropzone && fileInput && fileName) {
            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.add('border-blue-500', 'bg-blue-100');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.remove('border-blue-500', 'bg-blue-100');
                });
            });

            dropzone.addEventListener('drop', (event) => {
                if (event.dataTransfer.files.length) {
                    fileInput.files = event.dataTransfer.files;
                    fileName.textContent = event.dataTransfer.files[0].name;
                }
            });

            fileInput.addEventListener('change', () => {
                fileName.textContent = fileInput.files[0]?.name || 'ou clique para escolher um arquivo';
            });
        }

        document.querySelector('[data-copy-public-link]')?.addEventListener('click', async () => {
            const input = document.querySelector('[data-public-link]');
            await navigator.clipboard.writeText(input.value);
        });

    });
</script>
@endsection
