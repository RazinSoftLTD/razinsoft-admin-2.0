<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientDocument;
use App\Models\ClientInvoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Clients = customer-role users (from site registration, admin create, or lead conversion). */
class ClientController extends Controller
{
    public function index(Request $request)
    {
        $q = User::clients();

        // Scope: Owned = my clients (account_manager_id), Added = clients I created (created_by).
        $request->user()->applyScope($q, 'clients', 'view');

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%"));
        }

        // ---- Filters ----
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $q->where('client_category', $category);
        }
        if ($subCategory = $request->query('sub_category')) {
            $q->where('client_sub_category', $subCategory);
        }
        if ($country = $request->query('country')) {
            $q->where('country', $country);
        }
        if ($label = $request->query('label')) {
            $q->where('client_label', $label);
        }
        // Date added — preset range and/or a custom from–to range.
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

        // Total paid (across this client's CRM invoices) — powers the "Top paying" sort.
        $q->withSum('clientInvoices as total_paid', 'amount_paid');

        // Sorting — whitelist the sortable columns; "top_paying" orders by total paid.
        if ($request->query('sort') === 'top_paying') {
            $sort = 'top_paying';
            $dir = 'desc';
            $q->orderByDesc('total_paid');
        } else {
            $sortable = ['id', 'name', 'email', 'phone', 'status', 'created_at'];
            $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'created_at';
            $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
            $q->orderBy($sort, $dir);
        }

        // Export the current (filtered) result set — needs the Import / Export permission.
        if ($format = $request->query('export')) {
            abort_unless($request->user()->allows('clients', 'import_export'), 403);

            return $this->export((clone $q)->get(), $format);
        }

        // Per-page — "Show N entries" (up to 1000).
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250, 500, 1000], true) ? $perPage : 10;

        // List or grid layout.
        $view = $request->query('view') === 'grid' ? 'grid' : 'list';

        return view('admin.clients.index', [
            'clients' => $q->paginate($perPage)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'perPage' => $perPage,
            'view' => $view,
            // Most recent import that can still be undone (shown only with import/export access).
            'lastImport' => $request->user()->allows('clients', 'import_export') ? \App\Models\ClientImportBatch::undoable() : null,
            // Filter option lists.
            'statuses' => User::STATUSES,
            'filterCategories' => User::clients()->whereNotNull('client_category')->where('client_category', '!=', '')->distinct()->orderBy('client_category')->pluck('client_category')->all(),
            'filterSubCategories' => User::clients()->whereNotNull('client_sub_category')->where('client_sub_category', '!=', '')->distinct()->orderBy('client_sub_category')->pluck('client_sub_category')->all(),
            'filterCountries' => User::clients()->whereNotNull('country')->where('country', '!=', '')->distinct()->orderBy('country')->pluck('country')->all(),
            'clientLabels' => \App\Models\ClientLabel::ordered(),
            'filters' => $request->only(['status', 'category', 'sub_category', 'country', 'label', 'date_range', 'from', 'to']),
        ]);
    }

    /** Client profile: details + invoices, payments and documents for the tabbed profile page. */
    public function show(User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);
        abort_unless(auth()->user()->canAct('clients', 'view', $client), 403);

        $invoices = ClientInvoice::where('client_id', $client->id)
            ->withCount('items')->latest('id')->get();

        $payments = InvoicePayment::whereIn('client_invoice_id', $invoices->pluck('id'))
            ->with('invoice:id,invoice_number,currency')
            ->latest('paid_at')->latest('id')->get();

        $documents = $client->documents()->with('uploader:id,name')->get();

        return view('admin.clients.show', [
            'client' => $client,
            'invoices' => $invoices,
            'payments' => $payments,
            'documents' => $documents,
            'stats' => [
                'projects' => 0,
                'invoiced' => round((float) $invoices->sum('total'), 2),
                'earnings' => round((float) $invoices->sum('amount_paid'), 2),
                'due_count' => $invoices->filter(fn ($i) => $i->amountDue() > 0)->count(),
                'due_amount' => round($invoices->sum(fn ($i) => $i->amountDue()), 2),
            ],
        ]);
    }

    /** Quick-add a customer (used from the invoice form's "Add" button). Returns JSON. */
    public function quickStore(Request $request)
    {
        // Validate manually so we always return JSON errors (this web route otherwise redirects on failure).
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'company' => ['nullable', 'string', 'max:255'],
            'login_allowed' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $client = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
            'role' => User::ROLE_CUSTOMER,
            'status' => ($data['login_allowed'] ?? false) ? User::STATUS_ACTIVE : User::STATUS_BLOCKED,
            'password' => Str::random(16),
        ]);

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'company' => $client->company,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => '',
        ]);
    }

    /** Upload a document against a client. */
    public function storeDocument(Request $request, User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'], // ≤10MB
        ]);

        $file = $request->file('file');
        $client->documents()->create([
            'name' => trim((string) $request->input('name')) ?: $file->getClientOriginalName(),
            'path' => $file->store('client-documents/'.$client->id, 'public'),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Document uploaded.');
    }

    /** Delete a client document (file + record). */
    public function destroyDocument(User $client, ClientDocument $document)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);
        abort_unless($document->client_id === $client->id, 404);

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document deleted.');
    }

    public function create()
    {
        return view('admin.clients.form', array_merge(
            ['client' => new User(['role' => User::ROLE_CUSTOMER])],
            $this->formOptions(),
        ));
    }

    /** Shared select-option lists for the Add/Edit Client form. */
    private function formOptions(): array
    {
        return [
            'managers' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'salutations' => ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'],
            'genders' => ['Male', 'Female', 'Other'],
            'languages' => ['English', 'Bengali', 'Hindi', 'Arabic', 'Urdu'],
            'categories' => User::clients()->whereNotNull('client_category')->where('client_category', '!=', '')
                ->distinct()->orderBy('client_category')->pluck('client_category')->all(),
            'subCategories' => User::clients()->whereNotNull('client_sub_category')->where('client_sub_category', '!=', '')
                ->distinct()->orderBy('client_sub_category')->pluck('client_sub_category')->all(),
            'clientLabels' => \App\Models\ClientLabel::ordered(),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['role'] = User::ROLE_CUSTOMER;
        // Stamp the creator so the "Added" scope can find clients this staff member added.
        $data['created_by'] = $request->user()->id;
        // Password is optional — generate a random one when omitted (column is NOT NULL). Hashed by cast.
        $plain = $request->filled('password') ? $request->input('password') : Str::random(12);
        $data['password'] = $plain;
        $this->handlePhoto($request, $data);

        $client = User::create($data);
        $this->recordPassword($client, $plain, $request->user()->id);

        return redirect()->route('admin.clients.index')->with('status', 'Client created.');
    }

    public function edit(User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);
        abort_unless(auth()->user()->canAct('clients', 'edit', $client), 403);

        return view('admin.clients.form', array_merge(
            [
                'client' => $client,
                // Only a super admin may review the credentials set for a client.
                'passwordHistory' => auth()->user()->isSuperAdmin() ? $client->passwordHistories()->with('setter')->get() : collect(),
            ],
            $this->formOptions(),
        ));
    }

    public function update(Request $request, User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);
        abort_unless($request->user()->canAct('clients', 'edit', $client), 403);

        $data = $this->validated($request, $client);
        if ($request->filled('password')) {
            $data['password'] = $request->input('password'); // hashed by cast
        }
        $this->handlePhoto($request, $data, $client);

        $client->update($data);

        if ($request->filled('password')) {
            $this->recordPassword($client, $request->input('password'), $request->user()->id);
        }

        return redirect()->route('admin.clients.index')->with('status', 'Client updated.');
    }

    /** Log the plaintext password (encrypted at rest) so a super admin can review it later. */
    private function recordPassword(User $client, string $plain, ?int $setBy): void
    {
        $client->passwordHistories()->create([
            'password' => $plain,
            'set_by' => $setBy,
            'created_at' => now(),
        ]);
    }

    public function destroy(User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);
        abort_unless(auth()->user()->canAct('clients', 'delete', $client), 403);

        $client->delete();

        return back()->with('status', 'Client deleted.');
    }

    /** Set a client's account status (active / inactive / blocked). */
    public function updateStatus(Request $request, User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(User::STATUSES))]]);
        $client->update(['status' => $data['status']]);

        return back()->with('status', 'Client marked '.User::STATUSES[$data['status']].'.');
    }

    /** Download all clients as a CSV (honours the current search filter). */
    // ===== Import (CSV / Excel, smart column mapping) =====
    public function importForm()
    {
        return view('admin.clients.import', $this->formOptions());
    }

    /** Downloadable CSV template with the expected headers. */
    public function importSample()
    {
        $headers = ['Name', 'Email', 'Dial Code', 'Mobile', 'Company', 'Website', 'Country', 'City', 'Category', 'Sub Category', 'Note'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, ['John Doe', 'john@example.com', '+880', '1711000000', 'Acme Ltd', 'https://acme.io', 'Bangladesh', 'Dhaka', 'VIP', 'Gold', 'Imported client']);
            fclose($out);
        }, 'clients-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** Header aliases → User columns (case / spacing / wording insensitive). */
    private const IMPORT_ALIASES = [
        'name' => ['name', 'fullname', 'clientname', 'customername', 'contactname', 'firstname'],
        'email' => ['email', 'emailaddress', 'mail', 'emailid', 'workemail'],
        'phone' => ['phone', 'phonenumber', 'phoneno', 'mobile', 'mobilenumber', 'mobileno', 'contact', 'contactnumber', 'cell', 'whatsapp', 'number', 'msisdn'],
        'dial_code' => ['dialcode', 'countrycode', 'phonecode'],
        'company' => ['company', 'companyname', 'organization', 'organisation', 'business', 'businessname', 'firm'],
        'website' => ['website', 'url', 'site', 'web', 'officialwebsite'],
        'job_title' => ['jobtitle', 'designation', 'title', 'position'],
        'address' => ['address', 'streetaddress', 'street', 'companyaddress'],
        'city' => ['city', 'town'],
        'state' => ['state', 'region', 'province'],
        'country' => ['country', 'nation'],
        'zip' => ['zip', 'zipcode', 'postalcode', 'postcode', 'pincode'],
        'note' => ['note', 'notes', 'description', 'remarks', 'comment', 'comments', 'message'],
        'gender' => ['gender', 'sex'],
        'salutation' => ['salutation', 'title'],
        'tax_name' => ['taxname', 'tax'],
        'gst_number' => ['gst', 'gstnumber', 'vat', 'vatnumber', 'gstvat'],
        'office_phone' => ['officephone', 'officephonenumber', 'landline'],
        'client_category' => ['category', 'clientcategory'],
        'client_sub_category' => ['subcategory', 'clientsubcategory'],
    ];

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

    /** Parse the uploaded CSV / Excel → create clients (dedupe by email, skip bad rows). */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        @set_time_limit(300); // large files can take a while to parse

        // Read rows from either a CSV or an Excel (.xlsx/.xls) upload.
        $file = $request->file('file');
        if (in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath())
                ->getActiveSheet()->toArray(null, true, true, false);
        } else {
            $rows = array_map('str_getcsv', file($file->getRealPath()));
        }

        $map = $this->mapImportColumns(array_shift($rows) ?: []);
        if (! in_array('email', $map, true)) {
            return back()->with('error', 'Could not recognise an Email column. Check the header row of your file.');
        }

        // Imported clients get a throwaway random password (they set their own via the
        // "reset password" email). Hash it at a low bcrypt cost so a big import stays fast —
        // default-cost bcrypt per row is what blows past the execution-time limit.
        $cheapHash = fn () => \Illuminate\Support\Facades\Hash::make(Str::random(16), ['rounds' => 4]);

        // Tag every client created in this run with one batch key so the whole import
        // can be undone in a single click.
        $batchKey = 'imp_'.Str::random(20);
        $created = 0;
        $skipped = [];
        foreach ($rows as $n => $rawRow) {
            $row = array_values($rawRow);
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            $data = [];
            foreach ($map as $i => $field) {
                $data[$field] = trim((string) ($row[$i] ?? ''));
            }
            $email = $data['email'] ?? '';

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = 'Row '.($n + 2).': missing or invalid email';

                continue;
            }
            if (User::where('email', $email)->exists()) {
                $skipped[] = 'Row '.($n + 2)." ({$email}): duplicate";

                continue;
            }

            User::create([
                'name' => ($data['name'] ?? '') !== '' ? $data['name'] : Str::of($email)->before('@')->toString(),
                'email' => $email,
                'phone' => ($data['phone'] ?? '') ?: null,
                'dial_code' => ($data['dial_code'] ?? '') ?: null,
                'company' => ($data['company'] ?? '') ?: null,
                'website' => ($data['website'] ?? '') ?: null,
                'job_title' => ($data['job_title'] ?? '') ?: null,
                'address' => ($data['address'] ?? '') ?: null,
                'city' => ($data['city'] ?? '') ?: null,
                'state' => ($data['state'] ?? '') ?: null,
                'country' => ($data['country'] ?? '') ?: null,
                'zip' => ($data['zip'] ?? '') ?: null,
                'note' => ($data['note'] ?? '') ?: null,
                'gender' => in_array($data['gender'] ?? null, ['Male', 'Female', 'Other'], true) ? $data['gender'] : null,
                'salutation' => ($data['salutation'] ?? '') ?: null,
                'tax_name' => ($data['tax_name'] ?? '') ?: null,
                'gst_number' => ($data['gst_number'] ?? '') ?: null,
                'office_phone' => ($data['office_phone'] ?? '') ?: null,
                'client_category' => ($data['client_category'] ?? '') ?: null,
                'client_sub_category' => ($data['client_sub_category'] ?? '') ?: null,
                'role' => User::ROLE_CUSTOMER,
                'status' => User::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
                'import_batch' => $batchKey,
                'password' => $cheapHash(), // already-hashed → the 'hashed' cast keeps it as-is
            ]);
            $created++;
        }

        // Record the batch so it can be undone from the clients list.
        if ($created > 0) {
            \App\Models\ClientImportBatch::create([
                'batch_key' => $batchKey,
                'imported_by' => $request->user()->id,
                'count' => $created,
                'created_at' => now(),
            ]);
        }

        return redirect()->route('admin.clients.index')
            ->with('status', "Imported {$created} client(s)".(count($skipped) ? ', skipped '.count($skipped).'.' : '.'))
            ->with('import_skipped', array_slice($skipped, 0, 20));
    }

    /** Delete many clients at once (from the filtered list's checkboxes). */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $clients = User::clients()->whereIn('id', $data['ids'])->get();
        $deleted = 0;
        $blocked = 0;
        foreach ($clients as $client) {
            if (! $request->user()->canAct('clients', 'delete', $client)) {
                $blocked++;

                continue;
            }
            $client->delete(); // soft-delete → moves to the Bin
            $deleted++;
        }

        $msg = "Deleted {$deleted} client(s).";
        if ($blocked > 0) {
            $msg .= " Skipped {$blocked} you don’t have permission to delete.";
        }

        return redirect()->route('admin.clients.index')->with('status', $msg);
    }

    /** Undo the most recent client import — deletes the clients it created. */
    public function undoImport(Request $request)
    {
        $batch = \App\Models\ClientImportBatch::undoable();
        if (! $batch) {
            return back()->with('error', 'No recent import to undo.');
        }

        // Only remove clients still tagged with this batch that this import created,
        // and skip any that have since gained related records (orders/invoices/tickets).
        $clients = User::clients()->where('import_batch', $batch->batch_key)->get();
        $deleted = 0;
        $kept = 0;
        foreach ($clients as $client) {
            if ($client->orders()->exists() || ClientInvoice::where('client_id', $client->id)->exists() || $client->tickets()->exists()) {
                $kept++;

                continue; // has activity now — don't wipe it out silently
            }
            $client->delete(); // soft-delete → recoverable from the Bin
            $deleted++;
        }

        $batch->update(['undone_at' => now()]);

        $msg = "Undid the last import — removed {$deleted} client(s).";
        if ($kept > 0) {
            $msg .= " Kept {$kept} that already had orders/invoices/tickets.";
        }

        return redirect()->route('admin.clients.index')->with('status', $msg);
    }

    // ===== Export (CSV · Excel · PDF) =====

    /** Column headers + row values shared by every export format. */
    private function exportData($clients): array
    {
        $headers = ['Client ID', 'Name', 'Company', 'Email', 'Mobile', 'Country', 'Category', 'Sub Category', 'Status', 'Created At'];
        $rows = [];
        foreach ($clients as $c) {
            $rows[] = [
                $c->client_code, $c->name, $c->company, $c->email, trim($c->dial_code.' '.$c->phone),
                $c->country, $c->client_category, $c->client_sub_category,
                User::STATUSES[$c->status] ?? $c->status, $c->created_at?->format('Y-m-d H:i'),
            ];
        }

        return [$headers, $rows];
    }

    /** Dispatch to the requested export format (csv · excel · pdf). */
    private function export($clients, string $format)
    {
        return match ($format) {
            'excel' => $this->exportExcel($clients),
            'pdf' => $this->exportPdf($clients),
            default => $this->exportCsv($clients),
        };
    }

    private function exportCsv($clients)
    {
        [$headers, $rows] = $this->exportData($clients);

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, 'clients-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function exportExcel($clients)
    {
        [$headers, $rows] = $this->exportData($clients);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clients');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'clients-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function exportPdf($clients)
    {
        [$headers, $rows] = $this->exportData($clients);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.clients.export-pdf', [
            'headers' => $headers,
            'rows' => $rows,
            'generatedAt' => now()->format('d M Y, g:i A'),
        ])->setPaper('a4', 'landscape')->download('clients-'.now()->format('Y-m-d').'.pdf');
    }

    private function validated(Request $request, ?User $client = null): array
    {
        $data = $request->validate([
            // Only email is required to add a client — everything else is optional.
            'salutation' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client)],
            'phone' => ['nullable', 'string', 'max:40'],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'photo' => ['nullable', 'image', 'max:2048'], // ≤2MB
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'language' => ['nullable', 'string', 'max:40'],
            'client_category' => ['nullable', 'string', 'max:120'],
            'client_sub_category' => ['nullable', 'string', 'max:120'],
            'client_label' => ['nullable', 'string', 'max:40'],
            // Company
            'company' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'tax_name' => ['nullable', 'string', 'max:120'],
            'gst_number' => ['nullable', 'string', 'max:60'],
            'office_phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:65535'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
            'password' => ['nullable', 'string', 'min:4'], // min 4 characters
        ]);

        // The file and password are handled explicitly in store()/update() — never mass-assign them
        // from here (a blank password must NOT overwrite the existing hash with null).
        unset($data['photo'], $data['password']);

        // Name column is NOT NULL — fall back to the email's local part when left blank.
        $data['name'] = trim((string) ($data['name'] ?? '')) ?: Str::of($data['email'])->before('@')->toString();

        // Login Allowed (Yes/No) drives the account status; e-mail notifications is a toggle.
        $data['status'] = $request->boolean('login_allowed', ! $client) ? User::STATUS_ACTIVE : User::STATUS_BLOCKED;
        $data['receive_email_notifications'] = $request->boolean('receive_email_notifications');

        return $data;
    }

    /** Store the uploaded client image (if any) and return its public path, deleting the old one. */
    private function handlePhoto(Request $request, array &$data, ?User $client = null): void
    {
        if ($request->hasFile('photo')) {
            if ($client && $client->photo) {
                Storage::disk('public')->delete($client->photo);
            }
            $data['photo'] = $request->file('photo')->store('clients', 'public');
        }
    }
}
