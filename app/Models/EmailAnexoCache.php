<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAnexoCache extends Model
{
    protected $table = 'email_anexos_cache';

    protected $fillable = [
        'email_id',
        'nome_original',
        'nome_arquivo',
        'caminho',
        'mime_type',
        'tamanho_bytes',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(EmailCaixaEntrada::class, 'email_id');
    }
}
