<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $base = Deal::query()->with('client:id,name', 'assignee:id,name', 'lead:id,full_name');

        // Staff see only their own deals.
        if ($request->user()->isStaff()) {
            $base->where('assigned_to', $request->user()->id);
        }

        $view = $request->query('view') === 'list' ? 'list' : 'board';

        // Kanban columns grouped by stage.
        $byStage = (clone $base)->latest('id')->get()->groupBy('stage');

        $open = ['new', 'qualified', 'proposal', 'negotiation'];
        $stats = [
            ['label' => 'Open Deals', 'value' => (clone $base)->whereIn('stage', $open)->count(), 'tone' => 'bg-[var(--color-primary-soft)] text-[var(--color-primary)]'],
            ['label' => 'Pipeline Value', 'value' => number_format((float) (clone $base)->whereIn('stage', $open)->sum('value')), 'tone' => 'bg-amber-50 text-amber-600'],
            ['label' => 'Won', 'value' => (clone $base)->where('stage', 'won')->count(), 'tone' => 'bg-emerald-50 text-emerald-600'],
            ['label' => 'Won Value', 'value' => number_format((float) (clone $base)->where('stage', 'won')->sum('value')), 'tone' => 'bg-sky-50 text-sky-600'],
        ];

        return view('admin.deals.index', [
            'view' => $view,
            'byStage' => $byStage,
            'deals' => $view === 'list' ? (clone $base)->latest('id')->paginate(20)->withQueryString() : null,
            'stats' => $stats,
        ]);
    }

    /** Read-only deal detail with its linked lead, client and any invoices raised. */
    public function show(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $deal->load('client', 'lead', 'assignee', 'invoices');

        return view('admin.deals.show', ['deal' => $deal]);
    }

    public function create(Request $request)
    {
        $lead = $request->filled('lead') ? Lead::find($request->query('lead')) : null;

        return view('admin.deals.form', [
            'deal' => new Deal([
                'stage' => 'new', 'currency' => 'USD',
                'title' => $lead ? ($lead->company_name ?: $lead->full_name).' — Deal' : '',
                'lead_id' => $lead?->id,
                'client_id' => $lead?->converted_client_id,
                'assigned_to' => $lead?->assigned_to ?? $request->user()->id,
            ]),
            'clients' => User::clients()->orderBy('name')->get(['id', 'name']),
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        Deal::create($this->validated($request));

        return redirect()->route('admin.deals.index')->with('status', 'Deal created.');
    }

    public function edit(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);

        return view('admin.deals.form', [
            'deal' => $deal,
            'clients' => User::clients()->orderBy('name')->get(['id', 'name']),
            'staff' => User::assignable()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $deal->update($this->validated($request));

        return redirect()->route('admin.deals.index')->with('status', 'Deal updated.');
    }

    /** Quick stage change from the board/list (e.g. move to Won). */
    public function stage(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $data = $request->validate(['stage' => ['required', Rule::in(array_keys(Deal::STAGES))]]);
        $deal->update($data);

        return back()->with('status', "Deal moved to {$deal->stage}.");
    }

    public function destroy(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $deal->delete();

        return back()->with('status', 'Deal deleted.');
    }

    /** Create a draft invoice from a won deal, pre-filled with one line for the deal value. */
    public function invoice(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);

        $client = $deal->client;
        $invoice = ClientInvoice::create([
            'invoice_number' => ClientInvoice::nextNumber(),
            'public_token' => Str::random(40),
            'client_id' => $client?->id,
            'deal_id' => $deal->id,
            'bill_to_name' => $client?->name ?? $deal->title,
            'bill_to_company' => $client?->company,
            'bill_to_email' => $client?->email,
            'bill_to_phone' => $client?->phone,
            'bill_to_address' => $client ? collect([$client->address, $client->city, $client->state, $client->country, $client->zip])->filter()->join(', ') : null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => $deal->currency,
            'status' => 'draft',
            'subtotal' => $deal->value,
            'total' => $deal->value,
            'created_by' => $request->user()->id,
        ]);
        $invoice->items()->create([
            'description' => $deal->title, 'qty' => 1, 'unit_price' => $deal->value, 'amount' => $deal->value, 'sort_order' => 0,
        ]);

        return redirect()->route('admin.invoices.edit', $invoice)->with('status', "Invoice created from deal \"{$deal->title}\".");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:users,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'stage' => ['required', Rule::in(array_keys(Deal::STAGES))],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function authorizeDeal(Request $request, Deal $deal): void
    {
        abort_if($request->user()->isStaff() && $deal->assigned_to !== $request->user()->id, 403);
    }
}
