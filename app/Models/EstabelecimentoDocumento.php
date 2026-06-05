<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstabelecimentoDocumento extends Model
{
    protected $table = 'estabelecimento_documentos';

    protected $fillable = [
        'estabelecimento_id',
        'tipo_documento',
        'arquivo_path',
        'arquivo_nome',
        'token_publico',
        'token_expira_em',
    ];

    protected function casts(): array
    {
        return ['token_expira_em' => 'datetime'];
    }

    public function estabelecimento(): BelongsTo
    {
        return $this->belongsTo(Estabelecimento::class);
    }
}
