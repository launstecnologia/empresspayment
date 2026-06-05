<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChamadoHistorico extends Model
{
    protected $table = 'chamado_historico';

    protected $fillable = ['chamado_id', 'autor_id', 'autor_nome', 'acao', 'valor_anterior', 'valor_novo'];

    public function chamado()
    {
        return $this->belongsTo(Chamado::class, 'chamado_id');
    }
}
