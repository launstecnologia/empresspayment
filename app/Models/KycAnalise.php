<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycAnalise extends Model
{
    protected $fillable = [
        'estabelecimento_id',
        'status',
        'receita_consultado',
        'receita_situacao',
        'receita_nome',
        'receita_data_abertura',
        'receita_json',
        'receita_consultado_em',
        'score_risco',
        'risco_nivel',
        'admin_id',
        'admin_decisao',
        'admin_motivo',
        'admin_decidido_em',
        'tentativas_analise',
    ];

    protected function casts(): array
    {
        return [
            'receita_consultado' => 'boolean',
            'receita_data_abertura' => 'date',
            'receita_json' => 'array',
            'receita_consultado_em' => 'datetime',
            'admin_decidido_em' => 'datetime',
        ];
    }

    public function estabelecimento(): BelongsTo
    {
        return $this->belongsTo(Estabelecimento::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'admin_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(KycDocumento::class);
    }

    public function historico(): HasMany
    {
        return $this->hasMany(KycHistorico::class)->latest('created_at');
    }
}
