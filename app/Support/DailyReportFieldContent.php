<?php

namespace App\Support;

/**
 * Rich-text helpers for the daily report form (Tiptap HTML).
 *
 * Two responsibilities:
 *  - {@see hasMeaningfulText()} — emptiness check used by submit gating.
 *  - {@see sanitize()} / {@see sanitizePayload()} — server-side allowlist
 *    sanitizer. The five report fields are rendered with `v-html` in
 *    Show.vue / Review.vue, so a member could otherwise store an XSS payload
 *    that runs inside a reviewer (lead/admin) session at scoring time.
 *    Client-side Tiptap output is never trusted.
 */
final class DailyReportFieldContent
{
    /** Report content keys that hold user-authored HTML. */
    public const CONTENT_KEYS = [
        'goals_today',
        'progress_update',
        'blockers',
        'improvement_suggestions',
        'plan_tomorrow',
    ];

    /** Tags Tiptap may legitimately emit. Everything else is unwrapped. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins',
        'mark', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a', 'code', 'pre',
    ];

    /** Tags dropped together with their text content (never unwrapped). */
    private const DROP_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form'];

    /** Attribute allowlist, keyed by tag. Every other attribute is stripped. */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'target', 'rel'],
    ];

    public static function hasMeaningfulText(?string $html): bool
    {
        if ($html === null || $html === '') {
            return false;
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = trim($text);

        return $text !== '';
    }

    /**
     * Strip every tag/attribute outside the allowlist. Disallowed formatting
     * tags are unwrapped (text kept); script/style/etc. are removed wholesale.
     * Text nodes are preserved and HTML-escaped on serialization, so injected
     * markup can never round-trip back into executable HTML.
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML PI forces UTF-8; the wrapper div gives loadHTML a single root.
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return '';
        }

        self::cleanNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Sanitize every report content key present in a use-case payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizePayload(array $data): array
    {
        foreach (self::CONTENT_KEYS as $key) {
            if (array_key_exists($key, $data) && (is_string($data[$key]) || $data[$key] === null)) {
                $data[$key] = self::sanitize($data[$key]);
            }
        }

        return $data;
    }

    private static function cleanNode(\DOMNode $node): void
    {
        // Snapshot the live NodeList so removals/unwraps don't disturb iteration.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof \DOMElement) {
                continue; // text nodes are kept (escaped on save)
            }

            $tag = strtolower($child->tagName);

            // Clean descendants first so unwrapped children are already safe.
            self::cleanNode($child);

            if (in_array($tag, self::DROP_TAGS, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        $names = [];
        foreach ($el->attributes as $attr) {
            $names[] = $attr->nodeName;
        }

        foreach ($names as $name) {
            if (! in_array(strtolower($name), $allowed, true)) {
                $el->removeAttribute($name);

                continue;
            }
            if (strtolower($name) === 'href' && ! self::isSafeUrl(trim($el->getAttribute('href')))) {
                $el->removeAttribute('href');
            }
        }

        // Harden surviving links against tab-nabbing / SEO abuse.
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener nofollow ugc');
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        // Only absolute http(s) and mailto links; blocks javascript:, data:, vbscript:.
        return $url !== '' && preg_match('/^(https?:|mailto:)/i', $url) === 1;
    }
}
