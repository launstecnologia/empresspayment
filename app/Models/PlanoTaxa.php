<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoTaxa extends Model
{
    protected $table = 'plano_taxas';

    protected $fillable = ['plano_id', 'instituicao', 'tipo_transacao', 'meio_pagamento_cod', 'arranjo_ur', 'parcelas', 'taxa_percentual', 'ativo'];

    protected function casts(): array
    {
        return [
            'taxa_percentual' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function plano()
    {
        return $this->belongsTo(Plano::class);
    }

    public function royalties()
    {
        return $this->hasMany(PlanoTaxaRoyalty::class);
    }
}
