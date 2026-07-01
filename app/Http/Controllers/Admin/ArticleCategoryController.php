<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArticleCategoryController extends Controller
{
    public function index()
    {
        $categories = ArticleCategory::withCount('articles')->orderBy('name')->get();

        return view('admin.article-categories.index', compact('categories'));
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
