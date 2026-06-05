<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailNotificacaoLog extends Model
{
    protected $table = 'email_notificacoes_log';

    protected $fillable = [
        'template_slug',
        'destinatario',
        'assunto',
        'status',
        'erro',
    ];
}
