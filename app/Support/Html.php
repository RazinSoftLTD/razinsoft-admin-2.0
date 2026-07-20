<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Allow-list sanitiser for the small amount of rich text the admin editors produce.
 * Anything not on the list is unwrapped or dropped, so stored HTML is safe to print raw.
 */
class Html
{
    /** Tags we keep. Everything else is unwrapped (its text survives). */
    private const ALLOWED = ['p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'a', 'h3', 'h4', 'blockquote', 'code', 'pre'];

    /** Attributes we keep, per tag. */
    private const ATTRS = ['a' => ['href', 'target', 'rel']];

    public static function clean(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }
        if (trim(strip_tags($html)) === '' && ! str_contains($html, '<br')) {
            return null;   // an "empty" editor still posts <p><br></p>
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        // Walk deepest-first so unwrapping a parent cannot skip its children.
        foreach (iterator_to_array($xpath->query('//*')) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->nodeName);

            if ($tag === 'body') {
                continue;
            }

            // Script-ish nodes go entirely — keeping their text would be pointless noise.
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                $node->parentNode?->removeChild($node);

                continue;
            }

            if (! in_array($tag, self::ALLOWED, true)) {
                self::unwrap($node);

                continue;
            }

            foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                $keep = self::ATTRS[$tag] ?? [];
                if (! in_array(strtolower($attr->nodeName), $keep, true)) {
                    $node->removeAttribute($attr->nodeName);
                }
            }

            if ($tag === 'a') {
                $href = trim((string) $node->getAttribute('href'));
                // Only plain web/mail links — no javascript:, data:, vbscript: …
                if (! preg_match('#^(https?://|mailto:|/)#i', $href)) {
                    $node->removeAttribute('href');
                } else {
                    $node->setAttribute('target', '_blank');
                    $node->setAttribute('rel', 'noopener nofollow');
                }
            }
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        $out = '';
        foreach ($body?->childNodes ?? [] as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out) ?: null;
    }

    /** Replace a node with its children. */
    private static function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }
}
