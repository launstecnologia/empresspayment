<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstabelecimentoEmail extends Model
{
    protected $table = 'estabelecimento_emails';

    protected $fillable = [
        'estabelecimento_id',
        'nome_email',
        'email_completo',
        'senha_criptografada',
        'redirecionamento_para',
        'imap_host',
        'imap_porta',
        'imap_ssl',
        'smtp_host',
        'smtp_porta',
        'smtp_ssl',
        'criado_automaticamente',
        'ativo',
        'ultimo_sync',
        'ultimo_erro_sync',
    ];

    protected $hidden = ['senha_criptografada'];

    protected function casts(): array
    {
        return [
            'imap_ssl' => 'boolean',
            'smtp_ssl' => 'boolean',
            'criado_automaticamente' => 'boolean',
            'ativo' => 'boolean',
            'ultimo_sync' => 'datetime',
            'senha_criptografada' => 'encrypted',
        ];
    }

    public function estabelecimento(): BelongsTo
    {
        return $this->belongsTo(Estabelecimento::class);
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(EmailCaixaEntrada::class, 'estabelecimento_email_id');
    }

    public function enviados(): HasMany
    {
        return $this->hasMany(EmailEnviado::class, 'estabelecimento_email_id');
    }

    public function senha(): ?string
    {
        return $this->senha_criptografada;
    }

    public function dominioCompleto(): ?string
    {
        $sub = $this->estabelecimento?->subdominio;

        if (! $sub) {
            return null;
        }

        return $sub.'.'.config('directadmin.dominio');
    }
}
