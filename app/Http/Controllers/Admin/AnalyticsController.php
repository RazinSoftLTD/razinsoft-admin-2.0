<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealFollowUp;
use App\Models\Lead;
use Illuminate\Http\Request;

/**
 * CRM Analytics — one hub for lead & deal reporting. Merges the old
 * Follow-up centre and Lead-by-Country pages, plus full lead/deal reports.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['reports', 'followups', 'country'], true) ? $request->query('tab') : 'reports';

        $data = match ($tab) {
            'followups' => ['buckets' => $this->followUps($request)],
            'country' => $this->country($request),
            default => $this->reports($request),
        };

        return view('admin.analytics.index', array_merge(['tab' => $tab], $data));
    }

    /* ------------------------------------------------------------- date range */

    /** Apply the selected day/week/month/year/custom window to a query column. */
    private function applyRange(Request $request, $query, string $column)
    {
        match ($request->query('date_range')) {
            'today' => $query->whereDate($column, today()),
            'week' => $query->whereBetween($column, [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereBetween($column, [now()->startOfMonth(), now()->endOfMonth()]),
            'year' => $query->whereBetween($column, [now()->startOfYear(), now()->endOfYear()]),
            default => null,
        };
        if ($from = $request->query('from')) $query->whereDate($column, '>=', $from);
        if ($to = $request->query('to')) $query->whereDate($column, '<=', $to);

        return $query;
    }

    /* ---------------------------------------------------------------- reports */

    private function reports(Request $request): array
    {
        $user = $request->user();
        $seesLeads = $user->allows('leads', 'view');
        $seesDeals = $user->allows('deals', 'view');

        // Scoped base queries honouring ownership + the date window.
        $leadBase = fn () => $this->applyRange($request, Lead::query()->when(! $user->seesAll('leads'), fn ($q) => $q->where('assigned_to', $user->id)), 'created_at');
        $dealBase = fn () => $this->applyRange($request, Deal::query()->when(! $user->seesAll('deals'), fn ($q) => $q->where('assigned_to', $user->id)), 'created_at');

        $leadReport = null;
        if ($seesLeads) {
            $total = (clone $leadBase())->count();
            $leadReport = [
                'total' => $total,
                'new' => (clone $leadBase())->where('lead_status', 'new')->count(),
                'qualified' => (clone $leadBase())->where('lead_status', 'qualified')->count(),
                'unqualified' => (clone $leadBase())->where('lead_status', 'unqualified')->count(),
                'converted' => (clone $leadBase())->whereNotNull('converted_client_id')->count(),
                'by_status' => $this->breakdown((clone $leadBase()), 'lead_status', Lead::STATUSES),
                'by_source' => $this->breakdown((clone $leadBase()), 'lead_source'),
                'by_priority' => $this->breakdown((clone $leadBase()), 'priority', Lead::PRIORITIES),
                'by_owner' => $this->breakdownOwner((clone $leadBase()), 'assigned_to'),
            ];
        }

        $dealReport = null;
        if ($seesDeals) {
            $all = (clone $dealBase())->get();
            $won = $all->where('stage', 'won');
            $lost = $all->where('stage', 'lost');
            $open = $all->whereIn('stage', Deal::OPEN_STAGES);
            $closed = $won->count() + $lost->count();
            $dealReport = [
                'total' => $all->count(),
                'open' => $open->count(),
                'won' => $won->count(),
                'lost' => $lost->count(),
                'pipeline' => $open->sum('value'),
                'forecast' => $open->sum(fn ($d) => $d->weighted_value),
                'won_value' => $won->sum('value'),
                'win_rate' => $closed ? round($won->count() / $closed * 100) : 0,
                'avg_size' => $won->count() ? round($won->avg('value')) : 0,
                'by_stage' => $this->breakdown((clone $dealBase()), 'stage', Deal::STAGES),
                'by_type' => $this->breakdown((clone $dealBase()), 'project_type'),
                'by_owner' => $this->breakdownOwner((clone $dealBase()), 'assigned_to'),
            ];
        }

        return ['leadReport' => $leadReport, 'dealReport' => $dealReport, 'currency' => \App\Models\Currency::symbolMap()];
    }

    /** Count rows grouped by a column → [['label'=>, 'value'=>, 'pct'=>], ...] sorted desc. */
    private function breakdown($query, string $column, array $labels = []): array
    {
        $rows = $query->selectRaw("COALESCE(NULLIF({$column}, ''), 'Unspecified') as k, COUNT(*) as c")
            ->groupBy('k')->orderByDesc('c')->pluck('c', 'k');
        $total = $rows->sum() ?: 1;

        return $rows->map(fn ($c, $k) => [
            'label' => $labels[$k] ?? ($k === 'Unspecified' ? 'Unspecified' : ucfirst($k)),
            'value' => (int) $c,
            'pct' => (int) round($c / $total * 100),
        ])->values()->all();
    }

    private function breakdownOwner($query, string $column): array
    {
        $rows = $query->selectRaw("{$column} as uid, COUNT(*) as c")->groupBy('uid')->orderByDesc('c')->get();
        $names = \App\Models\User::whereIn('id', $rows->pluck('uid')->filter())->pluck('name', 'id');
        $total = $rows->sum('c') ?: 1;

        return $rows->map(fn ($r) => [
            'label' => $r->uid ? ($names[$r->uid] ?? 'User #'.$r->uid) : 'Unassigned',
            'value' => (int) $r->c,
            'pct' => (int) round($r->c / $total * 100),
        ])->all();
    }

    /* -------------------------------------------------------------- follow-ups */

    private function followUps(Request $request)
    {
        $user = $request->user();
        $items = collect();

        if ($user->allows('leads', 'view')) {
            Lead::query()->with('assignee:id,name')
                ->whereNull('converted_client_id')->whereNotNull('next_follow_up_at')
                ->when(! $user->seesAll('leads'), fn ($q) => $q->where('assigned_to', $user->id))
                ->get()->each(fn ($lead) => $items->push((object) [
                    'kind' => 'lead', 'title' => $lead->full_name,
                    'subtitle' => $lead->company_name ?: ($lead->email ?: $lead->phone),
                    'note' => null, 'due' => $lead->next_follow_up_at, 'has_time' => false,
                    'owner' => $lead->assignee?->name, 'url' => route('admin.leads.show', $lead), 'ref' => $lead->lead_code,
                ]));
        }

        if ($user->allows('deals', 'view')) {
            DealFollowUp::query()->with(['deal:id,title,assigned_to', 'deal.assignee:id,name'])
                ->whereNull('completed_at')
                ->whereHas('deal', fn ($q) => $q->when(! $user->seesAll('deals'), fn ($x) => $x->where('assigned_to', $user->id)))
                ->get()->each(fn ($fu) => $items->push((object) [
                    'kind' => 'deal', 'title' => $fu->title ?: 'Follow-up',
                    'subtitle' => $fu->deal?->title, 'note' => $fu->note, 'due' => $fu->due_at, 'has_time' => true,
                    'owner' => $fu->deal?->assignee?->name, 'url' => $fu->deal ? route('admin.deals.show', $fu->deal_id) : '#', 'ref' => null,
                ]));
        }

        $today = now()->startOfDay();
        $endToday = now()->endOfDay();
        $endWeek = now()->endOfWeek();
        $endMonth = now()->endOfMonth();

        $buckets = ['overdue' => collect(), 'today' => collect(), 'week' => collect(), 'month' => collect(), 'later' => collect()];
        foreach ($items->sortBy('due') as $item) {
            $due = $item->due->copy();
            $key = match (true) {
                $due->lt($today) => 'overdue',
                $due->lte($endToday) => 'today',
                $due->lte($endWeek) => 'week',
                $due->lte($endMonth) => 'month',
                default => 'later',
            };
            $buckets[$key]->push($item);
        }

        return $buckets;
    }

    /* ----------------------------------------------------------------- country */

    private function country(Request $request): array
    {
        $user = $request->user();
        $seesAll = $user->seesAll('leads');
        $ownerId = $user->id;

        $scoped = fn () => $this->applyRange($request, Lead::query()->when(! $seesAll, fn ($x) => $x->where('assigned_to', $ownerId)), 'leads.created_at');

        $rows = $scoped()
            ->selectRaw("COALESCE(NULLIF(country, ''), 'Unknown') as country_name")
            ->selectRaw("SUM(CASE WHEN lead_status = 'new' THEN 1 ELSE 0 END) as new_count")
            ->selectRaw("SUM(CASE WHEN lead_status = 'qualified' THEN 1 ELSE 0 END) as qualified")
            ->selectRaw("SUM(CASE WHEN lead_status = 'unqualified' THEN 1 ELSE 0 END) as unqualified")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('country_name')->orderByDesc('total')->get()->keyBy('country_name');

        $won = $this->applyRange($request,
            Deal::query()->join('leads', 'deals.lead_id', '=', 'leads.id')
                ->when(! $seesAll, fn ($x) => $x->where('leads.assigned_to', $ownerId))
                ->where('deals.stage', 'won'),
            'leads.created_at'
        )->reorder()
            ->selectRaw("COALESCE(NULLIF(leads.country, ''), 'Unknown') as country_name")
            ->selectRaw('COUNT(DISTINCT deals.lead_id) as won')
            ->groupBy('country_name')->pluck('won', 'country_name');

        $breakdown = $rows->map(fn ($r) => (object) [
            'country' => $r->country_name,
            'new' => (int) $r->new_count, 'qualified' => (int) $r->qualified,
            'unqualified' => (int) $r->unqualified, 'won' => (int) ($won[$r->country_name] ?? 0), 'total' => (int) $r->total,
        ])->values();

        return [
            'breakdown' => $breakdown,
            'totals' => [
                'countries' => $breakdown->count(), 'new' => $breakdown->sum('new'),
                'qualified' => $breakdown->sum('qualified'), 'unqualified' => $breakdown->sum('unqualified'),
                'won' => $breakdown->sum('won'), 'total' => $breakdown->sum('total'),
            ],
        ];
    }
}
