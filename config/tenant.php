<?php

return [
    /** Domínio base da plataforma (ex: expresspay.com.br) */
    'base_domain' => env('TENANT_BASE_DOMAIN', 'expresspay.com.br'),

    /** Hosts que representam o painel admin global (sem tenant) */
    'platform_hosts' => array_filter(array_map('trim', explode(',', env('TENANT_PLATFORM_HOSTS', 'localhost,127.0.0.1')))),

    /** Criar subdomínio no DirectAdmin ao cadastrar marketplace */
    'provision_subdomain' => (bool) env('TENANT_PROVISION_SUBDOMAIN', false),

    /** Em local: query string para simular tenant em localhost (ex: ?tenant=meu-slug) */
    'local_query' => env('TENANT_LOCAL_QUERY', 'tenant'),
];
