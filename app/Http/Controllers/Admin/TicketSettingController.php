<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReplyTemplate;
use App\Models\TicketAgent;
use App\Models\TicketGroup;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketSettingController extends Controller
{
    public function index(Request $request)
    {
        $agentUserIds = TicketAgent::pluck('user_id');

        return view('admin.tickets.settings', [
            'tab' => $request->query('tab', 'agents'),
            'agents' => TicketAgent::with('user.designation', 'groups')->get(),
            'groups' => TicketGroup::orderBy('name')->get(),
            'types' => TicketType::with('agents.user')->orderBy('name')->get(),
            'templates' => ReplyTemplate::latest()->get(),
            'addableEmployees' => User::assignable()->whereNotIn('id', $agentUserIds)->orderBy('name')->get(['id', 'name']),
            // Every employee can be picked per category; the agent record is created on demand.
            'assignableEmployees' => User::assignable()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ---- Agents ----
    /** Add one or more employees as agents in a single submit. */
    public function storeAgent(Request $request)
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // firstOrCreate, not create: the unique index would otherwise reject the whole batch
        // if one of the picked employees was made an agent in another tab meanwhile.
        $added = 0;
        foreach (array_unique($data['user_ids']) as $id) {
            $agent = TicketAgent::firstOrCreate(['user_id' => $id], ['status' => 'enabled']);
            $added += $agent->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('status', $added === 1 ? 'Agent added.' : "{$added} agents added.");
    }

    public function updateAgent(Request $request, TicketAgent $agent)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['enabled', 'disabled'])],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['exists:ticket_groups,id'],
        ]);
        if (isset($data['status'])) {
            $agent->update(['status' => $data['status']]);
        }
        // sync_groups marks a submit from the group picker, so unchecking the last group
        // (no group_ids key at all) still syncs to an empty set instead of being ignored.
        if ($request->has('group_ids') || $request->boolean('sync_groups')) {
            $agent->groups()->sync($data['group_ids'] ?? []);
        }

        return back()->with('status', 'Agent updated.');
    }

    public function destroyAgent(TicketAgent $agent)
    {
        $agent->delete();

        return back()->with('status', 'Agent removed.');
    }

    // ---- Types ----
    public function storeType(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('ticket_types', 'name')]]);
        TicketType::create($data);

        return back()->with('status', 'Ticket type added.');
    }

    public function updateType(Request $request, TicketType $type)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('ticket_types', 'name')->ignore($type->id)],
            // user ids, not ticket_agent ids: the picker lists every employee.
            'agent_ids' => ['sometimes', 'array'],
            'agent_ids.*' => ['integer', 'exists:users,id'],
        ]);
        if (array_key_exists('name', $data)) {
            $type->update(['name' => $data['name']]);
        }
        // sync_agents marks a submit from the agent picker, so unchecking the last agent
        // (no agent_ids key at all) still syncs to an empty set instead of being ignored.
        if ($request->has('agent_ids') || $request->boolean('sync_agents')) {
            // Picking someone who has never handled a ticket makes them an agent here, so the
            // category picker is not gated behind a separate trip to the Ticket Agents tab.
            $agentIds = collect($data['agent_ids'] ?? [])->unique()
                ->map(fn ($userId) => TicketAgent::firstOrCreate(['user_id' => $userId], ['status' => 'enabled'])->id)
                ->all();
            $type->agents()->sync($agentIds);
        }

        return back()->with('status', 'Ticket type updated.');
    }

    public function destroyType(TicketType $type)
    {
        $type->delete();

        return back()->with('status', 'Ticket type deleted.');
    }

    // ---- Reply templates ----
    public function storeTemplate(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:20000']]);
        ReplyTemplate::create(['title' => $data['title'], 'body' => clean($data['body'])]);

        return back()->with('status', 'Reply template added.');
    }

    public function updateTemplate(Request $request, ReplyTemplate $template)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:20000']]);
        $template->update(['title' => $data['title'], 'body' => clean($data['body'])]);

        return back()->with('status', 'Reply template updated.');
    }

    public function destroyTemplate(ReplyTemplate $template)
    {
        $template->delete();

        return back()->with('status', 'Reply template deleted.');
    }
}
