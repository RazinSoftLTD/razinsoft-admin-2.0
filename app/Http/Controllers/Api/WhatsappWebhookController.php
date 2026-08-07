<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsappMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappMessage;
use App\Services\WhatsappAutoReplyService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Meta calls this: GET to verify the endpoint, POST to deliver messages & status updates. */
class WhatsappWebhookController extends Controller
{
    /**
     * Verification handshake — echo hub.challenge when the token matches any Cloud API number.
     *
     * Each number carries its own verify token, so several can point at this one URL and Meta's
     * handshake still identifies which is calling.
     */
    public function verify(Request $request)
    {
        $token = (string) $request->query('hub_verify_token');

        $matches = $token !== '' && WhatsappAccount::where('driver', 'cloud_api')
            ->whereNotNull('verify_token')
            ->get()
            ->contains(fn (WhatsappAccount $a) => hash_equals((string) $a->verify_token, $token));

        if ($request->query('hub_mode') === 'subscribe' && $matches) {
            return response($request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Which of our numbers is this about? Meta names it in the payload, which is what
                // lets several Cloud API numbers share one webhook URL.
                $account = WhatsappAccount::where('driver', 'cloud_api')
                    ->where('phone_number_id', $value['metadata']['phone_number_id'] ?? null)
                    ->first();

                if (! $account) {
                    Log::warning('WhatsApp webhook for an unknown number.', [
                        'phone_number_id' => $value['metadata']['phone_number_id'] ?? null,
                    ]);

                    continue;
                }

                // Signature is checked per number, against that number's own app secret.
                if ($account->app_secret && ! $this->signatureMatches($request, $account)) {
                    return response('Invalid signature', 403);
                }

                $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach ($value['messages'] ?? [] as $msg) {
                    $this->storeInbound($msg, $contacts->get($msg['from'] ?? '') ?? [], $account);
                }
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->applyStatus($status);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function signatureMatches(Request $request, WhatsappAccount $account): bool
    {
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $account->app_secret);

        return hash_equals($expected, (string) $request->header('X-Hub-Signature-256', ''));
    }

    private function storeInbound(array $msg, array $contact, WhatsappAccount $account): void
    {
        $waId = $msg['from'] ?? null;
        if (! $waId) {
            return;
        }

        // Scoped to the number it arrived on — the same person writing to two of our numbers is
        // two conversations, and the reply has to go back out the way it came in.
        $chat = WhatsappChat::firstOrCreate(['wa_id' => $waId, 'account_id' => $account->id], [
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
            // Bound to the account the message arrived on: its token is the one Meta will accept.
            if ($stored = WhatsappService::for($account)->downloadMedia($mediaId)) {
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
            'last_message_preview' => Str::limit($body ?: ucfirst($type), 120),
            'unread_count' => $chat->unread_count + 1,
            'status' => $chat->status === 'resolved' ? 'open' : $chat->status,
        ]);

        // Never let a broadcasting hiccup (e.g. Reverb down) fail the webhook.
        try {
            event(new WhatsappMessageReceived($chat->id, $message->id, 'in'));
        } catch (\Throwable) {
        }

        // Razin AI gets the same chance here as on a paired number. Run after the response so
        // Meta's webhook never waits on OpenAI, and never let a failure break message intake —
        // Meta retries a webhook it thinks failed, which would duplicate the whole message.
        $chatId = $chat->id;
        $messageId = $message->id;
        app()->terminating(function () use ($chatId, $messageId) {
            try {
                $chat = WhatsappChat::find($chatId);
                $message = WhatsappMessage::find($messageId);
                if ($chat && $message) {
                    app(WhatsappAutoReplyService::class)->maybeReply($chat, $message);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
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
