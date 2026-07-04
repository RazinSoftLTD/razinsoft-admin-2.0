<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringInvoiceController extends Controller
{
    public function index()
    {
        return view('admin.recurring.index', [
            'profiles' => RecurringInvoice::with('client:id,name')->latest('id')->paginate(15),
        ]);
    }

    public function create()
    {
        return view('admin.recurring.form', [
            'profile' => new RecurringInvoice(['interval' => 'monthly', 'currency' => 'USD', 'due_days' => 14, 'next_run_at' => now()->addMonthNoOverflow()->toDateString(), 'active' => true]),
            'clients' => User::clients()->orderBy('name')->get(['id', 'name']),
            'items' => [['description' => '', 'qty' => 1, 'unit_price' => 0, 'discount_percent' => 0, 'tax_percent' => 0]],
        ]);
    }

    public function store(Request $request)
    {
        RecurringInvoice::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.recurring.index')->with('status', 'Recurring profile created.');
    }

    public function edit(RecurringInvoice $recurring)
    {
        return view('admin.recurring.form', [
            'profile' => $recurring,
            'clients' => User::clients()->orderBy('name')->get(['id', 'name']),
            'items' => $recurring->items,
        ]);
    }

    public function update(Request $request, RecurringInvoice $recurring)
    {
        $recurring->update($this->validated($request));

        return redirect()->route('admin.recurring.index')->with('status', 'Recurring profile updated.');
    }

    public function destroy(RecurringInvoice $recurring)
    {
        $recurring->delete();

        return back()->with('status', 'Recurring profile deleted.');
    }

    /** Generate an invoice from this profile right now. */
    public function run(RecurringInvoice $recurring)
    {
        $invoice = $recurring->generate();

        return redirect()->route('admin.invoices.show', $invoice)->with('status', "Invoice {$invoice->invoice_number} generated from the recurring profile.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:users,id'],
            'currency' => ['required', 'string', 'max:8'],
            'interval' => ['required', Rule::in(array_keys(RecurringInvoice::INTERVALS))],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'next_run_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
