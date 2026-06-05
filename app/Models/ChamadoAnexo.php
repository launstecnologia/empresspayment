<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChamadoAnexo extends Model
{
    protected $table = 'chamado_anexos';

    protected $fillable = [
        'mensagem_id',
        'chamado_id',
        'nome_original',
        'nome_arquivo',
        'caminho',
        'mime_type',
        'tamanho_bytes',
        'extensao',
    ];

    public function chamado()
    {
        return $this->belongsTo(Chamado::class, 'chamado_id');
    }

    public function mensagem()
    {
        return $this->belongsTo(ChamadoMensagem::class, 'mensagem_id');
    }
}
