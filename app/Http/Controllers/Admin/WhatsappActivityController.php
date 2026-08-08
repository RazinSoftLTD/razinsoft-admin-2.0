<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Activity › WhatsApp — read-only oversight of every connected number (active/inactive) and its
 * conversation history. Gated by the `whatsapp.activity` permission (super admins + granted users);
 * unlike the inbox, it is NOT limited to numbers the viewer is assigned to.
 */
class WhatsappActivityController extends Controller
{
    /** Page sizes offered for the new-conversation list. */
    private const PER_PAGE = [10, 20, 100];

    public function index(Request $request)
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

        return view('admin.whatsapp.activity', array_merge(
            compact('accounts', 'stats'),
            $this->periodReport($request),
        ));
    }

    /**
     * The window the report covers: today by default, or a named span, or two dates.
     *
     * Returns [from, to, key, label] — `to` is the end of its day, so "this month" includes
     * everything that has happened today rather than stopping at midnight.
     */
    private function period(Request $request): array
    {
        $key = (string) $request->query('period', 'today');
        $today = today();

        [$from, $to, $label] = match ($key) {
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek(), 'This week'],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), 'This month'],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear(), 'This year'],
            'custom' => (function () use ($request, $today) {
                $from = $this->safeDate($request->query('from')) ?? $today->copy();
                $to = $this->safeDate($request->query('to')) ?? $from->copy();
                // Dates the wrong way round is a slip, not a request for nothing.
                if ($to->lt($from)) {
                    [$from, $to] = [$to, $from];
                }

                return [$from, $to, 'Custom'];
            })(),
            default => [$today->copy(), $today->copy(), 'Today'],
        };

        if (! in_array($key, ['today', 'week', 'month', 'year', 'custom'], true)) {
            $key = 'today';
        }

        // Never look past today: a range running into the future reads as a dead stretch.
        if ($to->gt($today)) {
            $to = $today->copy();
        }

        return [$from->startOfDay(), $to->endOfDay(), $key, $label];
    }

    private function safeDate(?string $raw): ?Carbon
    {
        if (! $raw) {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * What came in today, and how it was judged.
     *
     * The cards below answer "how is each number doing overall", which never changes much from one
     * day to the next. What nobody could see was the day itself: how many people wrote in for the
     * first time, and how many of them anyone has since decided about.
     *
     * A chat's row is created the first time that number ever writes, so its created_at is the day
     * we met them — the definition of new that matters here, rather than a chat that merely spoke
     * again today.
     */
    private function periodReport(Request $request): array
    {
        [$from, $to, $periodKey, $periodLabel] = $this->period($request);

        $base = WhatsappChat::whereBetween('created_at', [$from, $to])->whereNull('blocked_at');

        // Counted over the whole window, not the page in front of you — a chip that only counted
        // the current page would disagree with itself as you paged.
        $inPeriod = (clone $base)->selectRaw('lead_quality, count(*) c')->groupBy('lead_quality')->pluck('c', 'lead_quality');
        $newCount = (int) $inPeriod->sum();

        // Overall alongside the period's, because one figure without the other says nothing about
        // whether a quiet day is unusual.
        $overall = WhatsappChat::whereNull('blocked_at')
            ->selectRaw('lead_quality, count(*) c')->groupBy('lead_quality')->pluck('c', 'lead_quality');

        $quality = [];
        foreach (array_keys(WhatsappChat::LEAD_QUALITIES) as $key) {
            $quality[$key] = [
                'today' => (int) ($inPeriod[$key] ?? 0),
                'total' => (int) ($overall[$key] ?? 0),
            ];
        }
        // Not judged yet is the number worth acting on, so it is counted like the rest.
        // NULL lead_quality comes back under an empty-string key; reading it as null offset is
        // deprecated in PHP 8.4 and filled the log with notices.
        $quality['unset'] = [
            'today' => (int) ($inPeriod->get('') ?? 0),
            'total' => (int) ($overall->get('') ?? 0),
        ];

        // The list is paged, so its quality filter has to be a real query rather than a
        // client-side hide — otherwise it would only ever filter the rows already on screen.
        $wantedQuality = (string) $request->query('quality', 'all');
        $list = (clone $base)->with('account:id,name,color')
            ->orderByDesc('created_at')->orderByDesc('id');

        if ($wantedQuality === 'unset') {
            $list->whereNull('lead_quality');
        } elseif (array_key_exists($wantedQuality, WhatsappChat::LEAD_QUALITIES)) {
            $list->where('lead_quality', $wantedQuality);
        } else {
            $wantedQuality = 'all';
        }

        // Page size is the reader's call: a quick glance wants ten, an audit wants a hundred.
        $perPage = (int) $request->query('per_page', 20);
        if (! in_array($perPage, self::PER_PAGE, true)) {
            $perPage = 20;
        }

        $new = $list->paginate($perPage)->withQueryString();

        return [
            'today' => [
                'new_chats' => $newCount,
                'messages_in' => WhatsappMessage::whereBetween('sent_at', [$from, $to])->where('direction', 'in')->count(),
                'messages_out' => WhatsappMessage::whereBetween('sent_at', [$from, $to])->where('direction', 'out')->count(),
                'active_chats' => WhatsappMessage::whereBetween('sent_at', [$from, $to])->distinct()->count('chat_id'),
            ],
            'todayQuality' => $quality,
            'todayChats' => $new,
            'periodKey' => $periodKey,
            'periodLabel' => $periodLabel,
            'periodFrom' => $from,
            'periodTo' => $to,
            'qualityFilter' => $wantedQuality,
            'newTotal' => $newCount,
            'perPage' => $perPage,
        ];
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
