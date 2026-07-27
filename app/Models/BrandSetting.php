<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The operator's own branding, overriding the shipped defaults in config/brand.php.
 *
 * Read on every page, so it is cached — and the cache is cleared on save rather than left to
 * expire, because someone changing their logo expects to see it on the next click.
 */
class BrandSetting extends Model
{
    private const CACHE_KEY = 'brand.settings';

    protected $fillable = ['product', 'tagline', 'logo', 'icon', 'primary', 'primary_hover'];

    public static function current(): self
    {
        // The attributes are cached, not the model. Serialising an Eloquent object across requests
        // comes back as __PHP_Incomplete_Class the moment the class or cache store shifts under it;
        // a plain array always rehydrates.
        $attributes = Cache::rememberForever(self::CACHE_KEY, fn () => static::first()?->attributesToArray() ?? []);

        return (new static)->forceFill($attributes);
    }

    /** The product name, falling back to what the software shipped with. */
    public function productName(): string
    {
        return $this->product ?: config('brand.product');
    }

    public function taglineText(): string
    {
        return $this->tagline ?: config('brand.tagline');
    }

    /** A URL for the wordmark, or null when nothing has been uploaded and no default exists. */
    public function logoUrl(): ?string
    {
        return $this->assetUrl($this->logo, config('brand.logo'));
    }

    public function iconUrl(): ?string
    {
        return $this->assetUrl($this->icon, config('brand.icon'));
    }

    public function primaryColour(): string
    {
        return $this->primary ?: '#5b6cf7';
    }

    public function primaryHoverColour(): string
    {
        // Derived rather than asked for: one colour picker is enough for anyone, and a hover shade
        // that clashes with the base is the usual result of offering two.
        return $this->primary_hover ?: $this->darken($this->primaryColour(), 0.12);
    }

    /** A soft tint of the primary, for chips and selected rows. */
    public function primarySoftColour(): string
    {
        return $this->mixWithWhite($this->primaryColour(), 0.9);
    }

    private function assetUrl(?string $uploaded, ?string $shipped): ?string
    {
        if ($uploaded) {
            return str_starts_with($uploaded, 'http') ? $uploaded : asset('storage/'.$uploaded);
        }

        return $shipped ? asset($shipped) : null;
    }

    private function darken(string $hex, float $by): string
    {
        [$r, $g, $b] = $this->rgb($hex);

        return sprintf('#%02x%02x%02x', (int) ($r * (1 - $by)), (int) ($g * (1 - $by)), (int) ($b * (1 - $by)));
    }

    private function mixWithWhite(string $hex, float $amount): string
    {
        [$r, $g, $b] = $this->rgb($hex);
        $mix = fn (int $c) => (int) ($c + (255 - $c) * $amount);

        return sprintf('#%02x%02x%02x', $mix($r), $mix($g), $mix($b));
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
