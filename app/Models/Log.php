<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'logs';

    protected $fillable = [
        'entidade',
        'entidade_id',
        'acao',
        'usuario_id',
        'usuario_nome',
        'mensagem',
        'dados_anteriores',
        'dados_novos',
    ];

    protected function casts(): array
    {
        return [
            'dados_anteriores' => 'array',
            'dados_novos' => 'array',
        ];
    }
}
