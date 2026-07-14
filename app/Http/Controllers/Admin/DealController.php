<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\DealAttachment;
use App\Models\DealFollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $base = fn () => Deal::query()->with('client:id,name', 'assignee:id,name', 'lead:id,full_name')
            ->when(! $request->user()->seesAll('deals'), fn ($q) => $q->where('assigned_to', $request->user()->id));

        $view = $request->query('view') === 'list' ? 'list' : 'board';
        $all = $base()->latest('id')->get();

        return view('admin.deals.index', [
            'view' => $view,
            'byStage' => $all->groupBy('stage'),
            'deals' => $view === 'list' ? $base()->latest('id')->paginate(20)->withQueryString() : null,
        ]);
    }

    public function show(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $deal->load('client', 'lead', 'assignee', 'followUps.user:id,name', 'attachments.user:id,name');

        return view('admin.deals.show', ['deal' => $deal]);
    }

    public function create(Request $request)
    {
        $lead = $request->filled('lead') ? Lead::find($request->query('lead')) : null;

        return view('admin.deals.form', [
            'deal' => new Deal([
                'stage' => 'new', 'currency' => 'BDT', 'priority' => 'medium',
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
        $deal = Deal::create($this->stamped($this->validated($request)));

        return redirect()->route('admin.deals.show', $deal)->with('status', 'Deal created.');
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
        $deal->update($this->stamped($this->validated($request), $deal));

        return redirect()->route('admin.deals.show', $deal)->with('status', 'Deal updated.');
    }

    /** Drag-and-drop / quick stage change. Returns JSON for the board, redirect otherwise. */
    public function stage(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $data = $request->validate(['stage' => ['required', Rule::in(array_keys(Deal::stages()))]]);

        if ($data['stage'] !== $deal->stage) {
            $from = Deal::stages()[$deal->stage] ?? $deal->stage;
            $deal->stage = $data['stage'];
            $deal->probability = Deal::STAGE_PROBABILITY[$data['stage']] ?? null;
            $deal->won_at = $data['stage'] === 'won' ? now() : null;
            $deal->lost_at = $data['stage'] === 'lost' ? now() : null;
            $deal->save();

            DealActivity::create([
                'deal_id' => $deal->id, 'user_id' => $request->user()->id, 'type' => 'stage',
                'body' => "Moved from {$from} to ".(Deal::stages()[$deal->stage] ?? $deal->stage).'.',
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'stage' => $deal->stage, 'probability' => $deal->effective_probability]);
        }

        return back()->with('status', "Deal moved to {$deal->stage}.");
    }

    /** Log a note / call / meeting / email on the deal timeline. */
    public function activity(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DealActivity::TYPES))],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $deal->activities()->create(['user_id' => $request->user()->id, 'type' => $data['type'], 'body' => $data['body']]);

        return back()->with('status', 'Activity logged.');
    }

    /** Schedule a follow-up (title, note, date+time) — appended to the deal's follow-up history. */
    public function followUp(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $data = $request->validate([
            'next_follow_up_at' => ['required', 'date'],
            'follow_up_title' => ['nullable', 'string', 'max:255'],
            'follow_up_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $deal->followUps()->create([
            'user_id' => $request->user()->id,
            'title' => $data['follow_up_title'] ?? null,
            'note' => $data['follow_up_note'] ?? null,
            'due_at' => $data['next_follow_up_at'],
        ]);
        $deal->syncNextFollowUp();

        return back()->with('status', 'Follow-up scheduled.');
    }

    /** Toggle a follow-up done / pending. */
    public function followUpComplete(Request $request, Deal $deal, DealFollowUp $followUp)
    {
        $this->authorizeDeal($request, $deal);
        abort_if($followUp->deal_id !== $deal->id, 404);

        $followUp->update(['completed_at' => $followUp->completed_at ? null : now()]);
        $deal->syncNextFollowUp();

        return back()->with('status', $followUp->completed_at ? 'Follow-up marked done.' : 'Follow-up reopened.');
    }

    /** Remove a follow-up from the history. */
    public function followUpDestroy(Request $request, Deal $deal, DealFollowUp $followUp)
    {
        $this->authorizeDeal($request, $deal);
        abort_if($followUp->deal_id !== $deal->id, 404);

        $followUp->delete();
        $deal->syncNextFollowUp();

        return back()->with('status', 'Follow-up removed.');
    }

    /** Update the deal description (inline from the detail page). */
    public function description(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:5000']]);
        $deal->update(['notes' => $data['notes'] ?? null]);

        return back()->with('status', 'Description saved.');
    }

    /** Attach a file (image / document) to the deal. */
    public function attachmentStore(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,csv'],
        ]);

        $file = $request->file('file');
        $deal->attachments()->create([
            'user_id' => $request->user()->id,
            'path' => $file->store('deals', 'public'),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);

        return back()->with('status', 'Attachment uploaded.');
    }

    /** Delete a deal attachment (file + row). */
    public function attachmentDestroy(Request $request, Deal $deal, DealAttachment $attachment)
    {
        $this->authorizeDeal($request, $deal);
        abort_if($attachment->deal_id !== $deal->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }

    public function destroy(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);
        $deal->delete();

        return redirect()->route('admin.deals.index')->with('status', 'Deal deleted.');
    }

    /** Create a draft invoice from a won deal, pre-filled with one line for the deal value. */
    public function invoice(Request $request, Deal $deal)
    {
        $this->authorizeDeal($request, $deal);

        // An invoice needs a client to bill — a lead-only deal can't be invoiced.
        if (! $deal->canInvoice()) {
            return back()->with('error', 'Link a client to this won deal before creating an invoice.');
        }

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
            'project_type' => ['nullable', Rule::in(Deal::PROJECT_TYPES)],
            'client_id' => ['nullable', 'exists:users,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'stage' => ['required', Rule::in(array_keys(Deal::stages()))],
            'priority' => ['required', Rule::in(array_keys(Deal::PRIORITIES))],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** Keep won_at / lost_at in sync with the chosen stage. */
    private function stamped(array $data, ?Deal $deal = null): array
    {
        $data['won_at'] = $data['stage'] === 'won' ? ($deal?->won_at ?? now()) : null;
        $data['lost_at'] = $data['stage'] === 'lost' ? ($deal?->lost_at ?? now()) : null;

        return $data;
    }

    private function authorizeDeal(Request $request, Deal $deal): void
    {
        abort_if(! $request->user()->seesAll('deals') && $deal->assigned_to !== $request->user()->id, 403);
    }
}
