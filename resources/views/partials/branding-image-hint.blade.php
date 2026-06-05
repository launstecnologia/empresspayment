@props(['variant' => 'logo'])

@php
    $hint = match ($variant) {
        'logo' => [
            'size' => '400 × 120 px',
            'ratio' => 'proporção horizontal (até ~4:1)',
            'formats' => 'PNG, WebP ou SVG',
            'extra' => 'fundo transparente · até 4 MB',
            'usage' => 'Menu lateral e login (altura máx. ~80 px na tela)',
        ],
        'logo_white' => [
            'size' => '400 × 120 px',
            'ratio' => 'mesmas dimensões da logo padrão',
            'formats' => 'PNG, WebP ou SVG',
            'extra' => 'versão clara para fundo escuro · até 4 MB',
            'usage' => 'Painel azul do login (altura máx. ~128 px)',
        ],
        'favicon' => [
            'size' => '512 × 512 px',
            'ratio' => 'quadrado (1:1)',
            'formats' => 'PNG, WebP ou ICO',
            'extra' => 'fundo sólido ou transparente · até 2 MB',
            'usage' => 'Aba do navegador e ícone no celular',
        ],
        default => [],
    };
@endphp

<p class="mb-2 text-[10px] leading-relaxed text-gray-400 dark:text-gray-500">
    <span class="font-semibold text-gray-500 dark:text-gray-400">Recomendado:</span>
    {{ $hint['size'] }} ({{ $hint['ratio'] }})<br>
    {{ $hint['formats'] }} · {{ $hint['extra'] }}<br>
    <span class="italic">{{ $hint['usage'] }}</span>
</p>
