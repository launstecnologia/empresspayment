<?php

namespace App\Models;

use App\Scopes\HierarquiaScope;
use Illuminate\Database\Eloquent\Model;

class Estabelecimento extends Model
{
    protected $fillable = [
        'pessoa_tipo',
        'cnpj',
        'razao_social',
        'inscricao_estadual',
        'data_abertura',
        'cpf',
        'nome_completo',
        'data_nascimento',
        'nome_fantasia',
        'segmento',
        'faturamento_mensal',
        'rep_nome',
        'rep_cpf',
        'rep_data_nascimento',
        'cep',
        'endereco',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'telefone',
        'celular',
        'email',
        'token_pagseguro',
        'pagbank_account_id',
        'pagbank_access_token',
        'pagbank_refresh_token',
        'pagbank_token_expira',
        'pagbank_cadastrado_em',
        'pagbank_edi_ativo',
        'ip_cadastro',
        'fv_job_id',
        'fv_status',
        'fv_senha_6',
        'fv_erro',
        'fv_iniciado_em',
        'fv_concluido_em',
        'webmail_email',
        'webmail_senha',
        'plano_id',
        'subdominio',
        'documento_token_publico',
        'cadastrado_por_id',
        'cadastrado_por_nivel',
        'master_id',
        'marketplace_id',
        'revenda_id',
        'status',
        'risco',
        'anotacoes_interno',
        'anotacoes',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'pagbank_edi_ativo' => 'boolean',
            'pagbank_access_token' => 'encrypted',
            'pagbank_refresh_token' => 'encrypted',
            'pagbank_token_expira' => 'datetime',
            'pagbank_cadastrado_em' => 'datetime',
            'fv_iniciado_em' => 'datetime',
            'fv_concluido_em' => 'datetime',
            'webmail_senha' => 'encrypted',
            'data_abertura' => 'date',
            'data_nascimento' => 'date',
            'rep_data_nascimento' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new HierarquiaScope);
    }

    public function plano()
    {
        return $this->belongsTo(Plano::class);
    }

    public function cadastradoPor()
    {
        return $this->belongsTo(Usuario::class, 'cadastrado_por_id');
    }

    public function master()
    {
        return $this->belongsTo(Usuario::class, 'master_id');
    }

    public function marketplace()
    {
        return $this->belongsTo(Usuario::class, 'marketplace_id');
    }

    public function revenda()
    {
        return $this->belongsTo(Usuario::class, 'revenda_id');
    }

    public function royalties()
    {
        return $this->hasMany(EstabelecimentoRoyalty::class);
    }

    public function movimentos()
    {
        return $this->hasMany(EdiMovimento::class);
    }

    public function emails()
    {
        return $this->hasMany(EstabelecimentoEmail::class);
    }

    public function documentos()
    {
        return $this->hasMany(EstabelecimentoDocumento::class);
    }

    public function kycAnalise()
    {
        return $this->hasOne(KycAnalise::class);
    }

    public function pagbankLogs()
    {
        return $this->hasMany(PagbankLog::class);
    }
}
