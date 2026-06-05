<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hierarquia extends Model
{
    protected $table = 'hierarquia';

    protected $fillable = ['usuario_id', 'pai_id', 'nivel'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function pai()
    {
        return $this->belongsTo(self::class, 'pai_id');
    }

    public function filhos()
    {
        return $this->hasMany(self::class, 'pai_id');
    }
}
