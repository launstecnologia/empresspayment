<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagbankLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'estabelecimento_id',
        'tipo',
        'endpoint',
        'metodo',
        'request_body',
        'response_status',
        'response_body',
        'sucesso',
        'erro',
        'duracao_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_body' => 'array',
            'response_body' => 'array',
            'sucesso' => 'boolean',
        ];
    }

    public function estabelecimento(): BelongsTo
    {
        return $this->belongsTo(Estabelecimento::class);
    }
}
