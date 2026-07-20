<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnvatoAuthor;
use App\Models\EnvatoNiche;
use App\Models\EnvatoProduct;
use App\Models\EnvatoSetting;
use App\Services\Envato\EnvatoClient;
use App\Services\Envato\EnvatoSync;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/** Activity → CodeCanyon: market & competitor analysis built only on the official Envato API. */
class CodeCanyonController extends Controller
{
    // ---------------------------------------------------------------- dashboard

    public function index(Request $request)
    {
        $nicheId = $request->query('niche');
        $products = EnvatoProduct::with('author', 'niche')
            ->when($nicheId, fn ($q) => $q->where('envato_niche_id', $nicheId))
            ->orderByDesc('number_of_sales')
            ->get();

        // Category popularity across everything we track.
        $categories = $products->groupBy(fn ($p) => str($p->classification ?? 'unknown')->explode('/')->first())
            ->map(fn ($rows, $key) => [
                'name' => str($key)->headline(),
                'products' => $rows->count(),
                'sales' => (int) $rows->sum('number_of_sales'),
                'revenue' => $rows->sum(fn ($p) => $p->estimatedRevenue()),
            ])->sortByDesc('sales')->values();

        return view('admin.codecanyon.index', [
            'settings' => EnvatoSetting::current(),
            'products' => $products,
            'authors' => EnvatoAuthor::with('products')->get(),
            'niches' => EnvatoNiche::withCount('products')->get(),
            'nicheId' => $nicheId,
            'categories' => $categories,
            'totals' => [
                'products' => $products->count(),
                'sales' => (int) $products->sum('number_of_sales'),
                'revenue' => $products->sum(fn ($p) => $p->estimatedRevenue()),
                'avgRating' => round((float) $products->where('rating', '>', 0)->avg('rating'), 2),
            ],
        ]);
    }

    public function author(EnvatoAuthor $author)
    {
        $author->load(['products' => fn ($q) => $q->orderByDesc('number_of_sales'), 'products.niche']);

        return view('admin.codecanyon.author', ['author' => $author]);
    }

    public function product(EnvatoProduct $product)
    {
        $product->load('author', 'niche', 'snapshots');

        return view('admin.codecanyon.product', ['product' => $product]);
    }

    // ---------------------------------------------------------------- watchlist

    public function storeAuthor(Request $request, EnvatoSync $sync)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:80', 'unique:envato_authors,username'],
            'is_own' => ['nullable', 'boolean'],
        ]);

        try {
            $author = EnvatoAuthor::create(['username' => trim($data['username']), 'is_own' => $request->boolean('is_own')]);
            $count = $sync->author($author);
        } catch (Throwable $e) {
            isset($author) && $author->delete();

            return back()->withErrors(['username' => $e->getMessage()]);
        }

        return back()->with('status', "Added {$author->username} — {$count} product(s) synced.");
    }

    public function destroyAuthor(EnvatoAuthor $author)
    {
        $author->products()->update(['envato_author_id' => null]);
        $author->delete();

        return back()->with('status', 'Author removed from the watchlist.');
    }

    public function storeProduct(Request $request, EnvatoSync $sync)
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'min:1'],
            'envato_niche_id' => ['nullable', Rule::exists('envato_niches', 'id')],
        ]);

        try {
            $product = $sync->product((int) $data['item_id'], null, $data['envato_niche_id'] ?? null);
        } catch (Throwable $e) {
            return back()->withErrors(['item_id' => $e->getMessage()]);
        }
        if (! $product) {
            return back()->withErrors(['item_id' => 'No CodeCanyon item found with that ID.']);
        }

        return back()->with('status', "Tracking “{$product->name}”.");
    }

    public function updateProduct(Request $request, EnvatoProduct $product)
    {
        $data = $request->validate(['envato_niche_id' => ['nullable', Rule::exists('envato_niches', 'id')]]);
        $product->update(['envato_niche_id' => $data['envato_niche_id'] ?? null]);

        return back()->with('status', 'Niche updated.');
    }

    public function destroyProduct(EnvatoProduct $product)
    {
        $product->delete();

        return back()->with('status', 'Product removed from tracking.');
    }

    public function storeNiche(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        EnvatoNiche::create($data + ['color' => $data['color'] ?? '#6366f1']);

        return back()->with('status', 'Niche added.');
    }

    public function destroyNiche(EnvatoNiche $niche)
    {
        $niche->products()->update(['envato_niche_id' => null]);
        $niche->delete();

        return back()->with('status', 'Niche removed.');
    }

    public function sync(EnvatoSync $sync)
    {
        try {
            [$authors, $products] = $sync->all();
        } catch (Throwable $e) {
            EnvatoSetting::current()->update(['last_error' => $e->getMessage()]);

            return back()->withErrors(['sync' => $e->getMessage()]);
        }

        return back()->with('status', "Synced {$authors} author(s) and {$products} product(s).");
    }

    // ---------------------------------------------------------------- settings

    public function settings()
    {
        return view('admin.settings.codecanyon', ['settings' => EnvatoSetting::current()]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'personal_token' => ['nullable', 'string', 'max:255'],
            'auto_sync' => ['nullable', 'boolean'],
        ]);

        $settings = EnvatoSetting::current();
        $settings->auto_sync = $request->boolean('auto_sync');

        // Blank means "leave the saved token alone".
        if (filled($data['personal_token'] ?? null)) {
            $settings->personal_token = trim($data['personal_token']);
        }
        $settings->save();

        if ($settings->isConfigured()) {
            try {
                $username = (new EnvatoClient($settings->personal_token))->verify();
                $settings->update(['is_connected' => true, 'verified_as' => $username, 'verified_at' => now(), 'last_error' => null]);

                return back()->with('status', "Connected to Envato as {$username}.");
            } catch (Throwable $e) {
                $settings->update(['is_connected' => false, 'last_error' => $e->getMessage()]);

                return back()->withErrors(['personal_token' => $e->getMessage()]);
            }
        }

        return back()->with('status', 'Settings saved.');
    }
}
