<?php

namespace App\Text;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Text written with the editor (news, event descriptions): HTML limited to formatting and links,
 * cleaned before being saved. Links are shown as text, or as a button with class="button".
 */
final readonly class RichText
{
    public const BUTTON_CLASS = 'button';

    public function __construct(
        #[Target('app.rich_text_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    /** Clean HTML, or null when nothing is written. Text without markup is taken as plain text. */
    public function clean(?string $input): ?string
    {
        $input = trim((string) $input);
        if ('' === $input) {
            return null;
        }
        if (!str_contains($input, '<')) {
            $input = self::fromPlainText($input);
        }

        $html = $this->sanitizer->sanitize($input);
        // The only class a link may carry
        $html = (string) preg_replace_callback(
            '/<a\b[^>]*>/i',
            static fn (array $m): string => (string) preg_replace('/\sclass="(?!'.self::BUTTON_CLASS.'")[^"]*"/i', '', $m[0]),
            $html,
        );
        // Empty paragraphs left by the editor at the end
        $html = trim((string) preg_replace('#(<p>(\s|&nbsp;|<br\s*/?>)*</p>\s*)+$#i', '', $html));

        return '' === trim(self::plain($html)) ? null : $html;
    }

    /** The words only, paragraphs on separate lines: for lengths, excerpts and plain-text e-mails. */
    public static function plain(?string $html): string
    {
        $text = (string) preg_replace('#<br\s*/?>|</(p|h2|h3|li|blockquote)>#i', "\n", (string) $html);
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t\u{a0}]+/u", ' ', $text);

        return trim((string) preg_replace("/\n\\s*\n+/", "\n\n", $text));
    }

    /** Plain text (before the editor existed): paragraphs on blank lines, line breaks kept. */
    public static function fromPlainText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $paragraphs = array_filter(array_map('trim', preg_split("/\n{2,}/", $text) ?: []), static fn (string $p): bool => '' !== $p);

        return implode('', array_map(
            static fn (string $p): string => '<p>'.str_replace("\n", '<br>', htmlspecialchars($p, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')).'</p>',
            $paragraphs,
        ));
    }

    /** For e-mails: buttons styled inline, as mail clients ignore the site's styles. */
    public static function forEmail(?string $html): string
    {
        return (string) preg_replace(
            '/<a\b([^>]*)\sclass="'.self::BUTTON_CLASS.'"([^>]*)>/i',
            '<a$1$2 style="display:inline-block;padding:8px 16px;background:#a3171d;color:#fbf7ee;text-decoration:none;border-radius:4px;font-weight:bold;">',
            (string) $html,
        );
    }
}
