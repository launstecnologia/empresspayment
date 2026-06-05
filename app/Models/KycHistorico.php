<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycHistorico extends Model
{
    protected $table = 'kyc_historico';

    public $timestamps = false;

    protected $fillable = [
        'kyc_analise_id',
        'evento',
        'descricao',
        'dados',
        'autor_id',
        'autor_nome',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function kycAnalise(): BelongsTo
    {
        return $this->belongsTo(KycAnalise::class);
    }
}
