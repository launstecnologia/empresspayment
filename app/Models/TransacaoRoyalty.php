<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransacaoRoyalty extends Model
{
    protected $table = 'transacao_royalties';

    protected $fillable = ['edi_movimento_id', 'usuario_id', 'nivel', 'percentual_royalty', 'valor_royalty'];

    protected function casts(): array
    {
        return [
            'percentual_royalty' => 'decimal:2',
            'valor_royalty' => 'decimal:2',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
