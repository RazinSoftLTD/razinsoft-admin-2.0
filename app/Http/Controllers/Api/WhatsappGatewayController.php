<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsappMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\User;
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

        return match ($request->input('event')) {
            'connection' => $this->onConnection($request, $settings),
            'message' => $this->onMessage($request),
            'status' => $this->onStatus($request),
            'reaction' => $this->onReaction($request),
            default => response()->json(['ok' => true]),
        };
    }

    private function onConnection(Request $request, WhatsappSetting $settings)
    {
        $settings->update([
            'session_state' => $request->input('state', 'disconnected'),
            'is_connected' => $request->input('state') === 'connected',
            'display_number' => $request->input('number') ?: $settings->display_number,
            'connected_at' => $request->input('state') === 'connected' ? now() : $settings->connected_at,
        ]);

        return response()->json(['ok' => true]);
    }

    private function onMessage(Request $request)
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

        $chat = WhatsappChat::firstOrCreate(['wa_id' => $waId], [
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

        $waMsgId = $request->input('id');
        if ($waMsgId && WhatsappMessage::where('wa_message_id', $waMsgId)->exists()) {
            return response()->json(['ok' => true]);
        }

        $type = $request->input('type', 'text');
        [$mediaPath, $mediaMime, $mediaName] = $this->storeMedia($request);

        $message = $chat->messages()->create([
            'wa_message_id' => $waMsgId,
            'direction' => $fromMe ? 'out' : 'in',
            'sender_name' => $isGroup && ! $fromMe ? $request->input('sender_name') : null,
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
    private function onReaction(Request $request)
    {
        $id = $request->input('id');
        if ($id) {
            $message = WhatsappMessage::where('wa_message_id', $id)->first();
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

    private function onStatus(Request $request)
    {
        if ($request->input('id') && $request->input('status')) {
            WhatsappMessage::where('wa_message_id', $request->input('id'))
                ->where('direction', 'out')
                ->update(['status' => $request->input('status')]);
        }

        return response()->json(['ok' => true]);
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
            $binary = str_starts_with($data, 'http')
                ? @file_get_contents($data)
                : base64_decode(preg_replace('#^data:[^;]+;base64,#', '', $data));
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
