<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingSetting;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Meeting;
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

        // Constrain to the rows this user's "view" scope allows (owned / added / both / all).
        $request->user()->applyScope($q, 'leads', 'view');

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

        // Created-date filter: preset range and/or a custom from–to range.
        match ($request->query('date_range')) {
            'today' => $q->whereDate('created_at', today()),
            'week' => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $q->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'year' => $q->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
            default => null,
        };
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }
        if ($country = $request->query('country')) {
            $q->where('country', $country);
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
        $qualified = Lead::where('lead_status', 'qualified')->count();
        $unqualified = Lead::where('lead_status', 'unqualified')->count();
        $converted = Lead::whereNotNull('converted_client_id')->count();

        $stats = [
            ['label' => 'Total Leads', 'value' => number_format($total), 'delta' => $delta($total, $totalPrev), 'tone' => 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
            ['label' => 'New Leads', 'value' => number_format($new), 'delta' => null, 'tone' => 'bg-emerald-50 text-emerald-600', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6'],
            ['label' => 'Qualified Leads', 'value' => number_format($qualified), 'delta' => null, 'tone' => 'bg-emerald-50 text-emerald-600', 'icon' => 'm5 13 4 4L19 7'],
            ['label' => 'Unqualified Leads', 'value' => number_format($unqualified), 'delta' => null, 'tone' => 'bg-red-50 text-red-500', 'icon' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM9 10h.01M15 10h.01M9.5 15.5c.7-.7 1.5-1 2.5-1s1.8.3 2.5 1'],
            ['label' => 'Conversion Rate', 'value' => ($total ? round($converted / $total * 100, 1) : 0).'%', 'delta' => null, 'tone' => 'bg-sky-50 text-sky-600', 'icon' => 'M9 11l3 3 8-8M21 12v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11'],
        ];

        // Country-wise qualified vs unqualified breakdown (respects staff scope + the date range).
        $scoped = Lead::query()->when(! $request->user()->seesAll('leads'), fn ($x) => $x->where('assigned_to', $request->user()->id));
        match ($request->query('date_range')) {
            'today' => $scoped->whereDate('created_at', today()),
            'week' => $scoped->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $scoped->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'year' => $scoped->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
            default => null,
        };
        if ($from = $request->query('from')) $scoped->whereDate('created_at', '>=', $from);
        if ($to = $request->query('to')) $scoped->whereDate('created_at', '<=', $to);

        $countryBreakdown = (clone $scoped)
            ->selectRaw("COALESCE(NULLIF(country, ''), 'Unknown') as country_name")
            ->selectRaw("SUM(CASE WHEN lead_status = 'qualified' THEN 1 ELSE 0 END) as qualified")
            ->selectRaw("SUM(CASE WHEN lead_status = 'unqualified' THEN 1 ELSE 0 END) as unqualified")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('country_name')->orderByDesc('total')->get();

        $countries = Lead::query()->when(! $request->user()->seesAll('leads'), fn ($x) => $x->where('assigned_to', $request->user()->id))
            ->whereNotNull('country')->where('country', '!=', '')->distinct()->orderBy('country')->pluck('country');

        return view('admin.leads.index', [
            'leads' => $q->paginate($perPage)->withQueryString(),
            'users' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'stats' => $stats,
            'perPage' => $perPage,
            'countryBreakdown' => $countryBreakdown,
            'countries' => $countries,
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

    /** Set/clear the next follow-up date from the lead detail page (blank = remove from follow-ups). */
    public function scheduleFollowUp(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validate(['next_follow_up_at' => ['nullable', 'date']]);
        $lead->update(['next_follow_up_at' => $data['next_follow_up_at'] ?? null]);

        return back()->with('status', $data['next_follow_up_at'] ? 'Follow-up scheduled.' : 'Follow-up cleared.');
    }

    /** Quick status change from the list or the detail page (no other fields touched). */
    public function status(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validate(['lead_status' => ['required', Rule::in(array_keys(Lead::STATUSES))]]);
        $lead->update($data);

        return back()->with('status', "Status updated to {$data['lead_status']}.");
    }

    /** Push the follow-up date out by N days without recording a contact (couldn't reach yet). */
    public function snooze(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $days = (int) $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365']])['days'];
        $lead->update(['next_follow_up_at' => now()->addDays($days)->toDateString()]);

        return back()->with('status', "Follow-up snoozed {$days} day(s).");
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
                'lead_source' => in_array($data['lead_source'] ?? null, Lead::sourceOptions(), true) ? $data['lead_source'] : 'Others',
                'industry' => trim((string) ($data['industry'] ?? '')) ?: null,   // free-text product name
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
            'users' => $this->leadOwners(),
        ]);
    }

    /** Users who can own a lead — only staff/admins granted the leads.view permission. */
    private function leadOwners()
    {
        return User::assignable()->with('assignedRole')->orderBy('name')->get()
            ->filter(fn (User $u) => $u->hasPermission('leads.view'))
            ->values();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Validate the deal fields up front (so a bad deal never leaves an orphan lead).
        $dealData = null;
        if ($request->boolean('create_deal')) {
            $dealData = $request->validate([
                'deal_name' => ['required', 'string', 'max:255'],
                'deal_stage' => ['required', Rule::in(array_keys(Deal::STAGES))],
                'deal_value' => ['required', 'numeric', 'min:0'],
                'deal_currency' => ['nullable', 'string', 'max:8'],
            ]);
        }

        $data['added_by'] = $request->user()->id;
        $lead = Lead::create($data);

        if ($dealData) {
            Deal::create([
                'title' => $dealData['deal_name'],
                'lead_id' => $lead->id,
                'stage' => $dealData['deal_stage'],
                'value' => $dealData['deal_value'],
                'currency' => $dealData['deal_currency'] ?? 'BDT',
                'assigned_to' => $lead->assigned_to,
            ]);
        }

        // "Save & Create Meeting" → book a meeting for this lead and open it (needs meetings access).
        if ($request->input('after') === 'meeting' && $request->user()->allows('meetings', 'view')) {
            if ($meeting = $this->meetingForLead($lead)) {
                return redirect()->route('admin.meetings.show', $meeting)
                    ->with('status', 'Lead saved. Set the meeting date/time and confirm below.');
            }
        }

        return redirect()->route('admin.leads.index')
            ->with('status', $dealData ? 'Lead & deal created.' : 'Lead saved.');
    }

    /** Book a meeting for a lead at the next available slot (they adjust the exact time after). */
    private function meetingForLead(Lead $lead): ?Meeting
    {
        $s = BookingSetting::current();

        // Find the next free slot within the bookable window.
        for ($i = 0; $i <= max(1, (int) $s->advance_days); $i++) {
            $date = today()->addDays($i);
            foreach ($s->slotsFor($date) as $slot) {
                if (! $slot['available']) {
                    continue;
                }
                // The unique (date, start_time) index also blocks cancelled meetings — skip on collision.
                if (Meeting::whereDate('date', $date->toDateString())->where('start_time', $slot['start'].':00')->exists()) {
                    continue;
                }
                try {
                    return Meeting::create([
                        'name' => $lead->full_name,
                        'email' => $lead->email ?: '',
                        'phone' => $lead->phone,
                        'dial_code' => $lead->dial_code,
                        'company' => $lead->company_name,
                        'date' => $date->toDateString(),
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'status' => 'pending',
                        'assigned_to' => $lead->assigned_to,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    continue; // slot taken between check and insert — try the next one
                }
            }
        }

        return null;
    }

    public function show(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $lead->load('assignee:id,name', 'convertedClient:id,name,email', 'deals');

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
            'lead_status' => 'qualified',
            'converted_client_id' => $client->id,
            'converted_at' => now(),
        ]);

        return redirect()
            ->route('admin.clients.edit', $client)
            ->with('status', "Lead converted to client {$client->client_code}.");
    }

    /** Open a deal from this lead and jump to the deal editor to fill in the details. */
    public function convertDeal(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        abort_unless($request->user()->allows('deals', 'create'), 403);

        $deal = Deal::create([
            'title' => ($lead->company_name ?: $lead->full_name).' — Deal',
            'lead_id' => $lead->id,
            'stage' => 'new',
            'value' => 0,
            'currency' => 'BDT',
            'assigned_to' => $lead->assigned_to,
        ]);

        return redirect()->route('admin.deals.edit', $deal)->with('status', 'Deal created from lead — add the details.');
    }

    public function edit(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        return view('admin.leads.form', [
            'lead' => $lead,
            'users' => $this->leadOwners(),
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

    /** Row-level access: the user's lead "view" scope must cover this record. */
    private function authorizeLead(Request $request, Lead $lead): void
    {
        abort_unless($request->user()->canAct('leads', 'view', $lead), 403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'salutation' => ['nullable', Rule::in(Lead::SALUTATIONS)],
            'full_name' => ['nullable', 'string', 'max:255'],
            // Email or phone — at least one identifies the lead.
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'office_phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'lead_source' => ['required', Rule::in(Lead::sourceOptions())],
            // "Product" — a RazinSoft product name (from the Products module). Stored on the industry column.
            'industry' => ['nullable', 'string', 'max:255'],
            'lead_status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'assigned_to' => ['required', 'exists:users,id'],
            'team' => ['nullable', Rule::in(Lead::departmentOptions())],
            'priority' => ['nullable', Rule::in(array_keys(Lead::PRIORITIES))],
            'next_follow_up_at' => ['nullable', 'date'],
        ], [
            'email.required_without' => 'Provide at least an email or a phone number.',
            'phone.required_without' => 'Provide at least an email or a phone number.',
        ]);

        $data['is_whatsapp'] = $request->boolean('is_whatsapp');

        // full_name & phone are NOT NULL — normalise and derive a name when left blank.
        $data['phone'] = trim((string) ($data['phone'] ?? ''));
        $name = trim((string) ($data['full_name'] ?? ''));
        if ($name === '') {
            $name = ! empty($data['email'])
                ? \Illuminate\Support\Str::of($data['email'])->before('@')->toString()
                : ($data['phone'] !== '' ? trim(($data['dial_code'] ?? '').' '.$data['phone']) : 'Lead');
        }
        $data['full_name'] = $name;

        return $data;
    }

    private function exportCsv($leads): StreamedResponse
    {
        return response()->streamDownload(function () use ($leads) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Lead ID', 'Full Name', 'Company', 'Job Title', 'Email', 'Phone', 'Source', 'Status', 'Priority', 'Assigned To', 'Team', 'Country', 'Created At']);
            foreach ($leads as $l) {
                fputcsv($out, [
                    $l->lead_code, $l->full_name, $l->company_name, $l->job_title, $l->email, $l->phone,
                    $l->lead_source, Lead::STATUSES[$l->lead_status] ?? $l->lead_status,
                    Lead::PRIORITIES[$l->priority] ?? $l->priority, $l->assignee?->name, $l->team, $l->country,
                    $l->created_at->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, 'leads-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
