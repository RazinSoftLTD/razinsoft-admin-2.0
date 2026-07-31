<?php

namespace App\Http\Controllers;

use App\Models\WhatsappLink;
use App\Models\WhatsappLinkClick;
use App\Support\Geo;
use Illuminate\Http\Request;

/**
 * The public hop: /wa/{code} records the click and sends the visitor on to WhatsApp.
 *
 * The redirect happens whatever else fails. Someone tapping a link in an ad expects WhatsApp to
 * open; a logging problem is ours to notice later, not theirs to run into.
 */
class WhatsappLinkRedirectController extends Controller
{
    public function __invoke(Request $request, string $code)
    {
        $link = WhatsappLink::where('code', $code)->first();

        abort_unless($link, 404);

        // A retired link still points at the chat — people keep old posts and screenshots around,
        // and sending them to a 404 helps nobody. It just stops counting toward the campaign.
        if ($link->is_active) {
            try {
                $ip = $request->ip();
                $agent = (string) $request->userAgent();

                // Bots follow links in bulk the moment one is posted; counting them would make a
                // dead campaign look busy.
                if (! Geo::isBot($ip, $agent)) {
                    WhatsappLinkClick::create([
                        'link_id' => $link->id,
                        'ip' => $ip,
                        'country' => Geo::country($ip),
                        'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 512) ?: null,
                        'device' => preg_match('/Mobile|Android|iPhone|iPad/i', $agent) ? 'mobile' : 'desktop',
                        'user_agent' => mb_substr($agent, 0, 500),
                        'clicked_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                report($e);   // never let the log stand between someone and the chat
            }
        }

        return redirect()->away($link->whatsappUrl());
    }
}
