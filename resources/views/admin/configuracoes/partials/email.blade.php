{{-- E-mail (SMTP) --}}
<div x-show="aba === 'email'" x-cloak class="space-y-6">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Servidor SMTP para envio de e-mails automáticos da plataforma.
    </p>

    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
        <p class="font-semibold">Templates de notificação</p>
        <p class="mt-1 text-blue-800 dark:text-blue-300">
            Cadastro, KYC, PagBank, chamados e outros e-mails automáticos são editáveis em
            <a href="{{ route('admin.email-templates.index') }}" class="font-semibold underline hover:text-blue-600">Templates E-mail</a>.
        </p>
    </div>


    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Driver</span>
            <select name="mail_mailer" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                @foreach (['smtp' => 'SMTP (produção)', 'log' => 'Log (testes — grava em storage/logs)', 'array' => 'Array (não envia)'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('mail_mailer', $config->mail_mailer ?? 'smtp') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Host SMTP</span>
            <input type="text" name="mail_host" value="{{ old('mail_host', $config->mail_host) }}" placeholder="smtp.seudominio.com.br" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </label>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Porta</span>
            <input type="number" name="mail_port" value="{{ old('mail_port', $config->mail_port ?? 587) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </label>
        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Criptografia</span>
            <select name="mail_encryption" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="tls" @selected(old('mail_encryption', $config->mail_encryption ?? 'tls') === 'tls')>TLS</option>
                <option value="ssl" @selected(old('mail_encryption', $config->mail_encryption) === 'ssl')>SSL</option>
                <option value="" @selected(old('mail_encryption', $config->mail_encryption) === null || old('mail_encryption', $config->mail_encryption) === '')>Nenhuma</option>
            </select>
        </label>
        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Usuário SMTP</span>
            <input type="text" name="mail_username" value="{{ old('mail_username', $config->mail_username) }}" autocomplete="off" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </label>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Senha SMTP</span>
            <input type="password" name="mail_password" value="" placeholder="{{ $config->mail_password ? '•••••••• (deixe em branco para manter)' : 'Senha do servidor SMTP' }}" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Remetente (e-mail)</span>
                <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $config->mail_from_address) }}" placeholder="noreply@express.com.br" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
            </label>
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nome do remetente</span>
                <input type="text" name="mail_from_name" value="{{ old('mail_from_name', $config->mail_from_name) }}" placeholder="Express Payments" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
            </label>
        </div>
    </div>

</div>
