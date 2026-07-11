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

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%"));
        }

        // Sorting — whitelist the sortable columns; default to newest first.
        $sortable = ['id', 'name', 'email', 'phone', 'status', 'created_at'];
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sort, $dir);

        // Per-page — "Show N entries".
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        // List or grid layout.
        $view = $request->query('view') === 'grid' ? 'grid' : 'list';

        return view('admin.clients.index', [
            'clients' => $q->paginate($perPage)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'perPage' => $perPage,
            'view' => $view,
        ]);
    }

    /** Client profile: details + invoices, payments and documents for the tabbed profile page. */
    public function show(User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);

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
        return view('admin.clients.form', ['client' => new User(['role' => User::ROLE_CUSTOMER])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['role'] = User::ROLE_CUSTOMER;
        // Password is optional — generate a random one when omitted (column is NOT NULL). Hashed by cast.
        $data['password'] = $request->filled('password') ? $request->input('password') : Str::random(16);
        $this->handlePhoto($request, $data);

        User::create($data);

        return redirect()->route('admin.clients.index')->with('status', 'Client created.');
    }

    public function edit(User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);

        return view('admin.clients.form', compact('client'));
    }

    public function update(Request $request, User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);

        $data = $this->validated($request, $client);
        if ($request->filled('password')) {
            $data['password'] = $request->input('password'); // hashed by cast
        }
        $this->handlePhoto($request, $data, $client);

        $client->update($data);

        return redirect()->route('admin.clients.index')->with('status', 'Client updated.');
    }

    public function destroy(User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);

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
    public function export(Request $request)
    {
        $q = User::clients()->orderBy('id');
        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%"));
        }

        $filename = 'clients-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Id', 'Name', 'Email', 'Mobile', 'Company', 'Country', 'Status', 'Created']);
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->id,
                        $c->name,
                        $c->email,
                        trim($c->dial_code.' '.$c->phone),
                        $c->company,
                        $c->country,
                        User::STATUSES[$c->status] ?? $c->status,
                        $c->created_at?->format('Y-m-d'),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Import clients from a CSV with a header row (name, email, phone, company, country). */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), (array) fgetcsv($handle));

        $created = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue; // blank line
            }
            // Normalise the row to the header width so array_combine never mismatches.
            $row = array_pad(array_slice($row, 0, count($header)), count($header), null);
            $data = array_combine($header, $row);
            $email = trim((string) ($data['email'] ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
                $skipped++;
                continue;
            }
            User::create([
                'name' => trim((string) ($data['name'] ?? '')) ?: Str::of($email)->before('@')->toString(),
                'email' => $email,
                'phone' => $data['phone'] ?? $data['mobile'] ?? null,
                'company' => $data['company'] ?? null,
                'country' => $data['country'] ?? null,
                'role' => User::ROLE_CUSTOMER,
                'status' => User::STATUS_ACTIVE,
                'password' => Str::random(16),
            ]);
            $created++;
        }
        fclose($handle);

        return redirect()->route('admin.clients.index')->with('status', "Import complete — {$created} added, {$skipped} skipped.");
    }

    private function validated(Request $request, ?User $client = null): array
    {
        $data = $request->validate([
            // Only email is required to add a client — everything else is optional.
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client)],
            'phone' => ['nullable', 'string', 'max:40'],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'photo' => ['nullable', 'image', 'max:2048'], // ≤2MB
            'company' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:65535'],
            'status' => ['nullable', Rule::in(array_keys(User::STATUSES))],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        // The file and password are handled explicitly in store()/update() — never mass-assign them
        // from here (a blank password must NOT overwrite the existing hash with null).
        unset($data['photo'], $data['password']);

        // Name column is NOT NULL — fall back to the email's local part when left blank.
        $data['name'] = trim((string) ($data['name'] ?? '')) ?: Str::of($data['email'])->before('@')->toString();

        // Default new/blank clients to active.
        $data['status'] = $data['status'] ?? User::STATUS_ACTIVE;

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
