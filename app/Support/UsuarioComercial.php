<?php

namespace App\Support;

use App\Models\SubUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

class UsuarioComercial
{
    public static function principal(): ?Usuario
    {
        $user = auth()->user();

        if ($user instanceof SubUsuario) {
            return $user->dono;
        }

        return $user instanceof Usuario ? $user : null;
    }

    public static function tipo(): ?string
    {
        return self::principal()?->tipo;
    }

    public static function ehAdmin(): bool
    {
        return self::tipo() === 'admin';
    }

    public static function ehMarketplace(): bool
    {
        return self::tipo() === 'marketplace';
    }

    public static function podeCadastrarEstabelecimento(): bool
    {
        return in_array(self::tipo(), ['admin', 'marketplace', 'revenda'], true);
    }

    public static function podeDefinirRetencaoPai(string $tipoFilho): bool
    {
        if ($tipoFilho === 'marketplace') {
            return self::ehAdmin();
        }

        if ($tipoFilho === 'revenda') {
            return self::ehAdmin() || self::ehMarketplace();
        }

        return false;
    }

    public static function podeGerenciar(Usuario $alvo): bool
    {
        $actor = self::principal();

        if (! $actor) {
            return false;
        }

        if ($actor->tipo === 'admin') {
            return true;
        }

        if ($actor->tipo === 'marketplace') {
            if ((int) $alvo->id === (int) $actor->id) {
                return true;
            }

            if ($alvo->tipo === 'revenda') {
                return self::revendasDo($actor)->whereKey($alvo->id)->exists();
            }

            return false;
        }

        if ($actor->tipo === 'master') {
            if ((int) $alvo->id === (int) $actor->id) {
                return true;
            }

            return self::pertenceAoMaster($alvo, $actor);
        }

        return false;
    }

    public static function revendasDo(Usuario $marketplace): Builder
    {
        $noId = $marketplace->hierarquia?->id;

        return Usuario::query()
            ->where('tipo', 'revenda')
            ->when(
                $noId,
                fn (Builder $q) => $q->whereHas('hierarquia', fn (Builder $h) => $h->where('pai_id', $noId)),
                fn (Builder $q) => $q->whereRaw('1 = 0')
            );
    }

    public static function tipoListaPermitido(?string $tipo): bool
    {
        if (self::ehAdmin()) {
            return $tipo === null || in_array($tipo, ['master', 'marketplace', 'revenda'], true);
        }

        if (self::ehMarketplace()) {
            return $tipo === 'revenda';
        }

        if (self::tipo() === 'master') {
            return $tipo === null || in_array($tipo, ['marketplace', 'revenda'], true);
        }

        return false;
    }

    private static function pertenceAoMaster(Usuario $alvo, Usuario $master): bool
    {
        $alvo->loadMissing('hierarquia.pai.usuario');

        $atual = $alvo->hierarquia?->pai?->usuario;

        for ($i = 0; $i < 5 && $atual; $i++) {
            if ((int) $atual->id === (int) $master->id) {
                return true;
            }

            $atual->loadMissing('hierarquia.pai.usuario');
            $atual = $atual->hierarquia?->pai?->usuario;
        }

        return false;
    }
}
