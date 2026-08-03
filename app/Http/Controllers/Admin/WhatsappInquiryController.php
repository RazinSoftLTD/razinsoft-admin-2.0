<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Product;
use App\Models\WhatsappAccount;
use App\Models\WhatsappInquiry;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * WhatsApp traffic, recorded before it becomes a lead.
 *
 * The list and the day's figures are served together: the question this module answers is "what
 * came in today, and on which number", which is a summary and a list of the same rows.
 */
class WhatsappInquiryController extends Controller
{
    public function index(Request $request)
    {
        $day = $request->date('day')?->toDateString() ?: now()->toDateString();

        $q = WhatsappInquiry::query()->with('account:id,name,display_number', 'lead:id,lead_code,full_name', 'addedBy:id,name');
        $request->user()->applyScope($q, 'whatsapp_inquiries', 'view');

        if ($search = trim((string) $request->query('search'))) {
            foreach (preg_split('/\s+/', $search) as $term) {
                $q->where(fn ($w) => $w
                    ->orWhere('client_number', 'like', "%{$term}%")
                    ->orWhere('client_name', 'like', "%{$term}%")
                    ->orWhere('interest', 'like', "%{$term}%")
                    ->orWhere('remarks', 'like', "%{$term}%"));
            }
        }
        if ($account = $request->query('account')) {
            $q->where('whatsapp_account_id', $account);
        }
        // Deliberately tri-state: "no" has to be selectable, or you cannot find the enquiries
        // nobody replied to — which is the whole point of tracking response rate.
        if (($started = $request->query('started')) !== null && $started !== '') {
            $q->where('conversation_started', $started === 'yes');
        }
        if (($relevant = $request->query('relevant')) !== null && $relevant !== '') {
            $q->where('is_relevant', $relevant === 'yes');
        }
        if ($interest = $request->query('interest')) {
            $q->where('interest', $interest);
        }

        // The date filter is a range so a week or a month can be read, defaulting to one day.
        $from = $request->date('from')?->toDateString() ?: $day;
        $to = $request->date('to')?->toDateString() ?: $day;
        // whereDate on both ends rather than whereBetween: SQLite keeps a date column as
        // "2026-08-03 00:00:00", which sorts after the bare "2026-08-03" a between compares
        // against, so a single-day range matched nothing locally while working on MySQL.
        $q->whereDate('inquiry_date', '>=', $from)->whereDate('inquiry_date', '<=', $to);

        $inquiries = $q->orderByDesc('inquiry_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $accounts = WhatsappAccount::orderBy('position')->orderBy('id')->get(['id', 'name', 'display_number']);

        return view('admin.whatsapp-inquiries.index', [
            'inquiries' => $inquiries,
            'accounts' => $accounts,
            'day' => $day,
            'from' => $from,
            'to' => $to,
            // Today's figures regardless of the filter — the header answers "what came in today",
            // and a filtered list should not change what that says.
            'today' => WhatsappInquiry::summaryFor(now()),
            'todayByNumber' => WhatsappInquiry::byNumberFor(now()),
            'rangeSummary' => $this->summaryBetween($from, $to),
            'interests' => WhatsappInquiry::interestsFor($from, $to),
            'interestOptions' => $this->interestOptions(),
        ]);
    }

    /** @return array{total:int, started:int, relevant:int, converted:int} */
    private function summaryBetween(string $from, string $to): array
    {
        $row = WhatsappInquiry::whereDate('inquiry_date', '>=', $from)->whereDate('inquiry_date', '<=', $to)
            ->selectRaw('COUNT(*) AS total, SUM(conversation_started = 1) AS started, SUM(is_relevant = 1) AS relevant, SUM(lead_id IS NOT NULL) AS converted')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'started' => (int) ($row->started ?? 0),
            'relevant' => (int) ($row->relevant ?? 0),
            'converted' => (int) ($row->converted ?? 0),
        ];
    }

