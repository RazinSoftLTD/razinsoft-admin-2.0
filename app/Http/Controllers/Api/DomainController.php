<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ResellerClub;
use Illuminate\Http\Request;

/** Public domain search for the website's Cloud & Domains page. */
class DomainController extends Controller
{
    public function search(Request $request, ResellerClub $rc)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tlds' => ['nullable', 'array', 'max:20'],
            'tlds.*' => ['string', 'max:20'],
        ]);

        $label = $rc->normaliseLabel($data['name']);

        if (strlen($label) < 2) {
            return response()->json([
                'configured' => $rc->configured(),
                'label' => $label,
                'results' => [],
                'message' => 'Enter at least two characters.',
            ], 422);
        }

        // Without credentials the site still has a domain page — it just asks people to get in
        // touch instead of showing a search that cannot answer.
        if (! $rc->configured()) {
            return response()->json([
                'configured' => false,
                'label' => $label,
                'results' => [],
            ]);
        }

        // Only TLDs we actually offer: an arbitrary list from the browser would have us quoting
        // extensions nobody has priced.
        $offered = $rc->tlds();
        $tlds = isset($data['tlds'])
            ? array_values(array_intersect(array_map(fn ($t) => ltrim($t, '.'), $data['tlds']), $offered))
            : $offered;

        $results = $rc->search($label, $tlds ?: $offered);

        return response()->json([
            'configured' => true,
            'label' => $label,
            'currency' => config('services.resellerclub.currency', 'USD'),
            // Available first, then by price, so the answer to "can I have it" is at the top.
            'results' => collect($results)
                ->sortBy([
                    fn ($a, $b) => ($b['available'] <=> $a['available']),
                    fn ($a, $b) => ($a['price'] ?? INF) <=> ($b['price'] ?? INF),
                ])
                ->values(),
        ]);
    }
}
