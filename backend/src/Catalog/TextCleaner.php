<?php

namespace App\Catalog;

/**
 * Catalogs return HTML snippets: keep plain text only (rendered as text by the client).
 */
final class TextCleaner
{
    public static function plain(?string $html, int $maxLength = 4000): ?string
    {
        if (null === $html) {
            return null;
        }

        $text = preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));

        if ('' === $text) {
            return null;
        }

        return mb_strlen($text) > $maxLength ? rtrim(mb_substr($text, 0, $maxLength - 1)).'…' : $text;
    }
}