    /**
     * What to offer for "interested in".
     *
     * The catalogue, plus anything already typed. Free text alone would make the report useless —
     * "Ready POS", "ready pos" and "POS" would each count separately — but a fixed list would not
     * survive someone asking about something we do not sell yet, so both.
     */
    private function interestOptions(): array
    {
        $products = Product::query()->orderBy('name')->pluck('name')->all();
        $used = WhatsappInquiry::query()->whereNotNull('interest')->where('interest', '!=', '')
            ->distinct()->orderBy('interest')->pluck('interest')->all();

        return collect($products)->merge($used)->unique()->values()->all();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['added_by'] = $request->user()->id;

        WhatsappInquiry::create($data);

        return back()->with('status', 'Enquiry recorded.');
    }

    public function update(Request $request, WhatsappInquiry $whatsappInquiry)
    {
        $whatsappInquiry->update($this->validated($request));

        return back()->with('status', 'Enquiry updated.');
    }

    public function destroy(WhatsappInquiry $whatsappInquiry)
    {
        $whatsappInquiry->delete();

        return back()->with('status', 'Enquiry removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'inquiry_date' => ['required', 'date'],
            'client_number' => ['required', 'string', 'max:32'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'whatsapp_account_id' => ['nullable', Rule::exists('whatsapp_accounts', 'id')],
            'conversation_started' => ['nullable', 'boolean'],
            'is_relevant' => ['nullable', 'boolean'],
            'interest' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        // Stored in one shape so the same person does not appear twice under +8801… and 8801….
        // Phone::normalize returns [dial, number] or null when it cannot parse — an unparseable
        // number is still worth recording, so it goes in as typed rather than being refused.
        if ($parsed = Phone::normalize($data['client_number'])) {
            $data['client_number'] = $parsed['dial'].$parsed['number'];
        }
        $data['conversation_started'] = $request->boolean('conversation_started');
        $data['is_relevant'] = $request->boolean('is_relevant');
        // Keep a copy of the number that was contacted, so the row still reads if the account goes.
        $data['company_number'] = WhatsappAccount::find($data['whatsapp_account_id'] ?? null)?->display_number;

        return $data;
    }

    /**
     * Promote an enquiry into the Leads module.
     *
     * Carries over what was already collected rather than asking for it again, and records the new
     * lead's id so the enquiry is never promoted twice or counted as still open.
     */
    public function convert(Request $request, WhatsappInquiry $whatsappInquiry)
    {
        if ($whatsappInquiry->isConverted()) {
            return back()->with('status', 'This enquiry is already a lead.');
        }

        // The same person may have written to two of our numbers. Attach to the lead that already
        // exists rather than making a second one.
        $existing = Lead::where('phone', Phone::normalize($whatsappInquiry->client_number)['number'] ?? $whatsappInquiry->client_number)->first();
        if ($existing) {
            $whatsappInquiry->update(['lead_id' => $existing->id, 'converted_at' => now()]);

            return redirect()->route('admin.leads.show', $existing)->with('status', 'Linked to the existing lead for this number.');
        }

        // Same split the chat-to-lead conversion uses, so a lead made either way looks the same.
        $parsed = Phone::normalize($whatsappInquiry->client_number);

        $lead = Lead::create([
            'full_name' => $whatsappInquiry->client_name ?: $whatsappInquiry->client_number,
            'phone' => $parsed['number'] ?? $whatsappInquiry->client_number,
            'dial_code' => $parsed['dial'] ?? null,
            'is_whatsapp' => true,
            'lead_source' => 'WhatsApp',
            // Not "qualified": that would tell Meta this lead is worth money before anyone has
            // looked at it. "new" is the unjudged state, and the observer stays quiet for it.
            'lead_status' => 'new',
            'notes' => collect([
                $whatsappInquiry->interest ? 'Interested in: '.$whatsappInquiry->interest : null,
                $whatsappInquiry->remarks,
                'Came in on '.$whatsappInquiry->companyNumberLabel().' on '.$whatsappInquiry->inquiry_date->format('d M Y').'.',
            ])->filter()->implode("\n"),
            'added_by' => $request->user()->id,
            'assigned_to' => $request->user()->id,
        ]);

        $whatsappInquiry->update(['lead_id' => $lead->id, 'converted_at' => now()]);

        return redirect()->route('admin.leads.show', $lead)->with('status', 'Enquiry converted to a lead.');
    }
}
