<?php

return [
    /** Exibe conta e mensagens de demonstração para validar o layout do cliente de e-mail. */
    'demo_layout' => filter_var(env('EMAIL_DEMO_LAYOUT', env('APP_ENV') === 'local'), FILTER_VALIDATE_BOOL),
];
