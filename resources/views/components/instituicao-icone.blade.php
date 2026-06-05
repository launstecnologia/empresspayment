@props([
    'codigo' => null,
    'size' => 'md',
])

@php
    $iconUrl = \App\Support\InstituicaoFinanceira::iconUrl($codigo);
    $nome = \App\Support\InstituicaoFinanceira::nome($codigo);
    $imgClass = match ($size) {
        'sm' => 'h-5 w-5',
        'lg' => 'h-7 w-7',
        default => 'h-6 w-6',
    };
    $boxClass = match ($size) {
        'sm' => 'h-8 w-8',
        'lg' => 'h-10 w-10',
        default => 'h-9 w-9',
    };
@endphp

<span
    {{ $attributes->merge(['class' => "inline-flex {$boxClass} shrink-0 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-white dark:ring-gray-500"]) }}
    title="{{ $nome }}"
>
    @if ($iconUrl)
        <img
            src="{{ $iconUrl }}"
            alt="{{ $nome }}"
            class="{{ $imgClass }} object-contain"
            width="28"
            height="28"
            loading="lazy"
            decoding="async"
            onerror="this.hidden=true; this.nextElementSibling?.classList.remove('hidden'); this.nextElementSibling?.classList.add('inline-flex')"
        >
        <span class="hidden {{ $imgClass }} inline-flex items-center justify-center rounded bg-gray-100 text-[10px] font-bold uppercase text-gray-600">
            {{ \Illuminate\Support\Str::substr($nome, 0, 2) }}
        </span>
    @else
        <i class="fa-solid fa-credit-card text-sm text-gray-500"></i>
    @endif
</span>
