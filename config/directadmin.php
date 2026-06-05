<?php

return [
    'url' => env('DIRECTADMIN_URL'),
    'usuario' => env('DIRECTADMIN_USER'),
    'senha' => env('DIRECTADMIN_PASS'),
    'dominio' => env('DIRECTADMIN_DOMINIO'),
    'imap_host' => env('DIRECTADMIN_IMAP_HOST'),
    'imap_porta' => (int) env('DIRECTADMIN_IMAP_PORT', 993),
    'smtp_host' => env('DIRECTADMIN_SMTP_HOST'),
    'smtp_porta' => (int) env('DIRECTADMIN_SMTP_PORT', 587),
    'criar_email_ao_habilitar' => filter_var(env('DIRECTADMIN_AUTO_EMAIL', true), FILTER_VALIDATE_BOOL),
];
