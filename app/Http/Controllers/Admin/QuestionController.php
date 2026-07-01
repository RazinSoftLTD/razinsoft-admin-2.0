<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        // Product filter sidebar: every product that has questions, with a pending-reply count.
        $products = Product::query()
            ->whereHas('questions')
            ->withCount([
                'questions',
                'questions as pending_count' => fn ($q) => $q->whereDoesntHave('answers', fn ($a) => $a->where('is_admin', true)),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $activeSlug = $request->query('product');
        $activeProduct = $activeSlug ? $products->firstWhere('slug', $activeSlug) : null;

        // Questions awaiting an admin reply first, then newest. Optionally scoped to one product.
        $questions = ProductQuestion::with('product:id,name,slug', 'user:id,name', 'answers.user:id,name')
            ->withCount(['answers as admin_answers_count' => fn ($a) => $a->where('is_admin', true)])
            ->when($activeProduct, fn ($q) => $q->where('product_id', $activeProduct->id))
            ->orderByRaw('admin_answers_count > 0')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.questions.index', compact('questions', 'products', 'activeProduct'));
    }

    /** Admin posts an answer in the thread (shows as "Author" on the storefront). */
    public function reply(Request $request, ProductQuestion $question)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $question->answers()->create([
            'user_id' => $request->user()->id,
            'name' => $request->user()->name,
            'body' => $data['body'],
            'is_admin' => true,
            'is_public' => true,
        ]);

        return back()->with('status', 'Reply posted to the thread.');
    }

    public function destroy(ProductQuestion $question)
    {
        $question->delete();

        return back()->with('status', 'Question deleted.');
    }
}
