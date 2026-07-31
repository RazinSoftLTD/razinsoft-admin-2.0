<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappLink;
use App\Models\WhatsappLinkClick;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Activity → WhatsApp Button: build a link that opens a chat, then see how often it was followed.
 *
 * The link deliberately points here rather than at wa.me. A wa.me URL goes straight to WhatsApp and
 * we never learn that anyone used it, so counting clicks means owning the first hop.
 */
class WhatsappLinkController extends Controller
{
    /** The windows the report offers, and where each one starts. */
    private const RANGES = [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
        'year' => 'This year',
        'all' => 'All time',
        'custom' => 'Custom range',
    ];

    public function index(Request $request)
    {
        [$from, $to, $range] = $this->window($request);

        $clicksInWindow = function ($linkId = null) use ($from, $to) {
            return WhatsappLinkClick::query()
                ->when($linkId, fn ($q) => $q->where('link_id', $linkId))
                ->when($from, fn ($q) => $q->where('clicked_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('clicked_at', '<=', $to));
        };

        $links = WhatsappLink::withCount([
            'clicks as clicks_total',
            'clicks as clicks_window' => fn ($q) => $q
                ->when($from, fn ($w) => $w->where('clicked_at', '>=', $from))
                ->when($to, fn ($w) => $w->where('clicked_at', '<=', $to)),
        ])->latest('id')->get();

        $recent = WhatsappLinkClick::with('link:id,label,code')
            ->when($request->query('link'), fn ($q, $id) => $q->where('link_id', $id))
            ->when($from, fn ($q) => $q->where('clicked_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('clicked_at', '<=', $to))
            ->latest('clicked_at')
            ->paginate(25)->withQueryString();

        return view('admin.whatsapp-links.index', [
            'links' => $links,
            'recent' => $recent,
            'ranges' => self::RANGES,
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'totalClicks' => (clone $clicksInWindow())->count(),
            'byDay' => $this->byDay($from, $to, $request->query('link')),
            'byCountry' => $clicksInWindow($request->query('link'))
                ->selectRaw('country, COUNT(*) as hits')
                ->whereNotNull('country')->groupBy('country')->orderByDesc('hits')->limit(8)->get(),
            'byDevice' => $clicksInWindow($request->query('link'))
                ->selectRaw('device, COUNT(*) as hits')->groupBy('device')->pluck('hits', 'device'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'number' => ['required', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        // Normalised through libphonenumber so a number typed as 01711-257498 and one typed as
        // +8801711257498 produce the same link, and a typo is caught before it is shared.
        $parsed = Phone::normalize($data['number'], null, '+880');

        if (! $parsed) {
            return back()->withInput()->withErrors(['number' => 'That does not look like a valid phone number.']);
        }

        $link = WhatsappLink::create([
            'code' => WhatsappLink::newCode(),
            'label' => $data['label'] ?? null,
            'number' => $parsed['dial'].$parsed['number'],
            'message' => $data['message'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Link ready: '.$link->shortUrl());
    }

    /**
     * Change the number or the message without changing the link.
     *
     * That is the whole point of editing rather than replacing: the short link is already printed
     * on an ad, a card or a post, so the code must survive. Only what it forwards to changes.
     */
    public function update(Request $request, WhatsappLink $whatsappLink)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'number' => ['required', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $parsed = Phone::normalize($data['number'], null, '+880');

        if (! $parsed) {
            return back()->withErrors(['number' => 'That does not look like a valid phone number.']);
        }

        $whatsappLink->update([
            'label' => $data['label'] ?? null,
            'number' => $parsed['dial'].$parsed['number'],
            'message' => $data['message'] ?? null,
        ]);

        return back()->with('status', 'Updated. The link itself is unchanged — anything already sharing it keeps working.');
    }

    /**
     * Point the website's floating button at this link.
     *
     * Exactly one at a time: the site asks for "the" button and has to get one answer, so choosing
     * a new one clears the last. Doing it in a transaction keeps that true even if two people press
     * it at once.
     */
    public function useOnSite(WhatsappLink $whatsappLink)
    {
        DB::transaction(function () use ($whatsappLink) {
            WhatsappLink::where('id', '!=', $whatsappLink->id)->update(['is_site_button' => false]);
            $whatsappLink->update(['is_site_button' => true, 'is_active' => true]);
        });

        return back()->with('status', 'The website\'s floating WhatsApp button now uses this link — its clicks are counted here.');
    }

    /** Take the floating button off the site's links entirely. */
    public function clearSiteButton()
    {
        WhatsappLink::where('is_site_button', true)->update(['is_site_button' => false]);

        return back()->with('status', 'The website button falls back to its built-in number.');
    }

    public function toggle(Request $request, WhatsappLink $whatsappLink)
    {
        $whatsappLink->update(['is_active' => ! $whatsappLink->is_active]);

        return back()->with('status', $whatsappLink->is_active
            ? 'Link is counting clicks again.'
            : 'Link retired — it still opens the chat, it just stops counting.');
    }

    public function destroy(WhatsappLink $whatsappLink)
    {
        $whatsappLink->delete();   // clicks cascade

        return back()->with('status', 'Link and its click history deleted.');
    }

    /** Resolve the chosen window to a from/to pair. */
    private function window(Request $request): array
    {
        $range = $request->query('range', 'month');

        if ($range === 'custom') {
            $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : null;
            $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : null;

            return [$from, $to, 'custom'];
        }

        return match ($range) {
            'today' => [now()->startOfDay(), now()->endOfDay(), 'today'],
            'week' => [now()->startOfWeek(), now()->endOfWeek(), 'week'],
            'year' => [now()->startOfYear(), now()->endOfYear(), 'year'],
            'all' => [null, null, 'all'],
            default => [now()->startOfMonth(), now()->endOfMonth(), 'month'],
        };
    }

    /** Clicks per day across the window — the shape of the campaign, not just its total. */
    private function byDay(?Carbon $from, ?Carbon $to, $linkId = null)
    {
        return WhatsappLinkClick::query()
            ->when($linkId, fn ($q) => $q->where('link_id', $linkId))
            ->when($from, fn ($q) => $q->where('clicked_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('clicked_at', '<=', $to))
            ->selectRaw('DATE(clicked_at) as day, COUNT(*) as hits')
            ->groupBy('day')->orderBy('day')
            ->limit(120)
            ->get();
    }
}
