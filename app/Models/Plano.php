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
}
