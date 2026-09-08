<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EdiReprocessamento extends Model
{
    protected $table = 'edi_reprocessamentos';

    protected $fillable = [
        'status',
        'periodo_de',
        'periodo_ate',
        'entrada_tipo',
        'total_itens',
        'processados',
        'total_dias',
        'total_movimentos',
        'total_cancelamentos',
        'total_erros',
        'iniciado_por_id',
        'iniciado_por_nome',
        'entradas',
        'resultado',
        'erro',
        'iniciado_em',
        'finalizado_em',
    ];

    protected function casts(): array
    {
        return [
            'periodo_de' => 'date',
            'periodo_ate' => 'date',
            'entradas' => 'array',
            'resultado' => 'array',
            'iniciado_em' => 'datetime',
            'finalizado_em' => 'datetime',
        ];
    }
}
