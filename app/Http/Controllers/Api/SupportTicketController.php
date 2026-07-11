<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    /** List the signed-in customer's tickets (optionally filtered by status). */
    public function index(Request $request)
    {
        $q = $request->user()->tickets()->withCount('replies');
        if (array_key_exists($request->query('status'), Ticket::STATUSES)) {
            $q->where('status', $request->query('status'));
        }

        return response()->json([
            'data' => $q->get()->map(fn ($t) => $this->summary($t)),
            'counts' => [
                'all' => $request->user()->tickets()->count(),
                'open' => $request->user()->tickets()->where('status', 'open')->count(),
                'pending' => $request->user()->tickets()->where('status', 'pending')->count(),
                'resolved' => $request->user()->tickets()->where('status', 'resolved')->count(),
                'closed' => $request->user()->tickets()->where('status', 'closed')->count(),
            ],
            'unread' => $request->user()->tickets()->where('unread_by_customer', true)->count(),
            'categories' => Ticket::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'message' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $ticket = $request->user()->tickets()->create([
            'ticket_number' => Ticket::nextNumber(),
            'subject' => $data['subject'],
            'category' => $data['category'],
            'message' => clean($data['message']), // sanitize customer HTML (XSS-safe for the admin panel)
            'status' => 'open',
            'last_reply_at' => now(),
            'attachment' => $request->hasFile('attachment') ? $request->file('attachment')->store('tickets', 'public') : null,
        ]);
        \App\Events\TicketUnreadBroadcast::dispatch('tickets.admin', Ticket::where('unread_by_admin', true)->count());

        return response()->json($this->detail($ticket), 201);
    }

    public function show(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->client_id === $request->user()->id, 404);

        // Customer opened it — mark seen (admin's "Seen" indicator) and clear their unread flag.
        $ticket->forceFill(['customer_seen_at' => now(), 'unread_by_customer' => false])->save();

        return response()->json($this->detail($ticket->load('replies.author')));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->client_id === $request->user()->id, 404);
        abort_if($ticket->status === 'closed', 422, 'This ticket is closed.');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $reply = $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'is_admin' => false,
            'message' => clean($data['message']), // sanitize customer HTML
            'attachment' => $request->hasFile('attachment') ? $request->file('attachment')->store('tickets/'.$ticket->id, 'public') : null,
        ]);
        // The support team now has a new (unread) message.
        $ticket->update(['last_reply_at' => now(), 'unread_by_admin' => true]);
        \App\Events\TicketReplyPosted::dispatch($reply);
        \App\Events\TicketUnreadBroadcast::dispatch('tickets.admin', Ticket::where('unread_by_admin', true)->count());

        return response()->json($this->detail($ticket->load('replies.author')));
    }

    private function summary(Ticket $t): array
    {
        return [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'category' => $t->category,
            'category_label' => $t->categoryLabel(),
            'status' => $t->status,
            'status_label' => $t->statusLabel(),
            'unread' => (bool) $t->unread_by_customer,
            'replies_count' => $t->replies_count ?? $t->replies()->count(),
            'updated_at' => ($t->last_reply_at ?? $t->created_at)?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    private function detail(Ticket $t): array
    {
        return array_merge($this->summary($t), [
            'message' => $t->message,
            'attachment' => $t->attachment ? asset('storage/'.$t->attachment) : null,
            'replies' => $t->replies->map(fn ($r) => [
                'id' => $r->id,
                'message' => $r->message,
                'is_admin' => $r->is_admin,
                'author' => $r->author->name ?? ($r->is_admin ? 'Support' : 'You'),
                'attachment' => $r->attachment ? asset('storage/'.$r->attachment) : null,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
