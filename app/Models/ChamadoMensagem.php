<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChamadoMensagem extends Model
{
    protected $table = 'chamado_mensagens';

    protected $fillable = ['chamado_id', 'autor_id', 'autor_tipo', 'autor_nome', 'mensagem', 'interno', 'visualizado'];

    protected function casts(): array
    {
        return [
            'interno' => 'boolean',
            'visualizado' => 'boolean',
        ];
    }

    public function chamado()
    {
        return $this->belongsTo(Chamado::class, 'chamado_id');
    }

    public function anexos()
    {
        return $this->hasMany(ChamadoAnexo::class, 'mensagem_id');
    }
}
