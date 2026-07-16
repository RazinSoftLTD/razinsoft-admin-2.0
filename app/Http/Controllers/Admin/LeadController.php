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

        // Smart search: every whitespace term must match (AND), each across many
        // fields (OR) — so "john dhaka" finds John located in Dhaka.
        if ($search = trim((string) $request->query('search'))) {
            $cols = ['full_name', 'email', 'phone', 'company_name', 'job_title', 'lead_source', 'industry', 'city', 'country', 'lead_code'];
            foreach (preg_split('/\s+/', $search) as $term) {
                $q->where(function ($w) use ($cols, $term) {
                    foreach ($cols as $c) {
                        $w->orWhere($c, 'like', "%{$term}%");
                    }
                });
            }
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

        if ($format = $request->query('export')) {
            return $this->export($q->get(), $format);
        }

        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100]) ? (int) $request->query('per_page') : 10;
        $leads = $q->paginate($perPage)->withQueryString();

        // Live search / pagination fetches only the results fragment (keeps the search box focused).
        if ($request->ajax()) {
            return view('admin.leads._results', compact('leads', 'perPage', 'search'));
        }

        // Country list for the filter drawer (respects the staff view scope).
        $countries = Lead::query()->when(! $request->user()->seesAll('leads'), fn ($x) => $x->where('assigned_to', $request->user()->id))
            ->whereNotNull('country')->where('country', '!=', '')->distinct()->orderBy('country')->pluck('country');

        return view('admin.leads.index', [
            'leads' => $leads,
            'users' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'perPage' => $perPage,
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

    /** Downloadable import template — CSV by default, Excel via ?format=excel. */
    public function importSample(Request $request)
    {
        // Dial Code sits in its OWN column so imports/exports keep the country code
        // separate from the number (and country lists can be derived from the data).
        $headers = ['Full Name', 'Email', 'Dial Code', 'Phone', 'Company', 'Job Title', 'Source', 'Product', 'Lead Quality', 'Priority', 'Department', 'Country'];
        $examples = [
            ['John Doe', 'john@example.com', '+880', '1711234567', 'Acme Ltd', 'Manager', 'Website', 'CRM Suite', 'New', 'High', 'Sales', 'Bangladesh'],
            ['Jane Smith', 'jane@example.com', '+1', '2125550175', 'Globex Inc', 'CTO', 'Facebook', 'Ready POS', 'Qualified', 'Medium', 'Sales', 'United States'],
        ];

        if ($request->query('format') === 'excel') {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Leads Template');
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($examples, null, 'A2');
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, 'leads-import-template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $examples) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($examples as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'leads-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** Parse the uploaded CSV → create leads (validate + skip bad rows, dedupe by email). */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        // Read the rows from either a CSV or an Excel (.xlsx/.xls) upload.
        $file = $request->file('file');
        if (in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath())
                ->getActiveSheet()->toArray(null, true, true, false);
        } else {
            $rows = array_map('str_getcsv', file($file->getRealPath()));
        }
        // Smart column mapping — match each header to a field regardless of casing /
        // spacing / wording ("Full Name", "full_name", "Name" all map to the name field).
        $map = $this->mapImportColumns(array_shift($rows) ?: []);
        $fields = array_values($map);
        if (! array_intersect(['full_name', 'email', 'phone'], $fields)) {
            return back()->with('error', 'Could not recognise a Name, Email or Phone column. Check the header row of your file.');
        }

        $created = 0;
        $skipped = [];
        $seenEmails = [];   // within-file duplicate guards
        $seenPhones = [];
        foreach ($rows as $n => $rawRow) {
            $row = array_values($rawRow);
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            $data = [];
            foreach ($map as $i => $field) {
                // Trim AND collapse internal double-spacing ("John  Doe " → "John Doe").
                $data[$field] = preg_replace('/\s{2,}/u', ' ', trim((string) ($row[$i] ?? '')));
            }
            $name = $data['full_name'] ?? '';
            $email = strtolower($data['email'] ?? '');
            $rawPhone = $data['phone'] ?? '';

            // At least an email OR a phone identifies the lead (same rule as the Add Lead form).
            if ($email === '' && $rawPhone === '') {
                $skipped[] = 'Row '.($n + 2).': no email or phone';

                continue;
            }
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';   // ignore a malformed email rather than dropping the whole row
            }

            // Phone must carry a resolvable country code (from a +CC prefix, the row's
            // Country, or a Dial Code column) and be a VALID number for that country.
            $dial = null;
            $phone = '';
            if ($rawPhone !== '') {
                $parsed = \App\Support\Phone::normalize($rawPhone, $data['country'] ?? null, $data['dial_code'] ?? null);
                if (! $parsed) {
                    $skipped[] = 'Row '.($n + 2)." ({$rawPhone}): invalid phone".(($data['country'] ?? '') !== '' ? ' for '.$data['country'] : ' — no country code');

                    continue;
                }
                [$dial, $phone] = [$parsed['dial'], $parsed['number']];
            }

            // Duplicates: against the database AND within this same file.
            if ($email !== '' && (isset($seenEmails[$email]) || Lead::whereRaw('LOWER(email) = ?', [$email])->exists())) {
                $skipped[] = 'Row '.($n + 2)." ({$email}): duplicate email";

                continue;
            }
            $phoneKey = $dial.$phone;
            if ($phone !== '' && (isset($seenPhones[$phoneKey]) || Lead::where('phone', $phone)->where(fn ($q) => $q->where('dial_code', $dial)->orWhereNull('dial_code'))->exists())) {
                $skipped[] = 'Row '.($n + 2)." ({$dial} {$phone}): duplicate phone";

                continue;
            }
            if ($email !== '') {
                $seenEmails[$email] = true;
            }
            if ($phone !== '') {
                $seenPhones[$phoneKey] = true;
            }

            // Derive a name when the file didn't include one.
            if ($name === '') {
                $name = $email !== '' ? \Illuminate\Support\Str::before($email, '@') : $phone;
            }

            Lead::create([
                'full_name' => $name,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'dial_code' => $dial,
                'company_name' => ($data['company_name'] ?? '') ?: null,
                'job_title' => ($data['job_title'] ?? '') ?: null,
                'website' => ($data['website'] ?? '') ?: null,
                'address' => ($data['address'] ?? '') ?: null,
                'city' => ($data['city'] ?? '') ?: null,
                'state' => ($data['state'] ?? '') ?: null,
                'country' => ($data['country'] ?? '') ?: null,
                'zip' => ($data['zip'] ?? '') ?: null,
                'notes' => ($data['notes'] ?? '') ?: null,
                'lead_source' => in_array($data['lead_source'] ?? null, Lead::sourceOptions(), true) ? $data['lead_source'] : 'Others',
                'industry' => ($data['industry'] ?? '') ?: null,   // free-text product name
                'lead_status' => $this->resolveStatus($data['lead_status'] ?? ''),
                'priority' => $this->resolvePriority($data['priority'] ?? ''),
                'team' => in_array($data['team'] ?? null, Lead::departmentOptions(), true) ? $data['team'] : null,
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
                'deal_stage' => ['required', Rule::in(array_keys(Deal::stages()))],
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

        // Normalise spacing everywhere: trim + collapse internal double spaces.
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = preg_replace('/\s{2,}/u', ' ', trim($v));
            }
        }

        // full_name & phone are NOT NULL — normalise and derive a name when left blank.
        // Phone keeps digits only (spaces/dashes/() stripped) so copies & lookups are clean.
        $data['phone'] = preg_replace('/[\s\-().]+/', '', (string) ($data['phone'] ?? ''));
        $name = trim((string) ($data['full_name'] ?? ''));
        if ($name === '') {
            $name = ! empty($data['email'])
                ? \Illuminate\Support\Str::of($data['email'])->before('@')->toString()
                : ($data['phone'] !== '' ? trim(($data['dial_code'] ?? '').' '.$data['phone']) : 'Lead');
        }
        $data['full_name'] = $name;

        return $data;
    }

    /** Accepted header spellings (normalised) → lead field, for the smart importer. */
    private const IMPORT_ALIASES = [
        'full_name' => ['fullname', 'name', 'leadname', 'contactname', 'customername', 'clientname', 'firstname'],
        'email' => ['email', 'emailaddress', 'mail', 'emailid', 'emails', 'workemail'],
        'phone' => ['phone', 'phonenumber', 'phoneno', 'mobile', 'mobilenumber', 'mobileno', 'contact', 'contactnumber', 'contactno', 'cell', 'cellphone', 'whatsapp', 'whatsappnumber', 'number', 'msisdn'],
        'dial_code' => ['dialcode', 'countrycode', 'phonecode'],
        'company_name' => ['company', 'companyname', 'organization', 'organisation', 'business', 'businessname', 'firm'],
        'job_title' => ['jobtitle', 'designation', 'title', 'position', 'role'],
        'website' => ['website', 'url', 'site', 'web'],
        'address' => ['address', 'streetaddress', 'street'],
        'city' => ['city', 'town'],
        'state' => ['state', 'region', 'province'],
        'country' => ['country', 'nation'],
        'zip' => ['zip', 'zipcode', 'postalcode', 'postcode', 'pincode'],
        'notes' => ['notes', 'note', 'description', 'remarks', 'comment', 'comments', 'message'],
        'lead_source' => ['source', 'leadsource', 'channel'],
        'industry' => ['product', 'products', 'industry', 'service', 'interestedin', 'interest'],
        'lead_status' => ['leadquality', 'quality', 'status', 'leadstatus', 'stage'],
        'priority' => ['priority', 'urgency'],
        'team' => ['department', 'leaddepartment', 'team', 'dept'],
    ];

    /** Map each uploaded column index to a lead field via normalised alias matching. */
    private function mapImportColumns(array $rawHeader): array
    {
        $lookup = [];
        foreach (self::IMPORT_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $lookup[$alias] = $field;
            }
        }

        $map = [];
        foreach ($rawHeader as $i => $h) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $h)));
            if ($key !== '' && isset($lookup[$key]) && ! in_array($lookup[$key], $map, true)) {
                $map[$i] = $lookup[$key];
            }
        }

        return $map;
    }

    /** Resolve a status key or label ("qualified" / "Qualified") to a valid key. */
    private function resolveStatus(string $value): string
    {
        $v = strtolower(trim($value));
        if (array_key_exists($v, Lead::STATUSES)) {
            return $v;
        }
        foreach (Lead::STATUSES as $key => $label) {
            if (strtolower($label) === $v) {
                return $key;
            }
        }

        return 'new';
    }

    /** Resolve a priority key or label ("high" / "High") to a valid key. */
    private function resolvePriority(string $value): string
    {
        $v = strtolower(trim($value));
        if (array_key_exists($v, Lead::PRIORITIES)) {
            return $v;
        }
        foreach (Lead::PRIORITIES as $key => $label) {
            if (strtolower($label) === $v) {
                return $key;
            }
        }

        return 'medium';
    }

    /** Column headers + row values shared by every export format. */
    private function exportData($leads): array
    {
        // Dial Code exports as its OWN column (separate from the number) so country
        // breakdowns can be derived straight from the sheet.
        $headers = ['Lead ID', 'Full Name', 'Company', 'Job Title', 'Email', 'Dial Code', 'Phone', 'Source', 'Lead Quality', 'Priority', 'Assigned To', 'Department', 'Country', 'Created At'];
        $rows = [];
        foreach ($leads as $l) {
            $rows[] = [
                $l->lead_code, $l->full_name, $l->company_name, $l->job_title, $l->email, $l->dial_code, $l->phone,
                $l->lead_source, Lead::STATUSES[$l->lead_status] ?? $l->lead_status,
                Lead::PRIORITIES[$l->priority] ?? $l->priority, $l->assignee?->name, $l->team, $l->country,
                $l->created_at->format('Y-m-d H:i'),
            ];
        }

        return [$headers, $rows];
    }

    /** Dispatch to the requested export format (csv · excel · pdf). */
    private function export($leads, string $format)
    {
        return match ($format) {
            'excel' => $this->exportExcel($leads),
            'pdf' => $this->exportPdf($leads),
            default => $this->exportCsv($leads),
        };
    }

    private function exportCsv($leads): StreamedResponse
    {
        [$headers, $rows] = $this->exportData($leads);

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, 'leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function exportExcel($leads): StreamedResponse
    {
        [$headers, $rows] = $this->exportData($leads);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Leads');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'leads-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function exportPdf($leads)
    {
        [$headers, $rows] = $this->exportData($leads);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.leads.export-pdf', [
            'headers' => $headers,
            'rows' => $rows,
            'generatedAt' => now()->format('d M Y, g:i A'),
        ])->setPaper('a4', 'landscape')->download('leads-'.now()->format('Y-m-d').'.pdf');
    }
}
