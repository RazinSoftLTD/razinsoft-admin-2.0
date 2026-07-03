<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    /** All Leads — stat cards + filterable, paginated list (and CSV export of the same filters). */
    public function index(Request $request)
    {
        $q = Lead::query()->with('assignee:id,name')->latest('id');

        // Staff only see the leads assigned to them; admins see everything.
        if ($request->user()->isStaff()) {
            $q->where('assigned_to', $request->user()->id);
        }

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%"));
        }
        if ($status = $request->query('status')) {
            $q->where('lead_status', $status);
        }
        if ($source = $request->query('source')) {
            $q->where('lead_source', $source);
        }
        if ($assigned = $request->query('assigned')) {
            $q->where('assigned_to', $assigned);
        }
        if ($priority = $request->query('priority')) {
            $q->where('priority', $priority);
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($q->get());
        }

        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100]) ? (int) $request->query('per_page') : 10;

        // Stat cards: this month vs last month deltas.
        $monthStart = now()->startOfMonth();
        $lastMonth = [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()];
        $delta = function (int $current, int $previous): ?float {
            if ($previous === 0) return null;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        $total = Lead::count();
        $totalPrev = Lead::where('created_at', '<', $monthStart)->count();
        $new = Lead::where('lead_status', 'new')->count();
        $qualified = Lead::whereIn('lead_status', ['qualified', 'proposal', 'negotiation'])->count();
        $unqualified = Lead::where('lead_status', 'lost')->count();
        $won = Lead::where('lead_status', 'won')->count();

        $stats = [
            ['label' => 'Total Leads', 'value' => number_format($total), 'delta' => $delta($total, $totalPrev), 'tone' => 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
            ['label' => 'New Leads', 'value' => number_format($new), 'delta' => null, 'tone' => 'bg-emerald-50 text-emerald-600', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6'],
            ['label' => 'Qualified Leads', 'value' => number_format($qualified), 'delta' => null, 'tone' => 'bg-amber-50 text-amber-600', 'icon' => 'M9 12h6m-6 4h6M8 3h8l4 4v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z'],
            ['label' => 'Unqualified Leads', 'value' => number_format($unqualified), 'delta' => null, 'tone' => 'bg-red-50 text-red-500', 'icon' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM9 10h.01M15 10h.01M9.5 15.5c.7-.7 1.5-1 2.5-1s1.8.3 2.5 1'],
            ['label' => 'Conversion Rate', 'value' => ($total ? round($won / $total * 100, 1) : 0) . '%', 'delta' => null, 'tone' => 'bg-sky-50 text-sky-600', 'icon' => 'M9 11l3 3 8-8M21 12v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11'],
        ];

        return view('admin.leads.index', [
            'leads' => $q->paginate($perPage)->withQueryString(),
            'users' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'stats' => $stats,
            'perPage' => $perPage,
        ]);
    }

    public function create()
    {
        return view('admin.leads.form', [
            'lead' => new Lead(['lead_status' => 'new', 'priority' => 'high']),
            'users' => User::assignable()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        Lead::create($this->validated($request));

        return redirect()->route('admin.leads.index')->with('status', 'Lead saved.');
    }

    public function edit(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        return view('admin.leads.form', [
            'lead' => $lead,
            'users' => User::assignable()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $lead->update($this->validated($request));

        return redirect()->route('admin.leads.index')->with('status', 'Lead updated.');
    }

    public function destroy(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $lead->delete();

        return back()->with('status', 'Lead deleted.');
    }

    /** Staff may only touch leads assigned to them; admins have full access. */
    private function authorizeLead(Request $request, Lead $lead): void
    {
        abort_if($request->user()->isStaff() && $lead->assigned_to !== $request->user()->id, 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'lead_source' => ['required', Rule::in(Lead::SOURCES)],
            'industry' => ['nullable', Rule::in(Lead::INDUSTRIES)],
            'lead_status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'assigned_to' => ['required', 'exists:users,id'],
            'team' => ['nullable', Rule::in(Lead::TEAMS)],
            'priority' => ['required', Rule::in(array_keys(Lead::PRIORITIES))],
        ]);
    }

    private function exportCsv($leads): StreamedResponse
    {
        return response()->streamDownload(function () use ($leads) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Lead ID', 'Full Name', 'Company', 'Job Title', 'Email', 'Phone', 'Source', 'Status', 'Priority', 'Assigned To', 'Team', 'Country', 'Created At']);
            foreach ($leads as $l) {
                fputcsv($out, [
                    'LEAD-' . $l->id, $l->full_name, $l->company_name, $l->job_title, $l->email, $l->phone,
                    $l->lead_source, Lead::STATUSES[$l->lead_status] ?? $l->lead_status,
                    Lead::PRIORITIES[$l->priority] ?? $l->priority, $l->assignee?->name, $l->team, $l->country,
                    $l->created_at->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, 'leads-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
