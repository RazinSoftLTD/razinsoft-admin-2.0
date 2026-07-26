<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailCampaign;
use App\Models\EmailCampaign;
use App\Models\EmailConfig;
use App\Models\EmailTemplate;
use App\Services\Email\CampaignAudience;
use App\Services\Email\EmailBodyBuilder;
use App\Services\Email\EmailDispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Email Settings → Manual Email. One message to many people. */
class EmailCampaignController extends Controller
{
    public function index(Request $request)
    {
        $this->can($request);

        return view('admin.email.campaigns.index', [
            'campaigns' => EmailCampaign::with('creator:id,name')->latest('id')->paginate(20),
        ]);
    }

    public function create(Request $request, CampaignAudience $audience)
    {
        $this->can($request);

        return view('admin.email.campaigns.form', [
            'campaign' => new EmailCampaign(['status' => 'draft']),
            'templates' => EmailTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'category']),
            'configs' => EmailConfig::usable()->pluck('name', 'id'),
            'audienceTypes' => CampaignAudience::TYPES,
            'audienceOptions' => $audience->options(),
            'totalClients' => $audience->count(['type' => 'all']),
        ]);
    }

    public function edit(Request $request, EmailCampaign $campaign, CampaignAudience $audience)
    {
        $this->can($request);

        abort_unless($campaign->isEditable(), 403, 'A campaign that has started cannot be edited.');

        return view('admin.email.campaigns.form', [
            'campaign' => $campaign,
            'templates' => EmailTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'category']),
            'configs' => EmailConfig::usable()->pluck('name', 'id'),
            'audienceTypes' => CampaignAudience::TYPES,
            'audienceOptions' => $audience->options(),
            'totalClients' => $audience->count(['type' => 'all']),
        ]);
    }

    public function store(Request $request, CampaignAudience $audience)
    {
        $this->can($request);

        $campaign = EmailCampaign::create($this->validated($request) + [
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return $this->afterSave($request, $campaign, $audience);
    }

    public function update(Request $request, EmailCampaign $campaign, CampaignAudience $audience)
    {
        $this->can($request);

        abort_unless($campaign->isEditable(), 403);

        $campaign->update($this->validated($request));

        return $this->afterSave($request, $campaign, $audience);
    }

    /** Stop a campaign that has not finished; anything already queued still goes. */
    public function cancel(Request $request, EmailCampaign $campaign)
    {
        $this->can($request);

        abort_if($campaign->status === 'sent', 400);

        $campaign->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();

        return back()->with('status', 'Campaign cancelled. Anything already queued will still be delivered.');
    }

    public function show(Request $request, EmailCampaign $campaign)
    {
        $this->can($request);

        return view('admin.email.campaigns.show', [
            'campaign' => $campaign->load('template:id,name', 'config:id,name', 'creator:id,name'),
            'progress' => $campaign->progress(),
            'recipients' => $campaign->recipients()->with('log:id,status,first_opened_at')
                ->latest('id')->paginate(25),
        ]);
    }

    public function destroy(Request $request, EmailCampaign $campaign)
    {
        $this->can($request);

        abort_if($campaign->status === 'sending', 400, 'Cancel the campaign before deleting it.');

        $campaign->delete();

        return redirect()->route('admin.email.campaigns')->with('status', 'Campaign deleted.');
    }

    /** How many people the chosen filter reaches — asked by the form as it is filled in. */
    public function audienceCount(Request $request, CampaignAudience $audience)
    {
        $this->can($request);

        return response()->json([
            'count' => $audience->count([
                'type' => $request->query('type', 'all'),
                'values' => array_filter((array) $request->query('values', [])),
            ]),
        ]);
    }

    /** A test copy to one address, before it goes to everyone. */
    public function sendTest(Request $request, EmailCampaign $campaign, EmailDispatcher $dispatcher)
    {
        $this->can($request);

        $data = $request->validate(['to' => ['required', 'email']]);

        $log = $dispatcher->send($data['to'], '[Test] '.$campaign->subject, (string) $campaign->body_html,
            $campaign->body_text, ['config_id' => $campaign->email_config_id, 'module' => 'campaign', 'dedupe' => false]);

        return back()->with($log ? 'status' : 'error',
            $log ? "Test queued to {$data['to']}." : 'Could not queue the test.');
    }

    // ---------------------------------------------------------------- internals

    /** Save, then either leave it as a draft, schedule it, or start it now. */
    private function afterSave(Request $request, EmailCampaign $campaign, CampaignAudience $audience)
    {
        $action = $request->input('action', 'draft');
        $count = $audience->count($campaign->audience ?? ['type' => 'all']);

        if ($count === 0 && $action !== 'draft') {
            return back()->withInput()->with('error', 'That audience matches nobody — nothing was sent.');
        }

        if ($action === 'schedule' && $campaign->scheduled_at) {
            $campaign->forceFill(['status' => 'scheduled', 'total_recipients' => $count])->save();
            SendEmailCampaign::dispatch($campaign->id)->delay($campaign->scheduled_at);

            return redirect()->route('admin.email.campaigns.show', $campaign)
                ->with('status', "Scheduled for {$campaign->scheduled_at->format('d M Y, g:i A')} — {$count} recipient(s).");
        }

        if ($action === 'send') {
            $campaign->forceFill(['status' => 'scheduled', 'scheduled_at' => null, 'total_recipients' => $count])->save();
            SendEmailCampaign::dispatch($campaign->id);

            return redirect()->route('admin.email.campaigns.show', $campaign)
                ->with('status', "Sending to {$count} recipient(s) — messages are queued in batches.");
        }

        return redirect()->route('admin.email.campaigns.edit', $campaign)->with('status', 'Saved as a draft.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:190'],
            'body_html' => ['required', 'string', 'max:120000'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'email_config_id' => ['nullable', 'exists:email_configs,id'],
            'audience_type' => ['required', Rule::in(array_keys(CampaignAudience::TYPES))],
            'audience_values' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        // Admin-authored HTML, sanitised like everywhere else in the panel.
        $html = clean($data['body_html']);

        return [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body_html' => $html,
            'body_text' => EmailBodyBuilder::toPlainText($html),
            'email_template_id' => $data['email_template_id'] ?? null,
            'email_config_id' => $data['email_config_id'] ?? null,
            'audience' => [
                'type' => $data['audience_type'],
                'values' => array_values(array_filter((array) ($data['audience_values'] ?? []))),
            ],
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ];
    }

    private function can(Request $request): void
    {
        abort_unless($request->user()->hasPermission('email.send'), 403);
    }
}
