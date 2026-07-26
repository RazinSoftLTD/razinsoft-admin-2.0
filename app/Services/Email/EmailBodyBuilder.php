<?php

namespace App\Services\Email;

use App\Models\EmailLog;

/**
 * Turns a template body into what actually goes down the wire.
 *
 * Two jobs, both about landing in the inbox rather than the spam folder:
 *  - produce a genuine plain-text alternative for the multipart message
 *  - add the tracking pixel and rewrite links, without breaking the HTML
 */
class EmailBodyBuilder
{
    /**
     * A readable plain-text version of an HTML body.
     *
     * Not just strip_tags: block elements become line breaks, list items get a bullet, and links
     * keep their target in brackets — a text part that reads like nonsense is itself a spam signal.
     */
    public static function toPlainText(string $html): string
    {
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;

        // Keep where a link pointed: "Pay now (https://…)".
        $text = preg_replace_callback(
            '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            function ($m) {
                $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $href = trim($m[1]);

                if ($href === '' || str_starts_with($href, 'mailto:') || $label === $href) {
                    return $label ?: $href;
                }

                return $label === '' ? $href : "{$label} ({$href})";
            },
            $text,
        ) ?? $text;

        $text = preg_replace('/<li\b[^>]*>/i', "\n  • ", $text) ?? $text;
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/(p|div|tr|h[1-6]|ul|ol|li|table)>/i', "\n", $text) ?? $text;

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);                  // non-breaking spaces
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;     // at most one blank line

        return trim($text);
    }

    /**
     * Add open tracking and click tracking to a body.
     *
     * The pixel goes last so a mail client that stops rendering early still shows the message, and
     * it carries alt="" so screen readers skip it. Links are rewritten to a redirect that records
     * the click and forwards on; anchors, mailto: and already-tracked links are left alone.
     */
    public static function withTracking(string $html, EmailLog $log): string
    {
        $html = self::rewriteLinks($html, $log);

        $pixel = '<img src="'.e(route('email.track.open', $log->tracking_id)).'" alt="" width="1" height="1" '
            .'style="display:block;width:1px;height:1px;border:0;outline:none" />';

        // Inside </body> when there is one, so the markup stays valid.
        return str_contains($html, '</body>')
            ? str_ireplace('</body>', $pixel.'</body>', $html)
            : $html.$pixel;
    }

    private static function rewriteLinks(string $html, EmailLog $log): string
    {
        return preg_replace_callback(
            '/(<a\b[^>]*\bhref=)(["\'])(.*?)\2/is',
            function ($m) use ($log) {
                $url = trim(html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // Only real outbound http(s) links are worth tracking.
                if (! preg_match('#^https?://#i', $url) || str_contains($url, '/email/track/')) {
                    return $m[0];
                }

                $tracked = route('email.track.click', [
                    'tracking' => $log->tracking_id,
                    'url' => $url,          // route() escapes it; the controller reads it back plain
                ]);

                return $m[1].$m[2].e($tracked).$m[2];
            },
            $html,
        ) ?? $html;
    }

    /** The domain our Message-IDs belong to. */
    public static function domain(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }

    /**
     * The free-text headers every message carries. Message-ID is set separately by the job — it is
     * a structured header and Symfony will not accept it as text.
     */
    public static function textHeadersFor(EmailLog $log): array
    {
        return [
            'X-Entity-Ref-ID' => (string) $log->tracking_id,
            // Lets a mailbox provider group our mail rather than treat each message as unrelated.
            'X-Mailer' => config('app.name').' Email Management',
        ];
    }
}
