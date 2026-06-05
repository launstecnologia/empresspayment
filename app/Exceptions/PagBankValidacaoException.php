<?php

namespace App\Exceptions;

use Exception;

class PagBankValidacaoException extends Exception
{
    /**
     * @param  array<int, string>  $erros
     */
    public function __construct(
        public readonly array $erros,
        string $message = 'Dados insuficientes para cadastro PagBank.',
    ) {
        parent::__construct($message);
    }
}
