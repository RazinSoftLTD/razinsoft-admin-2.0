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
        ]);
    }

    // ---- Agents ----
    public function storeAgent(Request $request)
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id', Rule::unique('ticket_agents', 'user_id')]]);
        TicketAgent::create(['user_id' => $data['user_id'], 'status' => 'enabled']);

        return back()->with('status', 'Agent added.');
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
            'agent_ids' => ['sometimes', 'array'],
            'agent_ids.*' => ['exists:ticket_agents,id'],
        ]);
        if (array_key_exists('name', $data)) {
            $type->update(['name' => $data['name']]);
        }
        // sync_agents marks a submit from the agent picker, so unchecking the last agent
        // (no agent_ids key at all) still syncs to an empty set instead of being ignored.
        if ($request->has('agent_ids') || $request->boolean('sync_agents')) {
            $type->agents()->sync($data['agent_ids'] ?? []);
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
