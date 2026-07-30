<?php

namespace App\Http\Controllers;

use App\Models\EmailSuppression;
use App\Models\MapsLead;
use Illuminate\Http\Request;

/**
 * One-click opt-out for outreach messages.
 *
 * Public and unauthenticated on purpose - a recipient must be able to stop mail
 * without an account. The token is the authorisation.
 *
 * The address goes on the shared EmailSuppression list, not just this lead's
 * row, so every other part of the app stops mailing it too.
 */
class OutreachUnsubscribeController extends Controller
{
    /**
     * GET is accepted because that is what a mail client follows. It only ever
     * adds to the suppression list, so a scanner pre-fetching the link causes an
     * unwanted opt-out at worst - never a deletion.
     */
    public function __invoke(Request $request, string $token)
    {
        $lead = MapsLead::withTrashed()->where('unsubscribe_token', $token)->first();

        if (! $lead) {
            return response()->view('outreach.unsubscribed', [
                'email' => null,
                'unknown' => true,
            ], 404);
        }

        if (filled($lead->email) && ! EmailSuppression::has($lead->email)) {
            EmailSuppression::add(
                $lead->email,
                'unsubscribe', // must match EmailSuppression::REASONS
                "Opted out of Maps outreach ({$lead->name})",
            );
        }

        $lead->forceFill([
            'outreach_status' => 'unsubscribed',
            'status' => 'lost',
        ])->save();

        return response()->view('outreach.unsubscribed', [
            'email' => $lead->email,
            'unknown' => false,
        ]);
    }
}
