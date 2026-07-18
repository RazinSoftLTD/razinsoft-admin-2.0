<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsappMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Endpoint the Node.js Baileys gateway calls to push events into Laravel:
 *  - connection state changes (qr / connected / disconnected)
 *  - inbound messages (already normalised by the gateway, media as base64 or URL)
 *  - outbound delivery/read receipts
 * Authenticated by the shared gateway secret.
 */
class WhatsappGatewayController extends Controller
{
    public function handle(Request $request)
    {
        $settings = WhatsappSetting::current();
        if (! $settings->gateway_secret || ! hash_equals((string) $settings->gateway_secret, (string) $request->header('X-Gateway-Secret'))) {
            return response('Unauthorized', 401);
        }

        // Which connected number (account) did this event come from?
        $account = WhatsappAccount::where('session_key', $request->input('session', 'default'))->first();

        return match ($request->input('event')) {
            'connection' => $this->onConnection($request, $account),
            'message' => $this->onMessage($request, $account),
            'status' => $this->onStatus($request, $account),
            'reaction' => $this->onReaction($request, $account),
            default => response()->json(['ok' => true]),
        };
    }

    private function onConnection(Request $request, ?WhatsappAccount $account)
    {
        if (! $account) {
            return response()->json(['ok' => true]);
        }
        $number = $request->input('number');
        $account->update([
            'session_state' => $request->input('state', 'disconnected'),
            'is_connected' => $request->input('state') === 'connected',
            'display_number' => $number ?: $account->display_number,
            'connected_at' => $request->input('state') === 'connected' ? now() : $account->connected_at,
        ]);

        // Re-added the same number? Pull its old chats (from a deleted/other account) back in.
        if ($request->input('state') === 'connected' && $number) {
            $this->relinkPriorChats($account, $number);
        }

        return response()->json(['ok' => true]);
    }

    /** Move chats from any prior account (incl. soft-deleted) with the same number into this one. */
    private function relinkPriorChats(WhatsappAccount $account, string $number): void
    {
        $priors = WhatsappAccount::withTrashed()
            ->where('id', '!=', $account->id)
            ->where('display_number', $number)
            ->get();

        foreach ($priors as $prior) {
            foreach (WhatsappChat::where('account_id', $prior->id)->get() as $oldChat) {
                $existing = WhatsappChat::where('account_id', $account->id)->where('wa_id', $oldChat->wa_id)->first();
                if ($existing) {
                    // Same contact already synced under the new account — fold the old thread into it.
                    WhatsappMessage::where('chat_id', $oldChat->id)->update(['chat_id' => $existing->id]);
                    \DB::table('whatsapp_notes')->where('chat_id', $oldChat->id)->update(['chat_id' => $existing->id]);
                    \DB::table('whatsapp_chat_label')->where('chat_id', $oldChat->id)->delete();
                    $oldChat->delete();
                } else {
                    $oldChat->update(['account_id' => $account->id]);
                }
            }
            $prior->forceDelete();
        }
    }

