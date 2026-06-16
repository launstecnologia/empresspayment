<?php

return [
    'edi_url' => env('PAGBANK_EDI_URL', 'https://edi.api.pagbank.com.br'),
    'edi_user' => env('PAGBANK_EDI_USER'),
    'edi_token' => env('PAGBANK_EDI_TOKEN'),
    'movimentos' => ['transactional', 'financial', 'cashouts', 'balances'],
    'pagina_limite' => 1000,
];
