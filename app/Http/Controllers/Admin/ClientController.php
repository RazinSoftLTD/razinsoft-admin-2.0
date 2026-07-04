<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Clients = customer-role users (from site registration, admin create, or lead conversion). */
class ClientController extends Controller
{
    public function index(Request $request)
    {
        $q = User::clients()->latest();

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%"));
        }

        return view('admin.clients.index', [
            'clients' => $q->paginate(15)->withQueryString(),
        ]);
    }

    /** Client profile: details + all of their CRM invoices with running totals. */
    public function show(User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);

        $invoices = \App\Models\ClientInvoice::where('client_id', $client->id)
            ->withCount('items')->latest('id')->get();

        return view('admin.clients.show', [
            'client' => $client,
            'invoices' => $invoices,
            'stats' => [
                'count' => $invoices->count(),
                'invoiced' => round((float) $invoices->sum('total'), 2),
                'paid' => round((float) $invoices->sum('amount_paid'), 2),
                'due' => round($invoices->sum(fn ($i) => $i->amountDue()), 2),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.clients.form', ['client' => new User(['role' => User::ROLE_CUSTOMER])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['role'] = User::ROLE_CUSTOMER;
        $data['password'] = $request->input('password'); // hashed by cast

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

        $client->update($data);

        return redirect()->route('admin.clients.index')->with('status', 'Client updated.');
    }

    public function destroy(User $client)
    {
        abort_unless($client->role === User::ROLE_CUSTOMER, 404);

        $client->delete();

        return back()->with('status', 'Client deleted.');
    }

    private function validated(Request $request, ?User $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client)],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:20'],
            'password' => [$client ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }
}
