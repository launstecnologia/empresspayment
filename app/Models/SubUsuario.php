<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class SubUsuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'sub_usuarios';

    protected $fillable = ['dono_id', 'dono_tipo', 'nome', 'email', 'avatar_path', 'password', 'perfil_id', 'ativo'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'ativo' => 'boolean'];
    }

    public function getAuthIdentifier()
    {
        return 'sub:'.$this->getKey();
    }

    public function getTipoAttribute(): string
    {
        return $this->dono_tipo;
    }

    public function nomeExibicao(): string
    {
        return $this->nome;
    }

    public function dono()
    {
        return $this->belongsTo(Usuario::class, 'dono_id');
    }

    public function perfil()
    {
        return $this->belongsTo(PerfilPermissao::class, 'perfil_id');
    }

    public function modulos()
    {
        return $this->hasMany(SubUsuarioModulo::class, 'sub_usuario_id');
    }
}