    private function onMessage(Request $request, ?WhatsappAccount $account)
    {
        $waId = $request->input('from');
        if (! $waId) {
            return response()->json(['ok' => false]);
        }
        // A message we sent ourselves (from the phone or another linked device) belongs in the
        // same thread as an outgoing bubble — 'from' is already the recipient's address here.
        $fromMe = $request->boolean('from_me');
        // Replayed from phone history — import silently (no unread bump, no live broadcast).
        $historic = $request->boolean('historic');

        $isGroup = $request->input('chat_type') === 'group' || str_contains($waId, '@g.us');
        $phone = $request->input('phone');
        // Groups have many senders, so don't auto-match them to a single client.
        $matchKey = $isGroup ? null : ($phone ?: (str_contains($waId, '@lid') ? null : $waId));

        // A chat is unique per account + wa_id (the same person can message two of our numbers).
        $chat = WhatsappChat::firstOrCreate(['account_id' => $account?->id, 'wa_id' => $waId], [
            'phone' => $isGroup ? null : $phone,
            'chat_type' => $isGroup ? 'group' : 'single',
            'profile_name' => $request->input('name'),
            'client_id' => $matchKey ? User::clients()->where('phone', 'like', '%'.substr($matchKey, -9))->value('id') : null,
            'status' => 'open',
            'unread_count' => 0,
        ]);

        // Backfill the phone if a later message resolves it (LID numbers can arrive after the first msg).
        if (! $isGroup && $phone && ! $chat->phone) {
            $chat->phone = $phone;
            if (! $chat->client_id) {
                $chat->client_id = User::clients()->where('phone', 'like', '%'.substr($phone, -9))->value('id');
            }
        }

        // Dedup within THIS chat only — the same wa_message_id legitimately appears in two accounts
        // when one of our numbers messages another (sender's outgoing copy + receiver's incoming copy).
        $waMsgId = $request->input('id');
        if ($waMsgId && $chat->messages()->where('wa_message_id', $waMsgId)->exists()) {
            return response()->json(['ok' => true]);
        }

        $type = $request->input('type', 'text');
        [$mediaPath, $mediaMime, $mediaName] = $this->storeMedia($request);

        // Quoted (reply-to) reference carried on the message.
        $quotedFields = [];
        if ($request->filled('quoted_id')) {
            $qPart = $request->input('quoted_participant');
            $ourNum = $account?->display_number;
            $sender = ($qPart && $ourNum && str_starts_with((string) $qPart, (string) $ourNum))
                ? 'You'
                : ($isGroup && $qPart
                    ? $chat->messages()->where('sender_jid', $qPart)->whereNotNull('sender_name')->latest('id')->value('sender_name')
                    : $chat->displayName());
            $quotedFields = [
                'quoted_id' => $request->input('quoted_id'),
                'quoted_body' => $request->input('quoted_body'),
                'quoted_sender' => $sender,
            ];
        }

        $message = $chat->messages()->create($quotedFields + [
            'wa_message_id' => $waMsgId,
            'direction' => $fromMe ? 'out' : 'in',
            'sender_name' => $isGroup && ! $fromMe ? $request->input('sender_name') : null,
            'sender_jid' => $isGroup && ! $fromMe ? $request->input('participant') : null,
            'type' => $type,
            'body' => $request->input('text'),
            'media_path' => $mediaPath,
            'media_mime' => $mediaMime,
            'media_name' => $mediaName ?: $request->input('filename'),
            'status' => $fromMe ? 'sent' : 'received',
            'sent_at' => $request->input('timestamp') ? now()->setTimestamp((int) $request->input('timestamp')) : now(),
        ]);

        // Keep last_message_* pointing at the newest message (historic imports may arrive out of order).
        $isNewest = ! $chat->last_message_at || $message->sent_at->gte($chat->last_message_at);
        $chat->update([
            'last_message_at' => $isNewest ? $message->sent_at : $chat->last_message_at,
            'last_message_preview' => $isNewest ? Str::limit($request->input('text') ?: ucfirst($type), 120) : $chat->last_message_preview,
            // Only live inbound messages bump the unread badge; our own replies and history don't.
            'unread_count' => ($fromMe || $historic) ? $chat->unread_count : $chat->unread_count + 1,
            'name' => $chat->name ?: ($fromMe ? null : $request->input('name')),
            'status' => $chat->status === 'resolved' && ! $historic ? 'open' : $chat->status,
        ]);

        if (! $historic) {
            try {
                event(new WhatsappMessageReceived($chat->id, $message->id, $fromMe ? 'out' : 'in'));
            } catch (\Throwable) {
            }
        }

        return response()->json(['ok' => true]);
    }

    /** An emoji reaction landed on one of our messages — attach it (empty text = reaction removed). */
    private function onReaction(Request $request, ?WhatsappAccount $account)
    {
        $id = $request->input('id');
        if ($id) {
            $message = $this->scopedMessage($id, $account);
            if ($message) {
                // Our own reaction (from the phone/another device) vs the other party's.
                $column = $request->boolean('from_me') ? 'my_reaction' : 'reaction';
                $message->update([$column => $request->input('emoji') ?: null]);
                try {
                    event(new WhatsappMessageReceived($message->chat_id, $message->id, 'reaction'));
                } catch (\Throwable) {
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function onStatus(Request $request, ?WhatsappAccount $account)
    {
        if ($request->input('id') && $request->input('status')) {
            $message = $this->scopedMessage($request->input('id'), $account, 'out');
            $message?->update(['status' => $request->input('status')]);
        }

        return response()->json(['ok' => true]);
    }

    /** Find a message by wa_message_id, scoped to the account's chats (ids can repeat across numbers). */
    private function scopedMessage(string $waMessageId, ?WhatsappAccount $account, ?string $direction = null): ?WhatsappMessage
    {
        $q = WhatsappMessage::where('wa_message_id', $waMessageId);
        if ($direction) {
            $q->where('direction', $direction);
        }
        if ($account) {
            $q->whereHas('chat', fn ($c) => $c->where('account_id', $account->id));
        }

        return $q->first();
    }

    /** Media may arrive as base64 (small files) or a URL the gateway hosts. Returns [path, mime, name]. */
    private function storeMedia(Request $request): array
    {
        if (! $request->filled('media')) {
            return [null, null, null];
        }
        $mime = $request->input('media_mime', 'application/octet-stream');
        $ext = explode(';', explode('/', $mime)[1] ?? 'bin')[0];
        $path = 'whatsapp/'.Str::random(24).'.'.$ext;

        try {
            $data = $request->input('media');
            // Strip the whole data-URI header up to the first comma — the mime may carry parameters
            // (e.g. "audio/ogg; codecs=opus"), which the old `[^;]+;base64,` pattern failed to match.
            $binary = str_starts_with($data, 'http')
                ? @file_get_contents($data)
                : base64_decode(preg_replace('#^data:[^,]*,#', '', $data));
            if ($binary === false || $binary === null) {
                return [null, null, null];
            }
            Storage::disk('public')->put($path, $binary);

            return [$path, $mime, $request->input('filename')];
        } catch (\Throwable) {
            return [null, null, null];
        }
    }
}
