<?php

namespace App\Support;

/**
 * Turns what the invoice editors produce into safe inline HTML that the web view and the PDF
 * render identically.
 *
 * The notes field and the item sub-description had a copy of this each, with docblocks claiming
 * they mirrored one another. They had drifted, and a fix applied to one silently left the other
 * behind. There is one copy now.
 *
 * DomPDF is the reason the work happens here rather than in CSS: its support for properties like
 * `white-space: pre-wrap` is not the browser's, so anything expressed as styling would render two
 * different ways on the two documents that have to match.
 */
class InvoiceRichText
{
    /** Tags worth keeping: what the toolbars can produce, and nothing that carries attributes. */
    private const KEEP = '<b><strong><i><em><u><br>';

    public static function format(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = self::spansToTags($html);

        $html = preg_replace('#<li[^>]*>#i', '◉ ', $html);
        $html = preg_replace('#</(p|li|ul|ol|div|h[1-6])>#i', '<br>', $html);
        $html = preg_replace('#<(p|ul|ol|div|h[1-6])[^>]*>#i', '', $html);
        $html = strip_tags($html, self::KEEP);

        // Values saved before the editors existed are plain text, and their newlines mean the same
        // thing a <br> does now.
        $html = preg_replace('#\R#u', '<br>', $html);

        // Two breaks is one blank line, which someone typed on purpose. Collapsing every run to a
        // single break threw those away; this cap is only here to stop a runaway gap, so it sits
        // above a deliberate blank line rather than below it. A browser writes a blank line as
        // three breaks once the block tags above have been flattened, hence 3+ rather than 2+.
        $html = preg_replace('#(<br\s*/?>\s*){3,}#i', '<br><br>', $html);

        $html = self::keepSpacing($html);

        return preg_replace('#^(<br\s*/?>)+|(<br\s*/?>)+$#i', '', trim($html));
    }

    /**
     * The list items out of a sub-description, or an empty array when it is not a list.
     *
     * A long feature list reads far better set in two numbered columns than as one column running
     * down the page, and that needs the items separately rather than pre-flattened into <br>s.
     *
     * @return string[] each already formatted as safe inline HTML
     */
    public static function listItems(string $html): array
    {
        if (! preg_match_all('#<li[^>]*>(.*?)</li>#is', $html, $m)) {
            return [];
        }

        // Anything outside the list would be dropped by taking only the items, so leave those
        // sub-descriptions to the normal formatter rather than silently losing half of one.
        $outside = trim(strip_tags(preg_replace('#<(ul|ol)[^>]*>.*?</(ul|ol)>#is', '', $html)));
        if ($outside !== '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => self::format($item),
            $m[1]
        ), fn ($item) => $item !== ''));
    }

    /** Plain-text fields — escaped first, then given the same line breaks and spacing. */
    public static function formatPlain(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        // nl2br leaves the newline in place behind the tag. Dropping it keeps the indentation rule
        // looking at the character that actually starts the line.
        return self::keepSpacing(preg_replace('#\R#u', '', nl2br(e(trim($text)), false)));
    }

    /**
     * Rewrites bold, italic and underline expressed as inline styles into the tags we keep.
     *
     * Browsers do not agree on how to record formatting: the toolbar path produces <b>, but with
     * CSS styling turned on the same command produces <span style="font-weight: bold">. The span
     * does not survive the tag whitelist, so that second form silently lost its formatting on the
     * way to the invoice and the PDF.
     *
     * Runs innermost-first so nested spans are unwrapped from the inside out.
     */
    private static function spansToTags(string $html): string
    {
        if (stripos($html, '<span') === false) {
            return $html;
        }

        // Matches a span whose content holds no further span — i.e. the innermost one.
        $innermost = '#<span[^>]*style=(["\'])(?<style>[^"\']*)\1[^>]*>(?<inner>(?:(?!<span\b|</span>).)*)</span>#is';

        // Bounded: content nested more than a handful of spans deep is malformed, not formatted.
        for ($pass = 0; $pass < 6; $pass++) {
            $out = preg_replace_callback($innermost, function ($m) {
                $style = strtolower($m['style']);
                $inner = $m['inner'];

                if (preg_match('/text-decoration[^;]*underline/', $style)) {
                    $inner = '<u>'.$inner.'</u>';
                }
                if (preg_match('/font-style\s*:\s*italic/', $style)) {
                    $inner = '<i>'.$inner.'</i>';
                }
                if (preg_match('/font-weight\s*:\s*(bold|bolder|[6-9]00)/', $style)) {
                    $inner = '<b>'.$inner.'</b>';
                }

                return $inner;
            }, $html);

            if ($out === null || $out === $html) {
                break;
            }
            $html = $out;
        }

        return $html;
    }

    /**
     * Keeps runs of spaces and indentation, which HTML would otherwise collapse to a single space.
     *
     * Safe at this point in the pipeline: only the tags in KEEP survive by now, and none of them
     * carry attributes whose spacing could be damaged.
     */
    private static function keepSpacing(string $html): string
    {
        // A run of spaces is deliberate — an aligned column, a gap after a label.
        $html = preg_replace_callback('/ {2,}/', fn ($m) => str_repeat('&nbsp;', strlen($m[0])), $html);

        // A single space at the start of a line is indentation, and collapses just as readily.
        return preg_replace_callback(
            '/(\A|<br\s*\/?>)( +)/i',
            fn ($m) => $m[1].str_repeat('&nbsp;', strlen($m[2])),
            $html
        );
    }
}
