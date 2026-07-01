<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        // Product filter sidebar: every product that has reviews, with a hidden count.
        $products = Product::query()
            ->whereHas('reviews')
            ->withCount([
                'reviews',
                'reviews as hidden_count' => fn ($q) => $q->where('is_approved', false),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $activeSlug = $request->query('product');
        $activeProduct = $activeSlug ? $products->firstWhere('slug', $activeSlug) : null;

        $reviews = Review::with('product:id,name,slug', 'user:id,name')
            ->when($activeProduct, fn ($q) => $q->where('product_id', $activeProduct->id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.reviews.index', compact('reviews', 'products', 'activeProduct'));
    }

    /** Edit the author, rating or comment. */
    public function update(Request $request, Review $review)
    {
        $data = $request->validate([
            'author_name' => ['nullable', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update($data);
        $review->product?->syncReviewAggregates();

        return back()->with('status', 'Review updated.');
    }

    /** Hide (unapprove) or show (approve) a review — affects the public product rating. */
    public function toggle(Review $review)
    {
        $review->update(['is_approved' => ! $review->is_approved]);
        $review->product?->syncReviewAggregates();

        return back()->with('status', $review->is_approved ? 'Review is now visible.' : 'Review hidden.');
    }

    public function destroy(Review $review)
    {
        $product = $review->product;
        $review->delete();
        $product?->syncReviewAggregates();

        return back()->with('status', 'Review deleted.');
    }
}
