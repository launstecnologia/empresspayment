<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'tipo',
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
        'avatar_path',
        'password',
        'must_change_password',
        'ativo',
        'percentual_retencao_pai',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'ativo' => 'boolean',
            'percentual_retencao_pai' => 'decimal:2',
            'data_abertura' => 'date',
            'data_nascimento' => 'date',
            'rep_data_nascimento' => 'date',
        ];
    }

    public function hierarquia()
    {
        return $this->hasOne(Hierarquia::class, 'usuario_id');
    }

    public function filhos()
    {
        return $this->hasManyThrough(Usuario::class, Hierarquia::class, 'pai_id', 'id', 'id', 'usuario_id');
    }

    public function estabelecimentos()
    {
        return $this->hasMany(Estabelecimento::class, 'cadastrado_por_id');
    }

    public function marketplaceBranding()
    {
        return $this->hasOne(MarketplaceBranding::class, 'marketplace_id');
    }

    public function planosHabilitados()
    {
        return $this->belongsToMany(Plano::class, 'marketplace_plano', 'marketplace_id', 'plano_id');
    }

    public function subUsuarios()
    {
        return $this->hasMany(SubUsuario::class, 'dono_id');
    }

    public function nomeExibicao(): string
    {
        if ($this->tipo === 'admin') {
            return $this->nome_completo ?: $this->email;
        }

        return $this->nome_fantasia ?: $this->razao_social ?: $this->nome_completo ?: $this->email;
    }

    public function paiHierarquico(): ?Usuario
    {
        if (! $this->relationLoaded('hierarquia')) {
            $this->loadMissing('hierarquia.pai.usuario');
        } elseif ($this->hierarquia && ! $this->hierarquia->relationLoaded('pai')) {
            $this->hierarquia->loadMissing('pai.usuario');
        } elseif ($this->hierarquia?->pai && ! $this->hierarquia->pai->relationLoaded('usuario')) {
            $this->hierarquia->pai->loadMissing('usuario');
        }

        return $this->hierarquia?->pai?->usuario;
    }
}
