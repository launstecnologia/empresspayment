<?php

namespace App\Support;

class EmailHtmlSanitizer
{
    private const TAGS = '<p><br><b><i><u><strong><em><a><ul><ol><li><table><thead><tbody><tr><td><th><img><div><span><h1><h2><h3><h4><blockquote><pre>';

    public static function limpar(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript:/i', '', $html) ?? $html;

        return strip_tags($html, self::TAGS);
    }
}
