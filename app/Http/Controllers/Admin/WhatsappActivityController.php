<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;

/**
 * Activity › WhatsApp — read-only oversight of every connected number (active/inactive) and its
 * conversation history. Gated by the `whatsapp.activity` permission (super admins + granted users);
 * unlike the inbox, it is NOT limited to numbers the viewer is assigned to.
 */
class WhatsappActivityController extends Controller
{
    public function index()
    {
        $accounts = WhatsappAccount::with('users:id,name')->orderBy('position')->orderBy('id')->get();

        // Per-account stats in a couple of grouped queries.
        $chatStats = WhatsappChat::selectRaw('account_id, count(*) chats, sum(case when unread_count>0 then 1 else 0 end) unread, max(last_message_at) last_at')
            ->groupBy('account_id')->get()->keyBy('account_id');
        $msgCounts = WhatsappMessage::selectRaw('whatsapp_chats.account_id, count(*) c')
            ->join('whatsapp_chats', 'whatsapp_chats.id', '=', 'whatsapp_messages.chat_id')
            ->groupBy('whatsapp_chats.account_id')->pluck('c', 'account_id');

        $stats = $accounts->mapWithKeys(fn ($a) => [$a->id => [
            'chats' => (int) ($chatStats[$a->id]->chats ?? 0),
            'unread' => (int) ($chatStats[$a->id]->unread ?? 0),
            'messages' => (int) ($msgCounts[$a->id] ?? 0),
            'last_at' => $chatStats[$a->id]->last_at ?? null,
        ]]);

        return view('admin.whatsapp.activity', compact('accounts', 'stats'));
    }

    public function show(WhatsappAccount $account)
    {
        $chats = $account->chats()->orderByDesc('last_message_at')->orderByDesc('id')->limit(300)->get();

        return view('admin.whatsapp.activity-detail', compact('account', 'chats'));
    }

    public function thread(WhatsappAccount $account, WhatsappChat $chat)
    {
        abort_unless($chat->account_id === $account->id, 404);

        $messages = $chat->messages()->with('agent:id,name')->get()->map(function ($m) {
            $at = $m->sent_at ?? $m->created_at;

            return [
                'id' => $m->id, 'direction' => $m->direction, 'type' => $m->type,
                'sender_name' => $m->sender_name, 'body' => $m->deleted_at ? null : $m->body,
                'media' => $m->deleted_at ? null : $m->mediaUrl(), 'media_mime' => $m->media_mime, 'media_name' => $m->media_name,
                'deleted' => (bool) $m->deleted_at, 'edited' => (bool) $m->edited_at,
                'reaction' => $m->reaction, 'my_reaction' => $m->my_reaction,
                'status' => $m->status, 'agent' => $m->agent?->name,
                'at' => $at->format('d M, h:i A'),
            ];
        });

        return response()->json([
            'name' => $chat->displayName(),
            'wa_id' => $chat->phoneLabel(),
            'messages' => $messages,
        ]);
    }
}
