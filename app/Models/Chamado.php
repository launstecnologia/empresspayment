<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chamado extends Model
{
    protected $table = 'chamados';

    protected $fillable = [
        'aberto_por_id',
        'aberto_por_tipo',
        'aberto_por_nivel',
        'master_id',
        'marketplace_id',
        'revenda_id',
        'titulo',
        'categoria',
        'prioridade',
        'status',
        'numero',
        'visualizado_admin',
        'avaliacao',
        'avaliacao_comentario',
        'fechado_em',
    ];

    protected function casts(): array
    {
        return [
            'visualizado_admin' => 'boolean',
            'fechado_em' => 'datetime',
        ];
    }

    public function mensagens()
    {
        return $this->hasMany(ChamadoMensagem::class, 'chamado_id');
    }

    public function anexos()
    {
        return $this->hasMany(ChamadoAnexo::class, 'chamado_id');
    }

    public function historicos()
    {
        return $this->hasMany(ChamadoHistorico::class, 'chamado_id');
    }

    public function master()
    {
        return $this->belongsTo(Usuario::class, 'master_id');
    }

    public function marketplace()
    {
        return $this->belongsTo(Usuario::class, 'marketplace_id');
    }

    public function revenda()
    {
        return $this->belongsTo(Usuario::class, 'revenda_id');
    }

    public function abertoPorUsuario()
    {
        return $this->belongsTo(Usuario::class, 'aberto_por_id');
    }
}
