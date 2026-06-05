<?php

return [
    'token' => env('PAGBANK_TOKEN'),
    'client_id' => env('PAGBANK_CLIENT_ID'),
    'client_secret' => env('PAGBANK_CLIENT_SECRET'),
    'ambiente' => env('PAGBANK_AMBIENTE', 'producao'),
    'api_url' => env('PAGBANK_API_URL'),
    'pipefy_edi_url' => env('PAGBANK_PIPEFY_EDI_URL', 'https://app.pipefy.com/organizations/142456/interfaces/3668b8ed-d930-4bcf-8038-8a00d3ed6901'),
    'renovacao_dias_antecedencia' => (int) env('PAGBANK_RENOVACAO_DIAS', 7),
];
