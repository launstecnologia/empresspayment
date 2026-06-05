<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubUsuarioModulo extends Model
{
    protected $table = 'sub_usuario_modulos';

    protected $fillable = ['sub_usuario_id', 'modulo', 'pode_ver', 'pode_editar'];
}
