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

    /** Follow-up page: leads with a scheduled follow-up, grouped Overdue / Today / Upcoming. */
    public function followUp(Request $request)
    {
        $q = Lead::query()->with('assignee:id,name')
            ->whereNull('converted_client_id')
            ->whereNotNull('next_follow_up_at');

        if ($request->user()->isStaff()) {
            $q->where('assigned_to', $request->user()->id);
        }

        $leads = $q->orderBy('next_follow_up_at')->get();
        $today = now()->startOfDay();

        return view('admin.leads.followup', [
            'overdue' => $leads->filter(fn ($l) => $l->next_follow_up_at->lt($today)),
            'today' => $leads->filter(fn ($l) => $l->next_follow_up_at->isSameDay($today)),
            'upcoming' => $leads->filter(fn ($l) => $l->next_follow_up_at->gt($today)),
        ]);
    }

    /** Mark a lead contacted now and optionally schedule the next follow-up. */
    public function markContacted(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validate([
            'next_follow_up_at' => ['nullable', 'date'],
            'lead_status' => ['nullable', Rule::in(array_keys(Lead::STATUSES))],
        ]);

        $lead->update([
            'last_contacted_at' => now(),
            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
            'lead_status' => $data['lead_status'] ?? $lead->lead_status,
        ]);

        return back()->with('status', 'Lead marked contacted.');
    }

    public function importForm()
    {
        return view('admin.leads.import', [
            'users' => User::assignable()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Downloadable CSV template with the expected headers. */
    public function importSample()
    {
        $headers = ['full_name', 'email', 'phone', 'company_name', 'job_title', 'lead_source', 'industry', 'lead_status', 'priority'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, ['John Doe', 'john@example.com', '+880 17XXXXXXXX', 'Acme Ltd', 'Manager', 'Website', 'Technology', 'new', 'high']);
            fclose($out);
        }, 'leads-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** Parse the uploaded CSV → create leads (validate + skip bad rows, dedupe by email). */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $rows = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?: []);

        $created = 0;
        $skipped = [];
        foreach ($rows as $n => $row) {
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }
            $data = array_combine($header, array_pad($row, count($header), null)) ?: [];
            $name = trim((string) ($data['full_name'] ?? ''));
            $phone = trim((string) ($data['phone'] ?? ''));
            $email = trim((string) ($data['email'] ?? ''));

            if ($name === '' || $phone === '') {
                $skipped[] = 'Row '.($n + 2).': missing name or phone';

                continue;
            }
            if ($email !== '' && Lead::where('email', $email)->exists()) {
                $skipped[] = 'Row '.($n + 2)." ({$email}): duplicate";

                continue;
            }

            Lead::create([
                'full_name' => $name,
                'email' => $email ?: null,
                'phone' => $phone,
                'company_name' => $data['company_name'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'lead_source' => in_array($data['lead_source'] ?? null, Lead::SOURCES, true) ? $data['lead_source'] : 'Other',
                'industry' => in_array($data['industry'] ?? null, Lead::INDUSTRIES, true) ? $data['industry'] : null,
                'lead_status' => array_key_exists($data['lead_status'] ?? '', Lead::STATUSES) ? $data['lead_status'] : 'new',
                'priority' => array_key_exists($data['priority'] ?? '', Lead::PRIORITIES) ? $data['priority'] : 'medium',
                'assigned_to' => $request->input('assigned_to'),
            ]);
            $created++;
        }

        return redirect()->route('admin.leads.index')
            ->with('status', "Imported {$created} lead(s)".(count($skipped) ? ', skipped '.count($skipped).'.' : '.'))
            ->with('import_skipped', array_slice($skipped, 0, 20));
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

    public function show(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $lead->load('assignee:id,name', 'convertedClient:id,name,email');

        return view('admin.leads.show', compact('lead'));
    }

    /** Convert a lead into a Client (customer user), reusing an existing client with the same email. */
    public function convert(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        if ($lead->isConverted()) {
            return back()->with('status', 'This lead is already converted.');
        }

        $client = null;
        if ($lead->email) {
            $client = User::where('email', $lead->email)->first();
        }

        if ($client && $client->role !== User::ROLE_CUSTOMER) {
            return back()->withErrors(['convert' => "A {$client->role} account already uses {$lead->email} — cannot convert to a client."]);
        }

        if (! $client) {
            $client = User::create([
                'name' => $lead->full_name,
                'email' => $lead->email ?: 'lead'.$lead->id.'@no-email.local',
                'phone' => $lead->phone,
                'company' => $lead->company_name,
                'address' => $lead->address,
                'city' => $lead->city,
                'state' => $lead->state,
                'country' => $lead->country,
                'zip' => $lead->zip,
                'role' => User::ROLE_CUSTOMER,
                'password' => \Illuminate\Support\Str::random(24), // hashed by cast; client resets via forgot-password
            ]);
        }

        $lead->update([
            'lead_status' => 'won',
            'converted_client_id' => $client->id,
            'converted_at' => now(),
        ]);

        return redirect()
            ->route('admin.clients.edit', $client)
            ->with('status', "Lead converted to client {$client->client_code}.");
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
            'next_follow_up_at' => ['nullable', 'date'],
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
