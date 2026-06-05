<?php

return [
    'name' => env('APP_NAME', 'Express Payments'),
    'description' => env('APP_DESCRIPTION', 'Plataforma Express Payments para gestão de estabelecimentos, faturamento EDI PagBank, comissões e operações de maquininhas.'),
    'keywords' => env('APP_KEYWORDS', 'Express Payments, PagBank, maquininha, faturamento, comissões, EDI, gestão de pagamentos'),
    'robots' => env('APP_ROBOTS', 'noindex, nofollow'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
    'locale' => env('APP_LOCALE', 'pt_BR'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'pt_BR'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'pt_BR'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
