<?php

namespace App\Models;

use App\Support\PlatformSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PpidUsoMensal extends Model
{
    protected $table = 'ppid_uso_mensal';

    protected $fillable = [
        'ano',
        'mes',
        'total',
        'limite',
    ];

    public static function registroAtual(): self
    {
        $ano = (int) now()->format('Y');
        $mes = (int) now()->format('n');
        $limite = PlatformSettings::ppidLimiteMensal();

        return self::query()->firstOrCreate(
            ['ano' => $ano, 'mes' => $mes],
            ['total' => 0, 'limite' => $limite],
        );
    }

    public static function limiteAtingido(): bool
    {
        if (! Schema::hasTable('ppid_uso_mensal')) {
            return false;
        }

        $registro = self::registroAtual();

        return $registro->total >= $registro->limite;
    }

    public static function incrementarUso(): void
    {
        $registro = self::registroAtual();
        $registro->increment('total');
    }
}
