<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FollowUpController extends Controller
{
    /** Which quick-filter tabs the module exposes (label + description shown in the UI). */
    public const VIEWS = ['all', 'today', 'tomorrow', 'week', 'upcoming', 'overdue', 'completed'];

    /**
     * Aggregated follow-up list across every lead the user may see, with dashboard
     * summary cards, quick-view tabs and advanced filters. Never creates follow-ups.
     */
    public function index(Request $request)
    {
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'all';

        $q = LeadFollowUp::query()
            ->with(['lead:id,full_name,company_name,phone,dial_code,lead_source,is_whatsapp', 'assignee:id,name'])
            ->join('leads', 'leads.id', '=', 'lead_follow_ups.lead_id')
            ->select('lead_follow_ups.*')
            ->orderByRaw("CASE lead_follow_ups.status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderBy('lead_follow_ups.scheduled_at');

        $request->user()->applyScope($q, 'follow_ups', 'view');

        $this->applyView($q, $view);
        $this->applyFilters($q, $request);

        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100]) ? (int) $request->query('per_page') : 10;
        $followUps = $q->paginate($perPage)->withQueryString();

        if ($request->ajax()) {
            return view('admin.follow-ups._results', compact('followUps', 'perPage'));
        }

        return view('admin.follow-ups.index', [
            'followUps' => $followUps,
            'view' => $view,
            'perPage' => $perPage,
            'cards' => $this->cards($request),
            'users' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'sources' => Lead::sourceOptions(),
        ]);
    }

    /** Monthly calendar — every follow-up on its scheduled date; clicking opens the lead. */
    public function calendar(Request $request)
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();

        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $q = LeadFollowUp::query()
            ->with(['lead:id,full_name,company_name'])
            ->whereBetween('scheduled_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
            ->orderBy('scheduled_at');
        $request->user()->applyScope($q, 'follow_ups', 'view');

        $byDay = $q->get()->groupBy(fn ($f) => $f->scheduled_at->toDateString());

        // Build the week rows for the grid.
        $weeks = [];
        for ($day = $gridStart->copy(); $day->lte($gridEnd); ) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $day->copy();
                $day->addDay();
            }
            $weeks[] = $week;
        }

        return view('admin.follow-ups.calendar', [
            'month' => $month,
            'weeks' => $weeks,
            'byDay' => $byDay,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }

    /** Constrain a scoped clone to one bucket for the summary cards. */
    private function scoped(Request $request)
    {
        $q = LeadFollowUp::query();
        $request->user()->applyScope($q, 'follow_ups', 'view');

        return $q;
    }

    /** Dashboard summary counts (all within the user's scope). */
    private function cards(Request $request): array
    {
        return [
            'today' => (clone $this->scoped($request))->where('status', 'pending')->whereDate('scheduled_at', today())->count(),
            'upcoming' => (clone $this->scoped($request))->where('status', 'pending')->whereDate('scheduled_at', '>', today())->count(),
            'overdue' => (clone $this->scoped($request))->where('status', 'pending')->whereDate('scheduled_at', '<', today())->count(),
            'completed_today' => (clone $this->scoped($request))->where('status', 'done')->whereDate('completed_at', today())->count(),
            'pending' => (clone $this->scoped($request))->where('status', 'pending')->count(),
        ];
    }

    /** Quick-view tab → query constraint. Buckets are day-based so they never overlap. */
    private function applyView($q, string $view): void
    {
        match ($view) {
            'today' => $q->where('lead_follow_ups.status', 'pending')->whereDate('lead_follow_ups.scheduled_at', today()),
            'tomorrow' => $q->where('lead_follow_ups.status', 'pending')->whereDate('lead_follow_ups.scheduled_at', today()->addDay()),
            'week' => $q->where('lead_follow_ups.status', 'pending')->whereBetween('lead_follow_ups.scheduled_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'upcoming' => $q->where('lead_follow_ups.status', 'pending')->whereDate('lead_follow_ups.scheduled_at', '>', today()),
            'overdue' => $q->where('lead_follow_ups.status', 'pending')->whereDate('lead_follow_ups.scheduled_at', '<', today()),
            'completed' => $q->where('lead_follow_ups.status', 'done'),
            default => null,
        };
    }

    /** Advanced filters: assigned user, type, lead source, explicit status, and search. */
    private function applyFilters($q, Request $request): void
    {
        if ($assigned = $request->query('assigned')) {
            $q->where('lead_follow_ups.user_id', $assigned);
        }
        if ($type = $request->query('type')) {
            $q->where('lead_follow_ups.type', $type);
        }
        if ($status = $request->query('status')) {
            $q->where('lead_follow_ups.status', $status);
        }
        if ($source = $request->query('source')) {
            $q->where('leads.lead_source', $source);
        }
        if ($search = trim((string) $request->query('search'))) {
            $q->where(function ($w) use ($search) {
                $w->where('leads.full_name', 'like', "%{$search}%")
                    ->orWhere('leads.company_name', 'like', "%{$search}%")
                    ->orWhere('leads.phone', 'like', "%{$search}%")
                    ->orWhere('leads.lead_code', 'like', "%{$search}%");
            });
        }
    }
}
