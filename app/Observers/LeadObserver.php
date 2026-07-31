<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\Meta\ConversionsApi;

/**
 * Reports lead quality back to Meta.
 *
 * Meta only sees that a form was filled in. Whether that person was worth talking to is known here,
 * days later, and sending it back is what lets ad delivery learn the difference — otherwise it
 * optimises for whoever fills in forms most readily, which is rarely the same group.
 *
 * Fires on the status settling to qualified or unqualified, whichever path set it: the panel, an
 * import, or the API. Only on an actual change, so re-saving a lead does not report it twice.
 */
class LeadObserver
{
    /** Lead status => the event Meta is told about. */
    private const EVENT_FOR = [
        'qualified' => 'QualifiedLead',
        'unqualified' => 'UnqualifiedLead',
    ];

    public function updated(Lead $lead): void
    {
        if (! $lead->wasChanged('lead_status')) {
            return;
        }

        $this->report($lead);
    }

    /** A lead can arrive already judged — an import, or a form that sets the status outright. */
    public function created(Lead $lead): void
    {
        $this->report($lead);
    }

    private function report(Lead $lead): void
    {
        $event = self::EVENT_FOR[$lead->lead_status] ?? null;

        if (! $event) {
            return;
        }

        try {
            [$first, $last] = array_pad(explode(' ', trim((string) $lead->full_name), 2), 2, null);

            ConversionsApi::make()->send(
                $event,
                // Stable per lead and status, so a retry — or the same lead being re-qualified
                // later — is deduplicated by Meta rather than counted twice.
                'lead-'.$lead->id.'-'.$lead->lead_status,
                [
                    'content_name' => 'Lead '.$lead->lead_status,
                    // The channel the lead came through, so ad reporting can tell WhatsApp leads
                    // from website ones.
                    'content_category' => $lead->lead_source,
                ],
                [
                    'email' => $lead->email,
                    'phone' => $lead->phone ?: $lead->office_phone,
                    'first_name' => $first,
                    'last_name' => $last,
                    'city' => $lead->city,
                    'country' => $lead->country,
                    'id' => $lead->id,
                ],
            );
        } catch (\Throwable $e) {
            // Never let reporting break saving a lead — the panel's job is the lead, not the pixel.
            report($e);
        }
    }
}
