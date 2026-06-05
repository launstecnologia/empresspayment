<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class EmailTemplate extends Model
{
    protected $fillable = [
        'slug',
        'nome',
        'categoria',
        'assunto',
        'corpo',
        'botao_texto',
        'ativo',
        'placeholders_ajuda',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public static function porSlug(string $slug): ?self
    {
        return Cache::remember("email_template.{$slug}", 3600, fn () => self::query()
            ->where('slug', $slug)
            ->first());
    }

    public static function esquecerCache(?string $slug = null): void
    {
        if ($slug) {
            Cache::forget("email_template.{$slug}");

            return;
        }

        self::query()->pluck('slug')->each(fn (string $s) => Cache::forget("email_template.{$s}"));
    }

    protected static function booted(): void
    {
        static::saved(fn (self $template) => self::esquecerCache($template->slug));
        static::deleted(fn (self $template) => self::esquecerCache($template->slug));
    }
}
