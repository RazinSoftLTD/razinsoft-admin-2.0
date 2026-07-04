<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $q = ClientInvoice::query()->with('client:id,name')->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('bill_to_name', 'like', "%{$search}%")
                ->orWhere('bill_to_company', 'like', "%{$search}%"));
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return view('admin.invoices.index', [
            'invoices' => $q->paginate(15)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.invoices.form', [
            'invoice' => new ClientInvoice([
                'invoice_number' => $this->nextNumber(),
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'currency' => 'USD',
                'status' => 'draft',
                'terms' => 'Payment should be made within the due date. Late payment may incur additional charges.',
                'notes' => 'Thank you for your business. We appreciate your trust in our services.',
            ]),
            'clients' => User::clients()->orderBy('name')->get(['id', 'name', 'company', 'email', 'phone', 'address', 'city', 'state', 'country', 'zip']),
            'items' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $invoice = DB::transaction(fn () => $this->persist(new ClientInvoice([
            'invoice_number' => $this->nextNumber(),
            'public_token' => \Illuminate\Support\Str::random(40),
            'created_by' => $request->user()->id,
        ]), $data, $request));

        return redirect()->route('admin.invoices.show', $invoice)->with('status', "Invoice {$invoice->invoice_number} saved.");
    }

    public function show(ClientInvoice $invoice)
    {
        $invoice->load('items', 'client', 'payments.recorder', 'installments');

        return view('admin.invoices.show', compact('invoice'));
    }

    public function edit(ClientInvoice $invoice)
    {
        $invoice->load('items');

        return view('admin.invoices.form', [
            'invoice' => $invoice,
            'clients' => User::clients()->orderBy('name')->get(['id', 'name', 'company', 'email', 'phone', 'address', 'city', 'state', 'country', 'zip']),
            'items' => $invoice->items->map(fn ($i) => [
                'description' => $i->description, 'sub_description' => $i->sub_description,
                'qty' => (float) $i->qty, 'unit_price' => (float) $i->unit_price,
                'discount_percent' => (float) $i->discount_percent, 'tax_percent' => (float) $i->tax_percent,
            ])->all(),
        ]);
    }

    public function update(Request $request, ClientInvoice $invoice)
    {
        $data = $this->validated($request);
        DB::transaction(fn () => $this->persist($invoice, $data, $request));

        return redirect()->route('admin.invoices.show', $invoice)->with('status', "Invoice {$invoice->invoice_number} updated.");
    }

    public function destroy(ClientInvoice $invoice)
    {
        if ($invoice->attachment) {
            Storage::disk('public')->delete($invoice->attachment);
        }
        $invoice->delete();

        return redirect()->route('admin.invoices.index')->with('status', 'Invoice deleted.');
    }

    /** Split the invoice total into N (roughly) equal installments with staggered due dates. */
    public function installments(Request $request, ClientInvoice $invoice)
    {
        $data = $request->validate([
            'parts' => ['required', 'integer', 'min:1', 'max:24'],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $parts = $data['parts'];
        $interval = $data['interval_days'] ?? 30;
        $each = floor(((float) $invoice->total / $parts) * 100) / 100;
        $start = $invoice->due_date ?? now()->addDays($interval);

        $invoice->installments()->delete();
        for ($i = 0; $i < $parts; $i++) {
            // Last part absorbs the rounding remainder so the installments sum to the total.
            $amount = $i === $parts - 1 ? round((float) $invoice->total - $each * ($parts - 1), 2) : $each;
            $invoice->installments()->create([
                'label' => 'Installment '.($i + 1).' of '.$parts,
                'amount' => $amount,
                'due_date' => $start->copy()->addDays($interval * $i),
                'sort_order' => $i,
            ]);
        }

        return back()->with('status', "Split into {$parts} installment(s).");
    }

    /** Set a payment request amount (what the client is asked to pay now). */
    public function requestPayment(Request $request, ClientInvoice $invoice)
    {
        $data = $request->validate([
            'requested_amount' => ['nullable', 'numeric', 'min:0.01', 'max:'.max(0.01, $invoice->amountDue())],
        ]);

        $invoice->update(['requested_amount' => $data['requested_amount'] ?? null]);

        return back()->with('status', $data['requested_amount']
            ? 'Payment request set to '.number_format($data['requested_amount'], 2).'. Share the pay link with the client.'
            : 'Payment request cleared — the client can pay the full due.');
    }

    /** Mark sent and (best-effort) email the client the invoice + pay link. */
    public function send(ClientInvoice $invoice)
    {
        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        if ($invoice->bill_to_email) {
            try {
                \Illuminate\Support\Facades\Mail::to($invoice->bill_to_email)
                    ->send(new \App\Mail\InvoiceSent($invoice));
            } catch (\Throwable $e) {
                return back()->with('status', 'Invoice marked sent. Email could not be delivered (mail not configured): '.$e->getMessage());
            }
        }

        return back()->with('status', 'Invoice sent to '.($invoice->bill_to_email ?: 'the client').'.');
    }

    public function pdf(ClientInvoice $invoice)
    {
        $invoice->load('items', 'client', 'payments');
        $pdf = Pdf::loadView('admin.invoices.pdf', ['invoice' => $invoice]);

        return $pdf->stream("{$invoice->invoice_number}.pdf");
    }

    /** Create/update the invoice + its items and recompute totals from the line data. */
    private function persist(ClientInvoice $invoice, array $data, Request $request): ClientInvoice
    {
        $client = $data['client_id'] ? User::find($data['client_id']) : null;

        $invoice->fill([
            'client_id' => $client?->id,
            'bill_to_name' => $client?->name ?? $data['bill_to_name'] ?? null,
            'bill_to_company' => $client?->company,
            'bill_to_email' => $client?->email,
            'bill_to_phone' => $client?->phone,
            'bill_to_address' => $client ? collect([$client->address, $client->city, $client->state, $client->country, $client->zip])->filter()->join(', ') : null,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? null,
            'currency' => $data['currency'],
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $invoice->attachment = $file->storeAs('invoices/attachments', $file->getClientOriginalName(), 'public');
        }

        // ---- Totals from line items ----
        $subtotal = $discountTotal = $taxTotal = 0;
        $lines = [];
        foreach ($data['items'] as $i => $row) {
            $qty = (float) $row['qty'];
            $price = (float) $row['unit_price'];
            $gross = $qty * $price;
            $discount = $gross * ((float) ($row['discount_percent'] ?? 0)) / 100;
            $net = $gross - $discount;
            $tax = $net * ((float) ($row['tax_percent'] ?? 0)) / 100;

            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $tax;

            $lines[] = [
                'description' => $row['description'], 'sub_description' => $row['sub_description'] ?? null,
                'qty' => $qty, 'unit_price' => $price,
                'discount_percent' => (float) ($row['discount_percent'] ?? 0), 'tax_percent' => (float) ($row['tax_percent'] ?? 0),
                'amount' => round($net, 2), 'sort_order' => $i,
            ];
        }

        $invoice->subtotal = round($subtotal, 2);
        $invoice->discount_total = round($discountTotal, 2);
        $invoice->tax_total = round($taxTotal, 2);
        $invoice->total = round($subtotal - $discountTotal + $taxTotal, 2);
        $invoice->save();

        $invoice->items()->delete();
        $invoice->items()->createMany($lines);

        return $invoice;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['nullable', 'exists:users,id'],
            'bill_to_name' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['required', 'string', 'max:8'],
            'status' => ['nullable', 'in:draft,sent'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'attachment' => ['nullable', 'file', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.sub_description' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function nextNumber(): string
    {
        return ClientInvoice::nextNumber();
    }
}
