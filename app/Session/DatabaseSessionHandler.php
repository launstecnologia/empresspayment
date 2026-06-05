<?php

namespace App\Session;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Session\DatabaseSessionHandler as BaseDatabaseSessionHandler;

class DatabaseSessionHandler extends BaseDatabaseSessionHandler
{
    /**
     * Sub-usuários usam identificador "sub:{id}" — não cabe em user_id (bigint).
     * A autenticação fica no payload da sessão; user_id só para Usuario numérico.
     */
    protected function addUserInformation(&$payload)
    {
        if ($this->container->bound(Guard::class)) {
            $payload['user_id'] = $this->userId();
        }

        return $this;
    }

    protected function userId()
    {
        $id = parent::userId();

        return is_numeric($id) ? (int) $id : null;
    }
}
