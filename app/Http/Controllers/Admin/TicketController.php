<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $q = $this->filtered($request);

        return view('admin.tickets.index', [
            'tickets' => $q->paginate((int) $request->query('per_page', 10) ?: 10)->withQueryString(),
            'status' => $request->query('status', ''),
            'search' => trim((string) $request->query('search')),
            'priority' => $request->query('priority', ''),
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
            'counts' => [
                'all' => Ticket::count(),
                'open' => Ticket::where('status', 'open')->count(),
                'pending' => Ticket::where('status', 'pending')->count(),
                'resolved' => Ticket::where('status', 'resolved')->count(),
                'closed' => Ticket::where('status', 'closed')->count(),
            ],
        ]);
    }

    /** Shared list query with status / priority / search / date-range filters. */
    private function filtered(Request $request)
    {
        $q = Ticket::with('client')
            ->withCount('replies')
            ->withCount(['replies as admin_replies_count' => fn ($r) => $r->where('is_admin', true)])
            ->latest('last_reply_at')->latest('id');

        if (array_key_exists($request->query('status'), Ticket::STATUSES)) {
            $q->where('status', $request->query('status'));
        }
        if (array_key_exists($request->query('priority'), Ticket::PRIORITIES)) {
            $q->where('priority', $request->query('priority'));
        }
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }
        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('subject', 'like', "%{$search}%")
                ->orWhere('ticket_number', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }

        return $q;
    }

    /** Download the (filtered) ticket list as CSV. */
    public function export(Request $request)
    {
        $rows = $this->filtered($request)->get();
        $filename = 'tickets-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ticket #', 'Subject', 'Requester', 'Email', 'Category', 'Priority', 'Status', 'Replies', 'Requested On']);
            foreach ($rows as $t) {
                fputcsv($out, [$t->ticket_number, $t->subject, $t->client->name ?? '', $t->client->email ?? '', $t->categoryLabel(), $t->priorityLabel(), $t->statusLabel(), $t->replies_count, $t->created_at?->format('d M Y H:i')]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create()
    {
        return view('admin.tickets.form', [
            'clients' => \App\Models\User::clients()->orderBy('name')->get(['id', 'name', 'company']),
            'employees' => \App\Models\User::assignable()->orderBy('name')->get(['id', 'name']),
            'agents' => \App\Models\User::assignable()->orderBy('name')->get(['id', 'name']),
            'groups' => \App\Models\TicketGroup::orderBy('name')->get(),
            'types' => \App\Models\TicketType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'requester_type' => ['required', Rule::in(['client', 'employee'])],
            'client_id' => ['required', 'exists:users,id'],
            'group_id' => ['nullable', 'exists:ticket_groups,id'],
            'type_id' => ['nullable', 'exists:ticket_types,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'message' => ['required', 'string', 'max:20000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $ticket = Ticket::create([
            'ticket_number' => Ticket::nextNumber(),
            'client_id' => $data['client_id'],
            'requester_type' => $data['requester_type'],
            'subject' => $data['subject'],
            'category' => 'other',
            'group_id' => $data['group_id'] ?? null,
            'type_id' => $data['type_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'priority' => $data['priority'],
            'message' => clean($data['message']),
            'status' => 'open',
            'last_reply_at' => now(),
            'unread_by_admin' => false,   // admin created it
            'unread_by_customer' => true, // notify the customer
            'attachment' => $request->hasFile('attachment') ? $request->file('attachment')->store('tickets', 'public') : null,
        ]);
        \App\Events\TicketUnreadBroadcast::dispatch('tickets.customer.'.$ticket->client_id, Ticket::where('client_id', $ticket->client_id)->where('unread_by_customer', true)->count());

        return redirect()->route('admin.tickets.show', $ticket)->with('status', 'Ticket created.');
    }

    /** Quick-add an Assign Group (from the form). Returns JSON. */
    public function storeGroup(Request $request)
    {
        $v = validator($request->all(), ['name' => ['required', 'string', 'max:120', Rule::unique('ticket_groups', 'name')]]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $g = \App\Models\TicketGroup::create($v->validated());

        return response()->json(['id' => $g->id, 'name' => $g->name]);
    }

    /** Quick-add a ticket Type. Returns JSON. */
    public function storeType(Request $request)
    {
        $v = validator($request->all(), ['name' => ['required', 'string', 'max:120', Rule::unique('ticket_types', 'name')]]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $t = \App\Models\TicketType::create($v->validated());

        return response()->json(['id' => $t->id, 'name' => $t->name]);
    }

    public function show(Ticket $ticket)
    {
        // Admin opened it — clear the "new message" flag for staff.
        if ($ticket->unread_by_admin) {
            $ticket->forceFill(['unread_by_admin' => false])->save();
        }

        $ticket->load('client', 'assignee', 'replies.author');

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'team' => \App\Models\User::assignable()->orderBy('name')->get(['id', 'name', 'role']),
            'templates' => \App\Models\ReplyTemplate::orderBy('title')->get(['id', 'title', 'body']),
        ]);
    }

    /** Assign the ticket to a team member (staff/admin) or unassign. */
    public function assign(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);
        $ticket->update(['assigned_to' => $data['assigned_to'] ?: null]);

        return back()->with('status', 'Ticket assignment updated.');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
        ]);

        $reply = $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'is_admin' => true,
            'message' => $data['message'],
            'attachment' => $request->hasFile('attachment') ? $request->file('attachment')->store('tickets/'.$ticket->id, 'public') : null,
        ]);
        \App\Events\TicketReplyPosted::dispatch($reply);

        // Replying moves an open ticket to pending unless the admin set a status explicitly.
        // The customer now has a new (unread) message.
        $ticket->update([
            'status' => $data['status'] ?? ($ticket->status === 'open' ? 'pending' : $ticket->status),
            'last_reply_at' => now(),
            'unread_by_customer' => true,
        ]);
        \App\Events\TicketUnreadBroadcast::dispatch('tickets.customer.'.$ticket->client_id, Ticket::where('client_id', $ticket->client_id)->where('unread_by_customer', true)->count());

        // Jump back to the newest message; no flash banner needed for a chat reply.
        return redirect(route('admin.tickets.show', $ticket).'#bottom');
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Ticket::PRIORITIES))],
        ]);
        $ticket->update(array_filter([
            'status' => $data['status'] ?? null,
            'priority' => $data['priority'] ?? null,
        ]));

        return back()->with('status', 'Ticket updated.');
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        // Only a super admin may delete tickets.
        abort_unless($request->user()->isAdmin(), 403);

        Storage::disk('public')->deleteDirectory('tickets/'.$ticket->id);
        $ticket->delete();

        return redirect()->route('admin.tickets.index')->with('status', 'Ticket deleted.');
    }
}
