<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomacaoLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'estabelecimento_id',
        'job_id',
        'nivel',
        'etapa',
        'mensagem',
        'detalhe',
        'origem',
        'origem_ref',
    ];

    protected function casts(): array
    {
        return [
            'detalhe' => 'array',
        ];
    }

    public function estabelecimento(): BelongsTo
    {
        return $this->belongsTo(Estabelecimento::class);
    }
}
