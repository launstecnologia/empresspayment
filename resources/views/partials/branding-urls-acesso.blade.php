@props([
    'urls' => [],
    'slug' => '',
])

@php
    $ehLocal = $urls['ehLocal'] ?? false;
    $local = $urls['local'] ?? '';
    $producao = $urls['producao'] ?? '';
    $param = config('tenant.local_query', 'tenant');
    $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
@endphp

@if ($ehLocal)
    <div class="space-y-3">
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            <p class="font-semibold">URL para testar (local)</p>
            <a href="{{ $local }}" target="_blank" rel="noopener" class="mt-1 block break-all font-mono underline">{{ $local }}</a>
            <p class="mt-2 opacity-90">
                Use este link no <strong>{{ $appHost }}</strong>. O parâmetro <code class="rounded bg-white/60 px-1">{{ $param }}={{ $slug }}</code> ativa a marca; fica salvo na sessão enquanto navegar.
            </p>
        </div>
        <div class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
            <p class="font-semibold">URL em produção</p>
            <p class="mt-1 break-all font-mono">{{ $producao }}</p>
            <p class="mt-2 opacity-90">Domínio real após publicar (subdomínio ou domínio personalizado verificado).</p>
        </div>
    </div>
@else
    <div class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
        <p class="font-semibold">URL de acesso</p>
        <a href="{{ $producao }}" target="_blank" rel="noopener" class="mt-1 block break-all font-mono underline">{{ $producao }}</a>
    </div>
@endif
