<div x-show="aba === 'kyc'" x-cloak class="space-y-5">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Configurações do módulo KYC (Know Your Customer) e integração com OpenAI Vision para análise de documentos.
    </p>

    <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
        <input type="hidden" name="kyc_ativo" value="0">
        <input type="checkbox" name="kyc_ativo" value="1" @checked(old('kyc_ativo', $config->kyc_ativo ?? true)) class="h-4 w-4 rounded border-gray-300 text-blue-600">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Módulo KYC ativo</span>
    </label>

    <label class="block space-y-1">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Token OpenAI (API Key)</span>
        <input
            type="password"
            name="openai_api_key"
            value=""
            autocomplete="new-password"
            placeholder="{{ $openaiConfigurado ? '••••••••  (deixe em branco para manter o atual)' : 'sk-...' }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        >
        <span class="text-xs text-gray-400">
            Usado na análise automática de documentos (GPT-4o Vision). Também pode ser definido em <code class="text-xs">OPENAI_API_KEY</code> no .env.
        </span>
        @if ($openaiConfigurado)
            <span class="inline-flex items-center gap-1 text-xs font-semibold text-green-600">
                <i class="fa-solid fa-circle-check"></i> Token configurado
            </span>
        @else
            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600">
                <i class="fa-solid fa-triangle-exclamation"></i> Nenhum token configurado — documentos irão para revisão manual
            </span>
        @endif
    </label>

    <label class="block max-w-md space-y-1">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Modelo OpenAI</span>
        <input
            type="text"
            name="openai_modelo"
            value="{{ old('openai_modelo', $config->openai_modelo ?: 'gpt-4o') }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        >
        <span class="text-xs text-gray-400">Padrão: gpt-4o (suporta visão)</span>
    </label>

    <label class="block space-y-1">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">URL BrasilAPI (Receita / CNPJ)</span>
        <input
            type="url"
            name="brasilapi_url"
            value="{{ old('brasilapi_url', $config->brasilapi_url ?: 'https://brasilapi.com.br/api') }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        >
    </label>
</div>
