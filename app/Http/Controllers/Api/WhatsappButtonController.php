<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappLink;

/** What the website's floating WhatsApp button should point at. Public: it is on every page. */
class WhatsappButtonController extends Controller
{
    public function __invoke()
    {
        $link = WhatsappLink::siteButton();

        // Null is a real answer, not an error: with no link chosen the site keeps its own built-in
        // number, so the button never disappears because the panel had nothing to say.
        return response()->json([
            'data' => $link ? [
                'url' => $link->shortUrl(),      // the counted hop, not wa.me
                'label' => $link->label,
            ] : null,
        ]);
    }
}
