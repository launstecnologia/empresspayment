<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstabelecimentoRoyalty extends Model
{
    protected $table = 'estabelecimento_royalties';

    protected $fillable = [
        'estabelecimento_id',
        'plano_taxa_id',
        'usuario_id',
        'nivel',
        'percentual_recebe',
        'percentual_repassa',
        'percentual_royalty',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'percentual_recebe' => 'decimal:2',
            'percentual_repassa' => 'decimal:2',
            'percentual_royalty' => 'decimal:2',
        ];
    }
}
