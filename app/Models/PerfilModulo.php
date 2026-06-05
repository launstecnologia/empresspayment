<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfilModulo extends Model
{
    protected $table = 'perfil_modulos';

    protected $fillable = ['perfil_id', 'modulo', 'pode_ver', 'pode_editar'];
}
