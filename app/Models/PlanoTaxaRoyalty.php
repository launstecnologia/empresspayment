<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoTaxaRoyalty extends Model
{
    protected $table = 'plano_taxa_royalties';

    protected $fillable = ['plano_taxa_id', 'usuario_id', 'nivel', 'percentual'];

    protected function casts(): array
    {
        return ['percentual' => 'decimal:2'];
    }

    public function taxa()
    {
        return $this->belongsTo(PlanoTaxa::class, 'plano_taxa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
