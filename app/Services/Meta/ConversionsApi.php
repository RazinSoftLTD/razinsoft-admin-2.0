<?php

namespace App\Services\Meta;

use App\Models\MetaCapiSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends events to Meta's Conversions API — the server-side half of the pixel.
 *
 * Two things make this worth having. The browser pixel silently loses events to ad blockers,
 * tracking prevention and tabs closed mid-request; and the server knows the customer's real email
 * and phone, which is what Meta matches on, so the events it does send are worth more.
 *
 * Every event carries an `event_id`. If the browser pixel fires the same event with the same id,
 * Meta counts it once — without that the two halves double-count everything.
 */
class ConversionsApi
{
    public function __construct(private MetaCapiSetting $settings) {}

    public static function make(): self
    {
        return new self(MetaCapiSetting::current());
    }

    /**
     * Queue-free, fire-and-forget send. Returns true when Meta accepted the event.
     *
     * @param  array<string, mixed>  $custom     value, currency, contents…
     * @param  array<string, mixed>  $user       email, phone, first_name, last_name, city, country…
     */
    public function send(string $event, string $eventId, array $custom = [], array $user = [], ?Request $request = null, ?\DateTimeInterface $occurredAt = null): bool
    {
        if (! $this->settings->sends($event)) {
            return false;
        }

        $payload = [
            'data' => [array_filter([
                'event_name' => $event,
                // Defaults to now, which is right for anything happening as it is sent. Backfills
                // pass the real time — Meta rejects anything older than seven days, so the caller
                // is responsible for not offering it something it will refuse.
                'event_time' => ($occurredAt ?? now())->getTimestamp(),
                // The deduplication key. The browser pixel must send the same one.
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => $custom['source_url'] ?? config('brand.website'),
                'user_data' => $this->userData($user, $request),
                'custom_data' => $this->customData($custom),
            ], fn ($v) => $v !== null && $v !== [])],
        ];

        if ($this->settings->test_event_code) {
            $payload['test_event_code'] = $this->settings->test_event_code;
        }

        return $this->post($payload, $event);
    }

    /** Meta requires personal fields hashed with SHA-256, lower-cased and trimmed first. */
    private function userData(array $user, ?Request $request): array
    {
        $hash = fn (?string $v) => filled($v) ? hash('sha256', mb_strtolower(trim($v))) : null;

        // Digits only, with the country code and no leading +, is the format Meta matches on.
        $phone = preg_replace('/\D+/', '', (string) ($user['phone'] ?? '')) ?: null;

        $data = array_filter([
            'em' => $hash($user['email'] ?? null),
            'ph' => $phone ? hash('sha256', $phone) : null,
            'fn' => $hash($user['first_name'] ?? null),
            'ln' => $hash($user['last_name'] ?? null),
            'ct' => $hash($user['city'] ?? null),
            'country' => $hash($user['country'] ?? null),
            'external_id' => filled($user['id'] ?? null) ? hash('sha256', (string) $user['id']) : null,
        ]);

        // The pixel's own cookies raise match quality more than anything else we can send, so pass
        // them through when the event came from a browser request.
        if ($request) {
            $data['client_ip_address'] = $request->ip();
            $data['client_user_agent'] = $request->userAgent();
            $data = array_filter($data + [
                'fbp' => $request->cookie('_fbp'),
                'fbc' => $request->cookie('_fbc'),
            ]);
        }

        return $data;
    }

    private function customData(array $custom): array
    {
        return array_filter([
            'value' => isset($custom['value']) ? round((float) $custom['value'], 2) : null,
            'currency' => $custom['currency'] ?? null,
            'order_id' => $custom['order_id'] ?? null,
            'content_name' => $custom['content_name'] ?? null,
            'content_type' => $custom['content_type'] ?? null,
            // Carries the lead's channel (WhatsApp, Website, Facebook…) so ad reporting can tell
            // which source produces leads worth talking to.
            'content_category' => $custom['content_category'] ?? null,
            'contents' => $custom['contents'] ?? null,
            'num_items' => $custom['num_items'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function post(array $payload, string $event): bool
    {
        $url = sprintf('https://graph.facebook.com/%s/%s/events',
            $this->settings->api_version ?: 'v21.0', $this->settings->pixel_id);

        try {
            $res = Http::timeout(10)
                ->withToken($this->settings->access_token)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            // Tracking must never break the thing it is tracking.
            $this->record(false, $e->getMessage());
            Log::warning('Meta CAPI unreachable.', ['event' => $event, 'error' => $e->getMessage()]);

            return false;
        }

        $this->record($res->successful(), $res->successful() ? null : ($res->json('error.message') ?: 'Rejected.'));

        if (! $res->successful()) {
            Log::warning('Meta CAPI rejected an event.', ['event' => $event, 'error' => $res->json('error')]);
        }

        return $res->successful();
    }

    private function record(bool $ok, ?string $error): void
    {
        $this->settings->forceFill([
            'last_sent_at' => now(),
            'last_status' => $ok ? 'ok' : 'failed',
            'last_error' => $error,
        ])->save();
    }
}
