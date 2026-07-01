<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Author;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/articles.json');
        if (! is_file($path)) {
            $this->command?->warn('articles.json not found — skipping.');

            return;
        }

        $articles = json_decode((string) file_get_contents($path), true) ?: [];
        $baseDate = '2026-06-03';

        foreach ($articles as $a) {
            $categoryId = null;
            if (! empty($a['category'])) {
                $categoryId = ArticleCategory::firstOrCreate(
                    ['slug' => Str::slug($a['category'])],
                    ['name' => $a['category']],
                )->id;
            }

            $authorId = null;
            if (! empty($a['author'])) {
                $authorId = Author::firstOrCreate(
                    ['slug' => Str::slug($a['author'])],
                    ['name' => $a['author'], 'role' => 'Content Writer'],
                )->id;
            }

            Article::updateOrCreate(
                ['slug' => Str::slug($a['title'])],
                [
                    'title' => $a['title'],
                    'excerpt' => $a['excerpt'] ?? null,
                    'image' => $a['image'] ?? null,
                    'image_alt' => $a['title'],
                    'category_id' => $categoryId,
                    'author_id' => $authorId,
                    'published_at' => $this->parseDate($a['date'] ?? $baseDate),
                    'read_time' => $a['readTime'] ?? null,
                    'tags' => $a['tags'] ?? [],
                    'content' => collect($a['content'] ?? [])->map(fn ($p) => '<p>'.e($p).'</p>')->implode("\n"),
                    'quote' => $a['quote'] ?? null,
                    'takeaways' => $a['takeaways'] ?? null,
                    'is_featured' => (bool) ($a['featured'] ?? false),
                    'status' => 'published',
                ],
            );
        }

        $this->command?->info('Seeded '.count($articles).' articles.');
    }

    private function parseDate(string $d): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($d)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }
}
