<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Find products whose SEO text still belongs to the product they were cloned from.
 *
 * Cloning used to copy the SEO row verbatim, so a duplicate published under the original's title,
 * description, canonical and SKU. The page heading was right and the browser tab was wrong, which
 * is exactly the kind of thing nobody notices from inside the admin.
 *
 *   php artisan products:seo-audit          list what is wrong
 *   php artisan products:seo-audit --fix    clear the borrowed text
 *
 * Clearing rather than rewriting: the site already falls back to "{name} — {tagline}", which is
 * correct, and inventing marketing copy for someone else's product is not this command's job.
 */
class ProductSeoAudit extends Command
{
    protected $signature = 'products:seo-audit {--fix : Clear the fields that belong to another product}';

    protected $description = 'Report (or clear) SEO text carried over from a cloned product';

    /** Text that names one specific product, as opposed to settings that apply to any of them. */
    private const IDENTITY = [
        'seo_title', 'meta_description', 'focus_keyword', 'meta_keywords', 'canonical_url',
        'og_title', 'og_description', 'og_image',
        'twitter_title', 'twitter_description', 'twitter_image',
        'sku',
    ];

    public function handle(): int
    {
        $products = Product::with('seo')->get();
        $slugs = $products->pluck('slug')->all();
        $bad = 0;

        foreach ($products as $p) {
            if (! $p->seo) {
                continue;
            }

            // Whose product is this text about? A canonical or an og_image naming another product's
            // slug is unambiguous — those are built from the slug, so they cannot coincide.
            $owner = collect($slugs)
                ->reject(fn ($s) => $s === $p->slug)
                ->first(fn ($s) => $this->mentions($p->seo, $s) || $this->mentions($p->seo, str_replace('-', '', $s)));

            if (! $owner) {
                continue;
            }

            $bad++;
            $dirty = collect(self::IDENTITY)->filter(fn ($f) => filled($p->seo->{$f}))->all();

            $this->line('');
            $this->warn("{$p->name}  (/{$p->slug})  — carries text from /{$owner}");
            $this->line('  title: '.($p->seo->seo_title ?: '—'));
            $this->line('  will clear: '.implode(', ', $dirty));

            if ($this->option('fix')) {
                $p->seo->update(array_fill_keys($dirty, null));
                $this->info('  cleared — the site now shows "'.$p->name.' — '.$p->tagline.'"');
            }
        }

        $this->line('');
        if ($bad === 0) {
            $this->info('Every product has its own SEO text.');
        } elseif ($this->option('fix')) {
            $this->info("Fixed {$bad} product(s). Rebuild the website so the pages are re-rendered.");
        } else {
            $this->warn("{$bad} product(s) affected. Re-run with --fix to clear the borrowed text.");
        }

        return self::SUCCESS;
    }

    /** Does any identity field name this slug? */
    private function mentions($seo, string $slug): bool
    {
        foreach (['canonical_url', 'og_image', 'twitter_image', 'sku'] as $f) {
            if (filled($seo->{$f}) && str_contains(strtolower($seo->{$f}), $slug)) {
                return true;
            }
        }

        return false;
    }
}
