<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArticleCategoryController extends Controller
{
    public function index()
    {
        $categories = ArticleCategory::withCount('articles')->orderBy('name')->get();

        // Who actually writes in each category, taken from the articles themselves rather than a
        // separate assignment — an assignment nobody maintained would drift from the truth, and
        // this cannot.
        $authors = DB::table('articles')
            ->join('authors', 'authors.id', '=', 'articles.author_id')
            ->whereNotNull('articles.category_id')
            ->select('articles.category_id', 'authors.name')
            ->distinct()
            ->orderBy('authors.name')
            ->get()
            ->groupBy('category_id')
            ->map(fn ($g) => $g->pluck('name')->all());

        return view('admin.article-categories.index', compact('categories', 'authors'));
    }

    /** Retire a category, or bring it back. Existing posts keep theirs either way. */
    public function toggleActive(Request $request, ArticleCategory $articleCategory)
    {
        $next = $request->input('status');
        $articleCategory->update([
            'is_active' => in_array($next, ['active', 'inactive'], true)
                ? $next === 'active'
                : ! $articleCategory->is_active,
        ]);

        return back()->with('status', $articleCategory->is_active
            ? "\"{$articleCategory->name}\" is available for new articles."
            : "\"{$articleCategory->name}\" is retired — existing articles keep it.");
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('article_categories', 'name')]]);
        ArticleCategory::create(['name' => $data['name'], 'slug' => Str::slug($data['name'])]);

        return back()->with('status', 'Category added.');
    }

    public function update(Request $request, ArticleCategory $articleCategory)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('article_categories', 'name')->ignore($articleCategory)]]);
        $articleCategory->update(['name' => $data['name'], 'slug' => Str::slug($data['name'])]);

        return back()->with('status', 'Category updated.');
    }

    public function destroy(ArticleCategory $articleCategory)
    {
        // Articles keep existing but lose their category link (nullOnDelete).
        $articleCategory->delete();

        return back()->with('status', 'Category deleted.');
    }
}
