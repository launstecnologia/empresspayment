<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Segmento extends Model
{
    protected $fillable = ['nome', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
