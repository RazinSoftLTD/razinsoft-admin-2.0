<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::with('category', 'author')->orderByDesc('published_at')->orderByDesc('id')->paginate(15);

        return view('admin.articles.index', compact('articles'));
    }

    public function create()
    {
        return view('admin.articles.form', [
            'article' => new Article(['status' => 'draft']),
            'categories' => ArticleCategory::orderBy('name')->get(),
            'authors' => Author::orderBy('name')->get(),
            'allProducts' => \App\Models\Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $article = Article::create($data);
        $this->syncFeatured($article);
        $article->products()->sync($request->input('product_ids', []));

        return redirect()->route('admin.articles.edit', $article)->with('status', 'Article created.');
    }

    public function edit(Article $article)
    {
        return view('admin.articles.form', [
            'article' => $article->load('products:id'),
            'categories' => ArticleCategory::orderBy('name')->get(),
            'authors' => Author::orderBy('name')->get(),
            'allProducts' => \App\Models\Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $article->update($this->validated($request, $article));
        $this->syncFeatured($article);
        $article->products()->sync($request->input('product_ids', []));

        return back()->with('status', 'Article updated.');
    }

    public function togglePublish(Article $article)
    {
        $article->update(['status' => $article->status === 'published' ? 'draft' : 'published']);

        return back()->with('status', $article->status === 'published' ? 'Article published.' : 'Article unpublished.');
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return redirect()->route('admin.articles.index')->with('status', 'Article deleted.');
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        $v = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($article)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'exists:article_categories,id'],
            'author_id' => ['nullable', 'exists:authors,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'published_at' => ['nullable', 'date'],
            'read_time' => ['nullable', 'string', 'max:50'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:4096', \App\Support\ImageSpecs::rule('article')],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'quote' => ['nullable', 'string', 'max:1000'],
            'takeaways' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'is_featured' => ['boolean'],
            'status' => ['required', 'in:draft,published'],
        ], [
            'image.dimensions' => \App\Support\ImageSpecs::message('article', 'featured image'),
        ]);

        $data = [
            'title' => $v['title'],
            'slug' => Str::slug(($v['slug'] ?? '') ?: $v['title']),
            'excerpt' => $v['excerpt'] ?? null,
            'category_id' => $v['category_id'] ?? null,
            'author_id' => $v['author_id'] ?? null,
            'published_at' => $v['published_at'] ?? ($article?->published_at ?? now()->toDateString()),
            'read_time' => $v['read_time'] ?? null,
            'image_alt' => $v['image_alt'] ?? null,
            'tags' => $this->lines($request->input('tags'), ','),
            'content' => $this->sanitizeHtml($request->input('content')),
            'quote' => $v['quote'] ?? null,
            'takeaways' => $this->lines($request->input('takeaways')) ?: null,
            'meta_title' => $v['meta_title'] ?? null,
            'meta_description' => $v['meta_description'] ?? null,
            'meta_keywords' => $v['meta_keywords'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
            'status' => $v['status'],
        ];

        // Image: uploaded file wins, else a pasted URL, else keep existing.
        if ($request->hasFile('image')) {
            $img = $request->file('image');
            $data['image'] = $img->storeAs('articles', $img->getClientOriginalName(), 'public');
        } elseif (! empty($v['image_url'])) {
            $data['image'] = $v['image_url'];
        }

        return $data;
    }

    /** Inline image upload for the rich-text editor. Returns a public URL. */
    public function uploadImage(Request $request)
    {
        $request->validate(
            ['file' => ['required', 'image', 'max:8192', \App\Support\ImageSpecs::rule('article_inline')]],
            ['file.dimensions' => \App\Support\ImageSpecs::message('article_inline', 'image')],
        );
        $file = $request->file('file');
        $path = $file->storeAs('articles/content', $file->getClientOriginalName(), 'public');

        return response()->json(['url' => \App\Http\Resources\ProductResource::media($path)]);
    }

    /** Keep editor HTML but drop scripts/handlers (admin-authored, defensive). */
    private function sanitizeHtml(?string $html): ?string
    {
        $html = (string) $html;
        if (trim(strip_tags($html)) === '' && ! str_contains($html, '<img')) {
            return null;
        }
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $html);
        $html = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $html);

        return $html;
    }

    /** Only one featured article at a time. */
    private function syncFeatured(Article $article): void
    {
        if ($article->is_featured) {
            Article::whereKeyNot($article->id)->where('is_featured', true)->update(['is_featured' => false]);
        }
    }

    /** Split a textarea into a clean list (by newline, optionally also a secondary separator). */
    private function lines(?string $text, ?string $also = null): array
    {
        $parts = preg_split('/\r\n|\r|\n'.($also ? '|'.preg_quote($also, '/') : '').'/', (string) $text);

        return collect($parts)->map(fn ($l) => trim($l))->filter()->values()->all();
    }
}
