<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailBounce;
use App\Models\EmailLog;
use App\Models\EmailSpamReport;
use App\Models\EmailSuppression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bounce and complaint reports from the sending provider.
 *
 * Acting on these is the single most important thing for staying out of spam folders: continuing
 * to mail an address that hard-bounced, or one whose owner pressed "this is spam", is what gets a
 * sending domain blocked. Both outcomes add the address to the suppression list, which
 * EmailDispatcher checks before anything is queued.
 *
 * Providers post different shapes, so the payload is normalised rather than trusted field by
 * field. The endpoint is guarded by a shared secret because it is necessarily public.
 */
class EmailWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! $this->authorised($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->all();
        $event = $this->normalise($payload);

        if (! $event['email']) {
            Log::info('Email webhook ignored — no recipient in the payload.', ['keys' => array_keys($payload)]);

            return response()->json(['status' => 'ignored']);
        }

        $log = $this->findLog($event);

        return match ($event['type']) {
            'bounce' => $this->recordBounce($event, $log, $payload),
            'complaint' => $this->recordComplaint($event, $log, $payload),
            'delivered' => $this->recordDelivered($log),
            default => response()->json(['status' => 'ignored', 'type' => $event['type']]),
        };
    }

    /**
     * A shared secret, sent as a header or a query parameter — whichever the provider supports.
     * Providers that sign payloads properly can be added here per-provider later.
     */
    private function authorised(Request $request): bool
    {
        $secret = config('services.email_webhook.secret');

        // With no secret configured the endpoint stays closed rather than open to anyone.
        if (blank($secret)) {
            return false;
        }

        $given = $request->header('X-Webhook-Secret') ?: $request->query('secret');

        return is_string($given) && hash_equals($secret, $given);
    }

    /**
     * Flatten the provider payloads into {type, email, reason, tracking_id}.
     *
     * Covers the common shapes: SES/SNS, Mailgun, SendGrid, Postmark. Anything unrecognised
     * returns type "unknown" and is ignored rather than guessed at.
     */
    private function normalise(array $p): array
    {
        // SES via SNS wraps the real event in a JSON string.
        if (isset($p['Message']) && is_string($p['Message'])) {
            $inner = json_decode($p['Message'], true);
            if (is_array($inner)) {
                $p = $inner;
            }
        }

        // Amazon SES
        if (isset($p['notificationType']) || isset($p['eventType'])) {
            $kind = mb_strtolower((string) ($p['notificationType'] ?? $p['eventType']));
            $recipients = $p['bounce']['bouncedRecipients'] ?? $p['complaint']['complainedRecipients'] ?? [];

            return [
                'type' => match ($kind) {
                    'bounce' => 'bounce',
                    'complaint' => 'complaint',
                    'delivery' => 'delivered',
                    default => 'unknown',
                },
                'email' => $recipients[0]['emailAddress'] ?? ($p['mail']['destination'][0] ?? null),
                'hard' => ($p['bounce']['bounceType'] ?? '') === 'Permanent',
                'reason' => $recipients[0]['diagnosticCode'] ?? ($p['bounce']['bounceSubType'] ?? null),
                'tracking_id' => $this->trackingFromHeaders($p['mail']['headers'] ?? []),
            ];
        }

        // Mailgun
        if (isset($p['event-data'])) {
            $d = $p['event-data'];
            $kind = mb_strtolower((string) ($d['event'] ?? ''));

            return [
                'type' => match ($kind) {
                    'failed' => 'bounce',
                    'complained' => 'complaint',
                    'delivered' => 'delivered',
                    default => 'unknown',
                },
                'email' => $d['recipient'] ?? null,
                'hard' => ($d['severity'] ?? '') === 'permanent',
                'reason' => $d['delivery-status']['message'] ?? ($d['reason'] ?? null),
                'tracking_id' => $d['user-variables']['tracking_id'] ?? null,
            ];
        }

        // Postmark
        if (isset($p['RecordType'])) {
            $kind = mb_strtolower((string) $p['RecordType']);

            return [
                'type' => match ($kind) {
                    'bounce' => 'bounce',
                    'spamcomplaint' => 'complaint',
                    'delivery' => 'delivered',
                    default => 'unknown',
                },
                'email' => $p['Email'] ?? $p['Recipient'] ?? null,
                'hard' => ($p['Type'] ?? '') === 'HardBounce',
                'reason' => $p['Description'] ?? ($p['Details'] ?? null),
                'tracking_id' => $p['Metadata']['tracking_id'] ?? null,
            ];
        }

        // SendGrid posts an array of events; the caller sends them one at a time here.
        if (isset($p['event'])) {
            $kind = mb_strtolower((string) $p['event']);

            return [
                'type' => match ($kind) {
                    'bounce', 'dropped' => 'bounce',
                    'spamreport' => 'complaint',
                    'delivered' => 'delivered',
                    default => 'unknown',
                },
                'email' => $p['email'] ?? null,
                'hard' => ($p['type'] ?? '') === 'bounce',
                'reason' => $p['reason'] ?? null,
                'tracking_id' => $p['tracking_id'] ?? null,
            ];
        }

        return ['type' => 'unknown', 'email' => $p['email'] ?? null, 'hard' => false, 'reason' => null, 'tracking_id' => null];
    }

    /** Our own X-Entity-Ref-ID header, when the provider echoes the original headers back. */
    private function trackingFromHeaders(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (mb_strtolower($header['name'] ?? '') === 'x-entity-ref-id') {
                return $header['value'] ?? null;
            }
        }

        return null;
    }

    /** Match the report to the message: by tracking id when we have it, otherwise the last one sent. */
    private function findLog(array $event): ?EmailLog
    {
        if ($event['tracking_id']) {
            $byId = EmailLog::where('tracking_id', $event['tracking_id'])->first();

            if ($byId) {
                return $byId;
            }
        }

        return EmailLog::where('to_email', mb_strtolower($event['email']))
            ->where('status', 'sent')->latest('sent_at')->first();
    }

    private function recordBounce(array $event, ?EmailLog $log, array $payload)
    {
        EmailBounce::create([
            'email_log_id' => $log?->id,
            'email' => $event['email'],
            'type' => $event['hard'] ? 'hard' : 'soft',
            'reason' => $event['reason'],
            'payload' => $payload,
        ]);

        $log?->forceFill(['bounced' => true])->save();

        // Soft bounces are temporary (a full mailbox), so only hard ones stop future mail.
        if ($event['hard']) {
            EmailSuppression::add($event['email'], 'bounce', $event['reason']);
        }

        return response()->json(['status' => 'recorded', 'type' => 'bounce']);
    }

    private function recordComplaint(array $event, ?EmailLog $log, array $payload)
    {
        EmailSpamReport::create([
            'email_log_id' => $log?->id,
            'email' => $event['email'],
            'payload' => $payload,
        ]);

        $log?->forceFill(['complained' => true])->save();

        // Always suppress: mailing someone who reported spam is what gets a domain blocked.
        EmailSuppression::add($event['email'], 'complaint', 'Reported as spam');

        return response()->json(['status' => 'recorded', 'type' => 'complaint']);
    }

    private function recordDelivered(?EmailLog $log)
    {
        $log?->forceFill(['delivered_at' => $log->delivered_at ?: now()])->save();

        return response()->json(['status' => 'recorded', 'type' => 'delivered']);
    }
}
