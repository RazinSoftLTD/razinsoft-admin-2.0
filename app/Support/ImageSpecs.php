<?php

namespace App\Support;

/**
 * Single source of truth for the size each uploaded image must be, so the website slot it
 * lands in fits correctly. Used to build the Laravel `dimensions` rule, the hint under the
 * upload field, and the rejection message — all from one place so they never drift.
 *
 * Website slots use object-cover (crop to the slot), so the ASPECT RATIO is what must match;
 * min_width guards against blurry uploads.
 */
class ImageSpecs
{
    public const SPECS = [
        // key       => [ratio, min_width, min_height, recommended, label]
        'product' => ['ratio' => '3/2', 'min_width' => 900, 'min_height' => 600, 'recommended' => '1200×800', 'label' => '3:2 landscape'],
        'gallery' => ['ratio' => '16/9', 'min_width' => 1024, 'min_height' => 576, 'recommended' => '1280×720', 'label' => '16:9 widescreen'],
        'article' => ['ratio' => '16/9', 'min_width' => 1200, 'min_height' => 675, 'recommended' => '1200×675', 'label' => '16:9 widescreen'],
        'article_inline' => ['min_width' => 600, 'recommended' => '≥ 600px wide', 'label' => 'at least 600px wide'],
        'avatar' => ['ratio' => '1/1', 'min_width' => 400, 'min_height' => 400, 'recommended' => '400×400', 'label' => 'square (1:1)'],
        // Top Banner — full-width strip shown above the site's nav menu. GIFs allowed (Laravel's
        // `image` rule already accepts gif/jpg/png/webp/bmp/svg).
        'banner' => ['ratio' => '16/1', 'min_width' => 1920, 'min_height' => 120, 'recommended' => '1920×120', 'label' => '16:1 wide banner'],
        // Popup — a modal promo graphic shown once per page load. GIFs allowed too.
        'popup_banner' => ['ratio' => '1/1', 'min_width' => 600, 'min_height' => 600, 'recommended' => '800×800', 'label' => 'square (1:1)'],
    ];

    /** Laravel `dimensions:` rule string for a spec key. */
    public static function rule(string $key): string
    {
        $s = self::SPECS[$key];
        $parts = [];
        foreach (['ratio', 'min_width', 'min_height'] as $k) {
            if (isset($s[$k])) {
                $parts[] = "{$k}={$s[$k]}";
            }
        }

        return 'dimensions:'.implode(',', $parts);
    }

    /** Hint shown under the upload field. */
    public static function hint(string $key): string
    {
        $s = self::SPECS[$key];

        return "Must be {$s['label']} — recommended {$s['recommended']}.";
    }

    /** Rejection message when the uploaded image doesn't match. */
    public static function message(string $key, string $field): string
    {
        $s = self::SPECS[$key];

        return ucfirst($field)." must be {$s['label']} (recommended {$s['recommended']}px). Please resize/crop and try again.";
    }
}
