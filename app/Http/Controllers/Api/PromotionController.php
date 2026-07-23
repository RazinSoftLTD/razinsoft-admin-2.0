<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Promotion;

class PromotionController extends Controller
{
    /** The currently live Top Banner and Popup (each published + within its schedule), or null. */
    public function active()
    {
        $topBanner = Promotion::type(Promotion::TYPE_TOP_BANNER)->live()->latest()->first();
        $popup = Promotion::type(Promotion::TYPE_POPUP)->live()->latest()->first();

        return response()->json([
            'data' => [
                'top_banner' => $topBanner ? [
                    'image' => ProductResource::media($topBanner->image),
                    'ends_at' => $topBanner->ends_at?->toIso8601String(),
                    'countdown_enabled' => (bool) $topBanner->countdown_enabled,
                    // null/blank means the admin deliberately cleared it — hide the title, not fall back to a default.
                    'countdown_label' => $topBanner->countdown_label,
                    'countdown_title_color' => $topBanner->countdown_title_color,
                    'countdown_value_color' => $topBanner->countdown_value_color,
                ] : null,
                'popup' => $popup ? [
                    'image' => ProductResource::media($popup->image),
                ] : null,
            ],
        ]);
    }
}
