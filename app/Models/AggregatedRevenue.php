<?php

namespace App\Models;

use App\Scopes\HierarquiaScope;
use Illuminate\Database\Eloquent\Model;

class AggregatedRevenue extends Model
{
    protected $table = 'aggregated_revenue';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'total_valor' => 'decimal:2',
            'total_royalty' => 'decimal:2',
            'atualizado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new HierarquiaScope);
    }

    public function estabelecimento()
    {
        return $this->belongsTo(Estabelecimento::class);
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
}
