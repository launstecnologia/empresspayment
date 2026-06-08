<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocumento extends Model
{
    public const TIPOS = [
        'rg_frente',
        'rg_verso',
        'cnh_frente',
        'cnh_verso',
        'comprovante_endereco',
        'contrato_social',
        'cartao_cnpj',
        'selfie_documento',
    ];

    protected $fillable = [
        'kyc_analise_id',
        'estabelecimento_id',
        'estabelecimento_documento_id',
        'tipo',
        'nome_original',
        'caminho',
        'mime_type',
        'tamanho_bytes',
        'enviado_por_id',
        'enviado_por_tipo',
        'openai_status',
        'openai_dados_extraidos',
        'openai_motivo_reprovacao',
        'openai_confianca',
        'openai_tokens_usados',
        'openai_modelo',
        'openai_analisado_em',
        'ppid_consulta_id',
        'cruzamento_status',
        'cruzamento_divergencias',
        'admin_override',
        'admin_override_motivo',
    ];

    protected function casts(): array
    {
        return [
            'openai_dados_extraidos' => 'array',
            'cruzamento_divergencias' => 'array',
            'openai_analisado_em' => 'datetime',
        ];
    }

    public function kycAnalise(): BelongsTo
    {
        return $this->belongsTo(KycAnalise::class);
    }

    public function estabelecimento(): BelongsTo
    {
        return $this->belongsTo(Estabelecimento::class);
    }

    public function estabelecimentoDocumento(): BelongsTo
    {
        return $this->belongsTo(EstabelecimentoDocumento::class);
    }

    public function usaDiscoPublico(): bool
    {
        return (bool) $this->estabelecimento_documento_id
            || str_starts_with($this->caminho, 'estabelecimentos/');
    }

    public function statusEfetivo(): string
    {
        if ($this->admin_override) {
            return $this->admin_override === 'aprovado' ? 'aprovado' : 'reprovado';
        }

        return $this->openai_status;
    }
}
