<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCaixaEntrada extends Model
{
    protected $table = 'email_caixa_entrada';

    protected $fillable = [
        'estabelecimento_email_id',
        'uid',
        'message_id',
        'pasta',
        'de_nome',
        'de_email',
        'para',
        'cc',
        'assunto',
        'corpo_texto',
        'corpo_html',
        'tem_anexo',
        'tamanho_bytes',
        'lido',
        'respondido',
        'encaminhado',
        'favorito',
        'spam',
        'deletado',
        'thread_id',
        'data_email',
    ];

    protected function casts(): array
    {
        return [
            'tem_anexo' => 'boolean',
            'lido' => 'boolean',
            'respondido' => 'boolean',
            'encaminhado' => 'boolean',
            'favorito' => 'boolean',
            'spam' => 'boolean',
            'deletado' => 'boolean',
            'data_email' => 'datetime',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(EstabelecimentoEmail::class, 'estabelecimento_email_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(EmailAnexoCache::class, 'email_id');
    }

    public function corpoSeguro(): string
    {
        return \App\Support\EmailHtmlSanitizer::limpar($this->corpo_html ?: nl2br(e($this->corpo_texto ?? '')));
    }
}
