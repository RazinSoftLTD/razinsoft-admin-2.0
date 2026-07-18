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

        $response = $this->responseMetrics($accounts->pluck('id')->all());

        $stats = $accounts->mapWithKeys(fn ($a) => [$a->id => [
            'chats' => (int) ($chatStats[$a->id]->chats ?? 0),
            'unread' => (int) ($chatStats[$a->id]->unread ?? 0),
            'messages' => (int) ($msgCounts[$a->id] ?? 0),
            'last_at' => $chatStats[$a->id]->last_at ?? null,
            'avg_response' => $response[$a->id]['avg'] ?? null,
            'response_rate' => $response[$a->id]['rate'] ?? null,
        ]]);

        return view('admin.whatsapp.activity', compact('accounts', 'stats'));
    }

    /**
     * Per-account team responsiveness: average first-response time and response rate.
     * A "turn" starts at the first client message that isn't yet answered; it's answered when the
     * team sends the next outgoing message. avg = mean answer time; rate = answered / total turns.
     */
    private function responseMetrics(array $accountIds): array
    {
        if (! $accountIds) {
            return [];
        }

        $rows = WhatsappMessage::query()
            ->join('whatsapp_chats', 'whatsapp_chats.id', '=', 'whatsapp_messages.chat_id')
            ->whereIn('whatsapp_chats.account_id', $accountIds)
            ->whereNotNull('whatsapp_messages.sent_at')
            ->orderBy('whatsapp_chats.account_id')
            ->orderBy('whatsapp_messages.chat_id')
            ->orderBy('whatsapp_messages.sent_at')
            ->orderBy('whatsapp_messages.id')
            ->get(['whatsapp_chats.account_id as acc', 'whatsapp_messages.chat_id as chat', 'whatsapp_messages.direction as dir', 'whatsapp_messages.sent_at as at']);

        // acc => ['sum'=>seconds, 'answered'=>n, 'total'=>n]; per-chat "awaiting" state.
        $agg = [];
        $chatState = []; // chat_id => questionTimestamp|null

        foreach ($rows as $r) {
            $agg[$r->acc] ??= ['sum' => 0, 'answered' => 0, 'total' => 0];
            $ts = strtotime((string) $r->at);

            if ($r->dir === 'in') {
                if (! isset($chatState[$r->chat])) {          // new unanswered turn
                    $chatState[$r->chat] = $ts;
                    $agg[$r->acc]['total']++;
                }
            } else { // 'out'
                if (isset($chatState[$r->chat])) {
                    $delta = $ts - $chatState[$r->chat];
                    if ($delta >= 0) {
                        $agg[$r->acc]['sum'] += $delta;
                        $agg[$r->acc]['answered']++;
                    }
                    unset($chatState[$r->chat]);              // turn closed
                }
            }
        }

        $out = [];
        foreach ($agg as $acc => $a) {
            $out[$acc] = [
                'avg' => $a['answered'] ? $this->humanDuration((int) round($a['sum'] / $a['answered'])) : null,
                'rate' => $a['total'] ? (int) round($a['answered'] / $a['total'] * 100) : null,
            ];
        }

        return $out;
    }

    /** Seconds → "45s" / "3m 20s" / "2h 10m" / "1d 4h". */
    private function humanDuration(int $s): string
    {
        if ($s < 60) {
            return $s.'s';
        }
        if ($s < 3600) {
            return floor($s / 60).'m '.($s % 60).'s';
        }
        if ($s < 86400) {
            return floor($s / 3600).'h '.floor(($s % 3600) / 60).'m';
        }

        return floor($s / 86400).'d '.floor(($s % 86400) / 3600).'h';
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
