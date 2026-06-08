@extends('layouts.app')

@section('title', 'Pesquisar Doc.')

@section('content')
<div class="mb-4 text-sm text-gray-500">
    <a href="{{ route('estabelecimentos.index') }}" class="font-semibold text-gray-700 hover:text-blue-600">Estabelecimentos</a>
    <span class="mx-2">›</span>
    <span>Pesquisar Doc.</span>
</div>

<div class="mx-auto max-w-2xl space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-800">Pesquisar Doc.</h1>
            <p class="mt-1 text-sm text-gray-500">
                Consulta na Força de Vendas PagBank se o CPF ou CNPJ já possui cadastro ou pode ser incluído na plataforma.
            </p>
        </div>

        @unless ($automacaoConfigurada)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Automação Força de Vendas não configurada. Defina URL da API, chave e credenciais FV em Configurações.
            </div>
        @else
            <div id="consulta-form-wrap">
                <form id="form-consulta-documento" class="space-y-4">
                    @csrf
                    <label class="block space-y-1.5">
                        <span class="text-sm font-semibold text-gray-700">CPF ou CNPJ</span>
                        <input
                            type="text"
                            name="documento"
                            id="documento"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="18"
                            placeholder="000.000.000-00 ou 00.000.000/0000-00"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            required
                        >
                    </label>

                    <button
                        type="submit"
                        id="btn-consultar"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Consultar</span>
                    </button>
                </form>
            </div>

            <div id="consulta-loading" class="mt-6 hidden rounded-lg border border-blue-100 bg-blue-50 p-6">
                <div class="flex flex-col items-center justify-center gap-3 text-center">
                    <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-600"></i>
                    <p class="text-sm font-semibold text-blue-800">Pesquisando...</p>
                </div>
            </div>

            <div id="consulta-resultado" class="mt-6 hidden"></div>
        @endunless
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-consulta-documento');
    if (!form) return;

    const formWrap = document.getElementById('consulta-form-wrap');
    const btn = document.getElementById('btn-consultar');
    const loading = document.getElementById('consulta-loading');
    const resultado = document.getElementById('consulta-resultado');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('[name="_token"]')?.value;

    const onlyDigits = (value) => (value || '').replace(/\D/g, '');

    const formatDocumento = (digits) => {
        if (digits.length === 11) {
            return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
        }
        if (digits.length === 14) {
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
        }
        return digits;
    };

    const mostrarLoading = () => {
        formWrap?.classList.add('hidden');
        resultado.classList.add('hidden');
        resultado.innerHTML = '';
        loading.classList.remove('hidden');
        btn.disabled = true;
    };

    const esconderLoading = () => {
        loading.classList.add('hidden');
        formWrap?.classList.remove('hidden');
        btn.disabled = false;
    };

    const renderResultado = (payload, meta) => {
        const detalhe = payload.resultado || {};
        const situacao = payload.situacao || detalhe.situacao;
        const mensagem = detalhe.mensagem || payload.erro || 'Consulta concluída.';
        const documento = meta.documento || detalhe.documento || '';
        const digits = meta.documento_digits || onlyDigits(documento);
        const cadastroUrl = @json(route('estabelecimentos.create')).replace(/\/$/, '') + '?documento=' + encodeURIComponent(digits);

        let boxClass = 'border-gray-200 bg-gray-50 text-gray-800';
        let titulo = 'Resultado da consulta';
        let icone = 'fa-circle-info';
        let acao = '';

        if (meta.local) {
            boxClass = 'border-amber-200 bg-amber-50 text-amber-900';
            titulo = 'Já cadastrado na plataforma';
            icone = 'fa-store';
            acao = `<a href="${meta.local.url}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver estabelecimento
            </a>`;
        } else if (situacao === 'cliente_interno') {
            boxClass = 'border-red-200 bg-red-50 text-red-900';
            titulo = 'Não disponível para cadastro (FV-CDS-01)';
            icone = 'fa-ban';
        } else if (situacao === 'ja_cadastrado') {
            boxClass = 'border-amber-200 bg-amber-50 text-amber-900';
            titulo = 'Documento já cadastrado no PagBank FV';
            icone = 'fa-circle-exclamation';
        } else if (situacao === 'disponivel') {
            boxClass = 'border-emerald-200 bg-emerald-50 text-emerald-900';
            titulo = 'Aprovado para cadastro';
            icone = 'fa-circle-check';
            acao = `<a href="${cadastroUrl}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                <i class="fa-solid fa-plus"></i> Continuar cadastrando
            </a>`;
        } else if (payload.status === 'erro') {
            boxClass = 'border-red-200 bg-red-50 text-red-900';
            titulo = 'Erro na consulta';
            icone = 'fa-triangle-exclamation';
        } else if (situacao === 'erro_pagbank') {
            boxClass = 'border-red-200 bg-red-50 text-red-900';
            titulo = 'PagBank retornou um aviso';
            icone = 'fa-triangle-exclamation';
        }

        resultado.innerHTML = `
            <div class="rounded-lg border p-5 ${boxClass}">
                <p class="flex items-center gap-2 text-sm font-bold">
                    <i class="fa-solid ${icone}"></i> ${titulo}
                </p>
                <p class="mt-2 text-sm leading-relaxed">${mensagem}</p>
                ${documento ? `<p class="mt-2 text-xs opacity-80">Documento: <strong>${documento}</strong></p>` : ''}
                ${acao}
                <button type="button" id="btn-nova-consulta" class="mt-4 text-sm font-semibold text-blue-600 hover:text-blue-800">
                    Nova consulta
                </button>
            </div>
        `;
        resultado.classList.remove('hidden');
        document.getElementById('btn-nova-consulta')?.addEventListener('click', () => {
            resultado.classList.add('hidden');
            resultado.innerHTML = '';
            formWrap?.classList.remove('hidden');
            form.documento.value = '';
            form.documento.focus();
        });
    };

    const pollStatus = async (jobId, meta) => {
        const finalStatus = ['concluido', 'erro'];
        let tentativas = 0;

        while (tentativas < 60) {
            tentativas++;
            await new Promise((resolve) => setTimeout(resolve, 3000));

            const resp = await fetch(`/pesquisar-documento/status/${encodeURIComponent(jobId)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.mensagem || 'Falha ao consultar status.');
            }

            if (finalStatus.includes(data.status)) {
                renderResultado(data, meta);
                return;
            }
        }

        throw new Error('Tempo esgotado aguardando resposta do PagBank.');
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const digits = onlyDigits(form.documento.value);
        if (digits.length !== 11 && digits.length !== 14) {
            alert('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');
            return;
        }

        mostrarLoading();

        try {
            const resp = await fetch(@json(route('fv-documento.consultar')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ documento: formatDocumento(digits) }),
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.mensagem || 'Não foi possível iniciar a consulta.');
            }

            if (data.local) {
                renderResultado({ situacao: 'local' }, data);
                return;
            }

            await pollStatus(data.job_id, data);
        } catch (error) {
            renderResultado({ status: 'erro', erro: error.message || 'Erro inesperado.' }, {});
        } finally {
            esconderLoading();
        }
    });
});
</script>
@endsection
