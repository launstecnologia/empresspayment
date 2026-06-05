<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfilPermissao extends Model
{
    protected $table = 'perfis_permissao';

    protected $fillable = ['dono_id', 'nome', 'descricao', 'ativo'];

    public function modulos()
    {
        return $this->hasMany(PerfilModulo::class, 'perfil_id');
    }
}
