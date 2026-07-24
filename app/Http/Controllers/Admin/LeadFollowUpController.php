<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadFollowUpController extends Controller
{
    /** Schedule a follow-up on a lead (the only place follow-ups are created). */
    public function store(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $data = $this->validated($request);

        $lead->followUps()->create($data + [
            'created_by' => $request->user()->id,
            'status' => LeadFollowUp::STATUS_PENDING,
        ]);
        $lead->syncFollowUpCache();

        return back()->with('status', 'Follow-up scheduled.');
    }

    /**
     * Mark a follow-up done (completion note required). Optionally schedule the next
     * follow-up in the same request — the completed one stays Done, the new one is Pending.
     */
    public function complete(Request $request, Lead $lead, LeadFollowUp $followUp)
    {
        $this->authorizeLead($request, $lead);
        abort_if($followUp->lead_id !== $lead->id, 404);
        abort_unless($request->user()->canAct('follow_ups', 'complete', $followUp), 403);

        $data = $request->validate([
            'completion_note' => ['required', 'string', 'max:2000'],
            'schedule_next' => ['nullable', 'boolean'],
        ]);

        $followUp->update([
            'status' => LeadFollowUp::STATUS_DONE,
            'completion_note' => $data['completion_note'],
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        // Schedule Next Follow-up — appended as a fresh Pending follow-up.
        if ($request->boolean('schedule_next')) {
            $next = $this->validated($request);
            $lead->followUps()->create($next + [
                'created_by' => $request->user()->id,
                'status' => LeadFollowUp::STATUS_PENDING,
            ]);
        }

        $lead->syncFollowUpCache();

        return back()->with('status', $request->boolean('schedule_next')
            ? 'Follow-up completed and the next one scheduled.'
            : 'Follow-up marked done.');
    }

    /** Edit a still-pending follow-up (managers/admin). */
    public function update(Request $request, Lead $lead, LeadFollowUp $followUp)
    {
        $this->authorizeLead($request, $lead);
        abort_if($followUp->lead_id !== $lead->id, 404);
        abort_unless($request->user()->canAct('follow_ups', 'edit', $followUp), 403);
        abort_unless($followUp->isPending(), 422, 'Only pending follow-ups can be edited.');

        $followUp->update($this->validated($request));
        $lead->syncFollowUpCache();

        return back()->with('status', 'Follow-up updated.');
    }

    /** Cancel a pending follow-up (kept in history, never deleted). */
    public function cancel(Request $request, Lead $lead, LeadFollowUp $followUp)
    {
        $this->authorizeLead($request, $lead);
        abort_if($followUp->lead_id !== $lead->id, 404);
        abort_unless($request->user()->canAct('follow_ups', 'edit', $followUp), 403);
        abort_unless($followUp->isPending(), 422, 'Only pending follow-ups can be cancelled.');

        $followUp->update(['status' => LeadFollowUp::STATUS_CANCELLED]);
        $lead->syncFollowUpCache();

        return back()->with('status', 'Follow-up cancelled.');
    }

    /** Hard-delete a pending follow-up (managers/admin; history items stay). */
    public function destroy(Request $request, Lead $lead, LeadFollowUp $followUp)
    {
        $this->authorizeLead($request, $lead);
        abort_if($followUp->lead_id !== $lead->id, 404);
        abort_unless($request->user()->canAct('follow_ups', 'delete', $followUp), 403);
        abort_unless($followUp->isPending(), 422, 'Only pending follow-ups can be deleted.');

        $followUp->delete();
        $lead->syncFollowUpCache();

        return back()->with('status', 'Follow-up deleted.');
    }

    /** Shared validation + normalization for a scheduled follow-up. */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(LeadFollowUp::TYPES))],
            'priority' => ['required', Rule::in(array_keys(LeadFollowUp::PRIORITIES))],
            'user_id' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            'type' => $data['type'],
            'priority' => $data['priority'],
            // Default the assignee to the lead's owner when not chosen explicitly.
            'user_id' => $data['user_id'] ?? $request->route('lead')->assigned_to,
            'scheduled_at' => $data['scheduled_at'],
            'note' => $data['note'] ?? null,
        ];
    }

    /** The user's lead "view" scope must cover this lead to touch its follow-ups. */
    private function authorizeLead(Request $request, Lead $lead): void
    {
        abort_unless($request->user()->canAct('leads', 'view', $lead), 403);
    }
}
