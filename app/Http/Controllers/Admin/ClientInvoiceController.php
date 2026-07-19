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

        // Apply the Owned / Added / Added & Owned / All view scope from the user's role.
        $request->user()->applyScope($q, 'invoices', 'view');

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

    public function create(Request $request)
    {
        $tpl = $request->filled('template') ? \App\Models\InvoiceTemplate::find($request->query('template')) : null;

        return view('admin.invoices.form', [
            'invoice' => new ClientInvoice([
                'invoice_number' => ClientInvoice::previewNumber(), // preview only — real number is allocated on save
                'client_id' => $request->query('client_id'),
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'currency' => $tpl->currency ?? 'USD',
                'status' => 'draft',
                'terms' => $tpl->terms ?? 'Payment should be made within the due date. Late payment may incur additional charges.',
                'notes' => $tpl->notes ?? 'Thank you for your business. We appreciate your trust in our services.',
                'payment_method' => $tpl->payment_method ?? 'Bank Transfer',
            ]),
            'clients' => User::clients()->orderBy('name')->get(['id', 'name', 'company', 'email', 'phone', 'address', 'city', 'state', 'country', 'zip']),
            'items' => $tpl ? collect($tpl->items)->map(fn ($i) => [
                'description' => $i['description'] ?? '', 'sub_description' => $i['sub_description'] ?? '',
                'qty' => (float) ($i['qty'] ?? 1), 'unit' => $i['unit'] ?? \App\Models\InvoiceUnit::defaultName(),
                'unit_price' => (float) ($i['unit_price'] ?? 0),
                'discount_percent' => (float) ($i['discount_percent'] ?? 0), 'taxIds' => [],
            ])->all() : [],
        ] + $this->configData());
    }

    /** Shared config lists for the invoice form (units, taxes, branding). */
    private function configData(): array
    {
        return [
            'units' => \App\Models\InvoiceUnit::options(),
            'taxes' => \App\Models\InvoiceTax::options(),
            'branding' => \App\Models\InvoiceSetting::current(),
            'defaultUnit' => \App\Models\InvoiceUnit::defaultName(),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $invoice = DB::transaction(fn () => $this->persist(new ClientInvoice([
            'invoice_number' => $this->nextNumber(),
            'public_token' => \Illuminate\Support\Str::random(40),
            'created_by' => $request->user()->id,
        ]), $data, $request));
        $invoice->logActivity('created', 'Invoice created.');

        return redirect()->route('admin.invoices.show', $invoice)->with('status', "Invoice {$invoice->invoice_number} saved.");
    }

    public function show(ClientInvoice $invoice)
    {
        // Honour the Owned / Added / Added & Owned / All view scope.
        abort_unless(request()->user()->canAct('invoices', 'view', $invoice), 403);

        $invoice->load('items', 'client', 'payments.recorder', 'payments.project', 'activities.user');

        return view('admin.invoices.show', [
            'invoice' => $invoice,
            'projects' => \App\Models\Project::when($invoice->client_id, fn ($q) => $q->where('client_id', $invoice->client_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(ClientInvoice $invoice)
    {
        abort_unless(auth()->user()->canAct('invoices', 'edit', $invoice), 403);
        $invoice->load('items');

        return view('admin.invoices.form', [
            'invoice' => $invoice,
            'clients' => User::clients()->orderBy('name')->get(['id', 'name', 'company', 'email', 'phone', 'address', 'city', 'state', 'country', 'zip']),
            'items' => $invoice->items->map(fn ($i) => [
                'description' => $i->description, 'sub_description' => $i->sub_description,
                'qty' => (float) $i->qty, 'unit' => $i->unit ?? \App\Models\InvoiceUnit::defaultName(),
                'unit_price' => (float) $i->unit_price, 'discount_percent' => (float) $i->discount_percent,
                'taxIds' => collect($i->taxes ?? [])->pluck('id')->filter()->values()->all(),
                'attachment' => $i->attachment,
            ])->all(),
        ] + $this->configData());
    }

    public function update(Request $request, ClientInvoice $invoice)
    {
        abort_unless($request->user()->canAct('invoices', 'edit', $invoice), 403);
        $data = $this->validated($request);
        DB::transaction(fn () => $this->persist($invoice, $data, $request));
        $invoice->logActivity('updated', 'Invoice details updated.');

        return redirect()->route('admin.invoices.show', $invoice)->with('status', "Invoice {$invoice->invoice_number} updated.");
    }

    public function destroy(ClientInvoice $invoice)
    {
        abort_unless(auth()->user()->canAct('invoices', 'delete', $invoice), 403);
        // Soft-delete → the invoice moves to the Bin (recoverable for 30 days). Files are kept.
        $invoice->logActivity('deleted', 'Invoice moved to the Trash.');
        $invoice->delete();

        return redirect()->route('admin.invoices.index')->with('status', "Invoice {$invoice->invoice_number} moved to the Bin.");
    }

    /**
     * Set the "amount to receive" — what the client's online pay link will charge.
     * Validated so it can never exceed the current due.
     */
    public function requestPayment(Request $request, ClientInvoice $invoice)
    {
        $data = $request->validate([
            'requested_amount' => ['nullable', 'numeric', 'min:0.01', 'max:'.max(0.01, $invoice->amountDue())],
        ], [
            'requested_amount.max' => 'The amount to receive cannot be more than the current due ('.number_format($invoice->amountDue(), 2).').',
        ]);

        $invoice->update(['requested_amount' => $data['requested_amount'] ?? null]);

        return back()->with('status', ! empty($data['requested_amount'])
            ? 'Amount to receive set to '.number_format($data['requested_amount'], 2).'. The pay link will charge exactly this.'
            : 'Cleared — the pay link will charge the full due.');
    }

    /** Payment Options: which gateways the pay link offers + optional partial-payment amount. */
    public function payOptions(Request $request, ClientInvoice $invoice)
    {
        $data = $request->validate([
            'pay_methods' => ['required', 'array', 'min:1'],
            'pay_methods.*' => ['in:stripe,paypal'],
            'partial_enabled' => ['nullable', 'boolean'],
            'partial_amount' => ['nullable', 'required_if:partial_enabled,1', 'numeric', 'min:0.01', 'max:'.max(0.01, $invoice->amountDue())],
            'partial_note' => ['nullable', 'string', 'max:255'],
        ], [
            'pay_methods.required' => 'Select at least one payment method.',
            'partial_amount.required_if' => 'Enter the partial payment amount.',
            'partial_amount.max' => 'The partial amount cannot be more than the current due ('.number_format($invoice->amountDue(), 2).').',
        ]);

        $invoice->update([
            'pay_methods' => array_values(array_unique($data['pay_methods'])),
            // Partial payment reuses requested_amount — the pay link charges exactly this.
            'requested_amount' => $request->boolean('partial_enabled') ? $data['partial_amount'] : null,
            // Optional remark: shown on the pay link, recorded on the payment once paid.
            'requested_note' => $request->boolean('partial_enabled') ? ($data['partial_note'] ?? null) : null,
        ]);

        return back()->with('status', 'Payment options updated.');
    }

    /** Mark sent and (best-effort) email the client the invoice + pay link. */
    public function send(ClientInvoice $invoice)
    {
        abort_unless(auth()->user()->canAct('invoices', 'send', $invoice), 403);
        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        if ($invoice->bill_to_email) {
            try {
                \Illuminate\Support\Facades\Mail::to($invoice->bill_to_email)
                    ->send(new \App\Mail\InvoiceSent($invoice));
            } catch (\Throwable $e) {
                $invoice->logActivity('sent', 'Marked sent (email delivery failed).');

                return back()->with('status', 'Invoice marked sent. Email could not be delivered (mail not configured): '.$e->getMessage());
            }
        }
        $invoice->logActivity('sent', 'Invoice emailed to '.($invoice->bill_to_email ?: 'the client').'.');

        return back()->with('status', 'Invoice sent to '.($invoice->bill_to_email ?: 'the client').'.');
    }

    public function pdf(Request $request, ClientInvoice $invoice)
    {
        $invoice->load('items', 'client', 'payments');
        $pdf = Pdf::loadView('admin.invoices.pdf', ['invoice' => $invoice]);
        $name = "{$invoice->invoice_number}.pdf";

        // ?download=1 forces a file download; otherwise it opens inline in the browser.
        return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
    }

    /** Cancel an invoice (keeps it for the record; excluded from due totals by status). */
    public function cancel(ClientInvoice $invoice)
    {
        abort_unless(auth()->user()->canAct('invoices', 'cancel', $invoice), 403);
        $invoice->update(['status' => 'cancelled']);
        $invoice->logActivity('cancelled', 'Invoice cancelled.');

        return back()->with('status', "Invoice {$invoice->invoice_number} cancelled.");
    }

    /** Save/replace the shipping address for an invoice. */
    public function shippingAddress(Request $request, ClientInvoice $invoice)
    {
        abort_unless($request->user()->canAct('invoices', 'edit', $invoice), 403);
        $data = $request->validate(['shipping_address' => ['nullable', 'string', 'max:1000']]);
        $invoice->update(['shipping_address' => $data['shipping_address'] ?? null]);
        $invoice->logActivity('shipping_updated', 'Shipping address updated.');

        return back()->with('status', 'Shipping address saved.');
    }

    /** Email the client a payment reminder (re-sends the invoice + pay link). */
    public function reminder(ClientInvoice $invoice)
    {
        abort_unless(auth()->user()->canAct('invoices', 'send', $invoice), 403);
        if (! $invoice->bill_to_email) {
            return back()->with('error', 'This invoice has no client email to remind.');
        }
        try {
            \Illuminate\Support\Facades\Mail::to($invoice->bill_to_email)->send(new \App\Mail\InvoiceSent($invoice, true));
        } catch (\Throwable $e) {
            return back()->with('error', 'Reminder could not be delivered (mail not configured): '.$e->getMessage());
        }
        $invoice->logActivity('reminder_sent', 'Payment reminder emailed to '.$invoice->bill_to_email.'.');

        return back()->with('status', 'Payment reminder sent to '.$invoice->bill_to_email.'.');
    }

    /** Clone an invoice (+ its items) into a fresh draft and open it for editing. */
    public function duplicate(Request $request, ClientInvoice $invoice)
    {
        abort_unless($request->user()->canAct('invoices', 'duplicate', $invoice), 403);
        $invoice->load('items');
        $copy = DB::transaction(function () use ($invoice, $request) {
            $new = $invoice->replicate([
                'invoice_number', 'public_token', 'status', 'amount_paid', 'requested_amount', 'created_at', 'updated_at',
            ]);
            $new->invoice_number = $this->nextNumber();
            $new->public_token = \Illuminate\Support\Str::random(40);
            $new->status = 'draft';
            $new->amount_paid = 0;
            $new->requested_amount = null;
            $new->invoice_date = now()->toDateString();
            $new->created_by = $request->user()->id;
            $new->save();

            foreach ($invoice->items as $item) {
                $line = $item->replicate(['id', 'client_invoice_id']);
                $new->items()->save($line);
            }

            return $new;
        });
        $copy->logActivity('created', "Duplicated from {$invoice->invoice_number}.");

        return redirect()->route('admin.invoices.edit', $copy)->with('status', "Duplicated to draft {$copy->invoice_number}.");
    }

    // ===== Bin (soft-deleted invoices) — super admin only =====
    public function bin()
    {
        $trashed = ClientInvoice::onlyTrashed()->with('client:id,name')->latest('deleted_at')->paginate(20);

        return view('admin.invoices.bin', ['invoices' => $trashed, 'retentionDays' => 30]);
    }

    public function restore(int $id)
    {
        $invoice = ClientInvoice::onlyTrashed()->findOrFail($id);
        $invoice->restore();
        $invoice->logActivity('restored', 'Invoice restored from the Trash.');

        return back()->with('status', "Invoice {$invoice->invoice_number} restored.");
    }

    public function forceDelete(int $id)
    {
        $invoice = ClientInvoice::onlyTrashed()->findOrFail($id);
        if ($invoice->attachment) {
            Storage::disk('public')->delete($invoice->attachment);
        }
        $number = $invoice->invoice_number;
        $invoice->forceDelete();

        return back()->with('status', "Invoice {$number} permanently deleted.");
    }

    /** Create/update the invoice + its items and recompute totals from the line data. */
    private function persist(ClientInvoice $invoice, array $data, Request $request): ClientInvoice
    {
        $client = $data['client_id'] ? User::find($data['client_id']) : null;

        $invoice->fill([
            'client_id' => $client?->id,
            // Owner = the staff managing the invoice's client (falls back to the creator) →
            // powers the "Owned" permission scope. Set once; kept stable on later edits.
            'owner_id' => $invoice->owner_id ?: ($client?->account_manager_id ?: $invoice->created_by),
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
            // No longer on the form — keep whatever the invoice already has (payments record their own method).
            'payment_method' => $data['payment_method'] ?? $invoice->payment_method,
            'status' => $data['status'] ?? 'draft',
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => (float) ($data['discount_value'] ?? 0),
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $invoice->attachment = $file->storeAs('invoices/attachments', $file->getClientOriginalName(), 'public');
        }

        // ---- Totals from line items ----
        $taxCatalog = \App\Models\InvoiceTax::whereIn('id', collect($data['items'])->pluck('taxes')->flatten()->filter()->unique())->get()->keyBy('id');
        $subtotal = $discountTotal = $taxTotal = 0;
        $lines = [];
        foreach ($data['items'] as $i => $row) {
            $qty = (float) $row['qty'];
            $price = (float) $row['unit_price'];
            $gross = $qty * $price;
            $discount = $gross * ((float) ($row['discount_percent'] ?? 0)) / 100;
            $net = $gross - $discount;

            // Resolve the selected taxes server-side (never trust submitted rates).
            $applied = collect($row['taxes'] ?? [])
                ->map(fn ($id) => $taxCatalog->get($id))
                ->filter()
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'rate' => (float) $t->rate])
                ->values();
            $rate = $applied->sum('rate');
            $tax = $net * $rate / 100;

            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $tax;

            // Per-line attachment (optional) — keep the existing one unless a new file is uploaded.
            $attachment = $row['existing_attachment'] ?? null;
            if ($request->hasFile("items.$i.attachment")) {
                $f = $request->file("items.$i.attachment");
                $attachment = $f->store('invoices/items', 'public');
            }

            $lines[] = [
                'description' => $row['description'], 'sub_description' => $row['sub_description'] ?? null,
                'qty' => $qty, 'unit' => $row['unit'] ?? null, 'unit_price' => $price,
                'discount_percent' => (float) ($row['discount_percent'] ?? 0), 'tax_percent' => $rate,
                'taxes' => $applied->all(), 'attachment' => $attachment,
                'amount' => round($net, 2), 'sort_order' => $i,
            ];
        }

        // ---- Invoice-level discount (flat amount or % of the item net) on top of any per-line discounts. ----
        $netAfterLines = $subtotal - $discountTotal;
        $invoiceDiscount = 0.0;
        if (($data['discount_type'] ?? null) === 'percent') {
            $invoiceDiscount = $netAfterLines * min(100, max(0, (float) ($data['discount_value'] ?? 0))) / 100;
        } elseif (($data['discount_type'] ?? null) === 'flat') {
            // A flat discount can never exceed what's left to discount.
            $invoiceDiscount = min(max(0, (float) ($data['discount_value'] ?? 0)), $netAfterLines);
        }
        $discountTotal += $invoiceDiscount;

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
            'client_id' => ['required', 'exists:users,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['required', 'string', 'max:8'],
            'status' => ['nullable', 'in:draft,sent'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'discount_type' => ['nullable', 'in:flat,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:discount_type'],
            'attachment' => ['nullable', 'file', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.sub_description' => ['nullable', 'string', 'max:5000'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:60'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.taxes' => ['nullable', 'array'],
            'items.*.taxes.*' => ['integer', 'exists:invoice_taxes,id'],
            'items.*.attachment' => ['nullable', 'file', 'max:5120'],
            'items.*.existing_attachment' => ['nullable', 'string', 'max:255'],
        ], [
            'client_id.required' => 'Please select a client for this invoice.',
        ]);
    }

    private function nextNumber(): string
    {
        return ClientInvoice::nextNumber();
    }
}
