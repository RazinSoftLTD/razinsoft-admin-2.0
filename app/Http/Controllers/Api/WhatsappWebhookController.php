<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsappMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappChat;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;

/** Meta calls this: GET to verify the endpoint, POST to deliver messages & status updates. */
class WhatsappWebhookController extends Controller
{
    /** Verification handshake — echo hub.challenge when the verify token matches. */
    public function verify(Request $request)
    {
        $settings = WhatsappSetting::current();
        if ($request->query('hub_mode') === 'subscribe'
            && $request->query('hub_verify_token') === $settings->verify_token) {
            return response($request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        $settings = WhatsappSetting::current();

        // Verify the payload signature (X-Hub-Signature-256) when an app secret is set.
        if ($settings->app_secret) {
            $sig = $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $settings->app_secret);
            if (! hash_equals($expected, $sig)) {
                return response('Invalid signature', 403);
            }
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach ($value['messages'] ?? [] as $msg) {
                    $this->storeInbound($msg, $contacts->get($msg['from'] ?? '') ?? []);
                }
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->applyStatus($status);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function storeInbound(array $msg, array $contact): void
    {
        $waId = $msg['from'] ?? null;
        if (! $waId) {
            return;
        }

        $chat = WhatsappChat::firstOrCreate(['wa_id' => $waId], [
            'profile_name' => $contact['profile']['name'] ?? null,
            'client_id' => User::clients()->where('phone', 'like', '%'.substr($waId, -9))->value('id'),
            'status' => 'open',
            'unread_count' => 0,
        ]);
        if (! $chat->profile_name && isset($contact['profile']['name'])) {
            $chat->profile_name = $contact['profile']['name'];
        }

        // Idempotency — skip if we already stored this WhatsApp message id.
        if (! empty($msg['id']) && WhatsappMessage::where('wa_message_id', $msg['id'])->exists()) {
            return;
        }

        [$type, $body, $mediaId, $mediaName] = $this->parse($msg);
        $mediaPath = $mediaMime = null;
        if ($mediaId) {
            if ($stored = app(WhatsappService::class)->downloadMedia($mediaId)) {
                [$mediaPath, $mediaMime] = $stored;
            }
        }

        $message = $chat->messages()->create([
            'wa_message_id' => $msg['id'] ?? null,
            'direction' => 'in',
            'type' => $type,
            'body' => $body,
            'media_path' => $mediaPath,
            'media_mime' => $mediaMime,
            'media_name' => $mediaName,
            'status' => 'received',
            'sent_at' => isset($msg['timestamp']) ? now()->setTimestamp((int) $msg['timestamp']) : now(),
        ]);

        $chat->update([
            'last_message_at' => $message->sent_at,
            'last_message_preview' => \Illuminate\Support\Str::limit($body ?: ucfirst($type), 120),
            'unread_count' => $chat->unread_count + 1,
            'status' => $chat->status === 'resolved' ? 'open' : $chat->status,
        ]);

        // Never let a broadcasting hiccup (e.g. Reverb down) fail the webhook.
        try {
            event(new WhatsappMessageReceived($chat->id, $message->id, 'in'));
        } catch (\Throwable) {
        }
    }

    /** Normalise a message payload → [type, body, mediaId, filename]. */
    private function parse(array $msg): array
    {
        $type = $msg['type'] ?? 'text';

        return match ($type) {
            'text' => ['text', $msg['text']['body'] ?? '', null, null],
            'image' => ['image', $msg['image']['caption'] ?? null, $msg['image']['id'] ?? null, null],
            'video' => ['video', $msg['video']['caption'] ?? null, $msg['video']['id'] ?? null, null],
            'audio' => ['audio', null, $msg['audio']['id'] ?? null, null],
            'voice' => ['audio', null, $msg['audio']['id'] ?? ($msg['voice']['id'] ?? null), null],
            'document' => ['document', $msg['document']['caption'] ?? null, $msg['document']['id'] ?? null, $msg['document']['filename'] ?? 'document'],
            'sticker' => ['sticker', null, $msg['sticker']['id'] ?? null, null],
            'location' => ['location', ($msg['location']['latitude'] ?? '').','.($msg['location']['longitude'] ?? ''), null, null],
            default => ['text', $msg[$type]['body'] ?? '[Unsupported message]', null, null],
        };
    }

    private function applyStatus(array $status): void
    {
        if (empty($status['id']) || empty($status['status'])) {
            return;
        }
        WhatsappMessage::where('wa_message_id', $status['id'])
            ->where('direction', 'out')
            ->update(['status' => $status['status']]);   // sent | delivered | read | failed
    }
}
