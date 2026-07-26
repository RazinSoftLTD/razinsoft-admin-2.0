<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\EmailSuppression;
use Illuminate\Http\Request;

/**
 * Public tracking endpoints hit by the recipient's mail client and browser.
 *
 * Deliberately unauthenticated and unguessable: each message carries its own UUID, which is the
 * only thing that identifies it. Nothing here trusts input beyond that UUID.
 */
class EmailTrackingController extends Controller
{
    /** 1×1 transparent GIF — the smallest valid image, so it costs the recipient nothing. */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** Open tracking. Always returns the pixel, even for an unknown id — never leak what exists. */
    public function open(Request $request, string $tracking)
    {
        $log = EmailLog::where('tracking_id', $tracking)->first();

        if ($log) {
            $log->opens()->create([
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'opened_at' => now(),
            ]);

            $log->forceFill([
                'open_count' => $log->open_count + 1,
                'first_opened_at' => $log->first_opened_at ?: now(),
                // Reaching the recipient's client is the best delivery proof SMTP alone gives us.
                'delivered_at' => $log->delivered_at ?: now(),
            ])->save();
        }

        return response(base64_decode(self::PIXEL))
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** Click tracking, then straight on to the real link. */
    public function click(Request $request, string $tracking)
    {
        // Already decoded once by the query parser — decoding again would corrupt a legitimate
        // %20 inside the target URL.
        $url = (string) $request->query('url');

        // Only ever redirect to a real http(s) address — an open redirect would be a phishing tool.
        if (! preg_match('#^https?://#i', $url)) {
            return redirect(config('app.url'));
        }

        $log = EmailLog::where('tracking_id', $tracking)->first();

        if ($log) {
            $log->clicks()->create([
                'url' => mb_substr($url, 0, 2000),
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'clicked_at' => now(),
            ]);

            $log->forceFill([
                'click_count' => $log->click_count + 1,
                'first_clicked_at' => $log->first_clicked_at ?: now(),
                'delivered_at' => $log->delivered_at ?: now(),
                'first_opened_at' => $log->first_opened_at ?: now(),   // a click implies an open
            ])->save();
        }

        return redirect()->away($url);
    }

    /**
     * One-click unsubscribe, the target of the List-Unsubscribe header on marketing mail.
     * Suppresses the address so nothing else in the system can mail it again.
     */
    public function unsubscribe(string $tracking)
    {
        $log = EmailLog::where('tracking_id', $tracking)->first();

        if ($log) {
            EmailSuppression::add($log->to_email, 'unsubscribe', 'Unsubscribed from '.$log->subject);
        }

        return response()->view('emails.unsubscribed', ['email' => $log?->to_email]);
    }
}
