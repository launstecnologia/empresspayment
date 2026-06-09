<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plano extends Model
{
    protected $fillable = ['nome', 'codigo_fv', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function taxas()
    {
        return $this->hasMany(PlanoTaxa::class);
    }

    public function marketplaces()
    {
        return $this->belongsToMany(Usuario::class, 'marketplace_plano', 'plano_id', 'marketplace_id');
    }
}
