<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEnviado extends Model
{
    protected $table = 'email_enviados';

    protected $fillable = [
        'estabelecimento_email_id',
        'para',
        'cc',
        'cco',
        'assunto',
        'corpo_html',
        'tem_anexo',
        'status',
        'erro',
        'resposta_ao_id',
    ];

    protected function casts(): array
    {
        return [
            'tem_anexo' => 'boolean',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(EstabelecimentoEmail::class, 'estabelecimento_email_id');
    }
}
