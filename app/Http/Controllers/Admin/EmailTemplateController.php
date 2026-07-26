<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\Email\EmailBodyBuilder;
use App\Services\Email\EmailDispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Email Settings → Email Templates. */
class EmailTemplateController extends Controller
{
    public function index(Request $request)
    {
        $this->can($request, 'templates');

        $templates = EmailTemplate::orderBy('category')->orderBy('name')->get();

        return view('admin.email.templates.index', [
            'grouped' => $templates->groupBy('category'),
            'total' => $templates->count(),
        ]);
    }

    public function edit(Request $request, EmailTemplate $template)
    {
        $this->can($request, 'templates');

        return view('admin.email.templates.edit', [
            'template' => $template,
            'variables' => $this->variablesFor($template),
        ]);
    }

    public function update(Request $request, EmailTemplate $template)
    {
        $this->can($request, 'templates');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(EmailTemplate::CATEGORIES))],
            'subject' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:120000'],
            'body_text' => ['nullable', 'string', 'max:60000'],
            'description' => ['nullable', 'string', 'max:500'],
            'variables' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // The body is admin-authored HTML that must survive intact (inline styles, tables), but it
        // still goes through the sanitiser the rest of the panel uses so a pasted <script> cannot
        // ride along into someone's inbox.
        $data['body'] = clean($data['body']);

        // An empty text part is a spam signal, so it is regenerated rather than left blank.
        $data['body_text'] = filled($data['body_text'] ?? null)
            ? $data['body_text']
            : EmailBodyBuilder::toPlainText($data['body']);

        $data['is_active'] = $request->boolean('is_active');

        $template->update($data);

        return back()->with('status', "Template “{$template->name}” saved.");
    }

    public function store(Request $request)
    {
        $this->can($request, 'templates');

        $data = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'unique:email_templates,key'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(EmailTemplate::CATEGORIES))],
            'subject' => ['required', 'string', 'max:190'],
        ], [
            'key.regex' => 'The key may only contain lowercase letters, numbers and underscores.',
        ]);

        $body = \App\Services\Email\DefaultTemplates::wrap('<p>Hi {{customer_name}},</p><p>Write your message here.</p>');

        $template = EmailTemplate::create($data + [
            'body' => $body,
            'body_text' => EmailBodyBuilder::toPlainText($body),
            'is_active' => true,
            'is_system' => false,
        ]);

        return redirect()->route('admin.email.templates.edit', $template)
            ->with('status', 'Template created — write the content and save.');
    }

    public function destroy(Request $request, EmailTemplate $template)
    {
        $this->can($request, 'templates');

        // The shipped ones are wired to real events; deleting one silently stops those emails.
        if ($template->is_system) {
            return back()->with('error', 'This is a built-in template. Turn it off instead of deleting it.');
        }

        $template->delete();

        return redirect()->route('admin.email.templates')->with('status', 'Template deleted.');
    }

    /**
     * Flip a template on or off from a list, without opening it.
     *
     * Its own endpoint rather than a trip through update(): that would need the whole body posted
     * back, and a half-filled form would overwrite the content.
     */
    public function toggle(Request $request, EmailTemplate $template)
    {
        $this->can($request, 'templates');

        $template->update(['is_active' => ! $template->is_active]);

        $state = $template->is_active ? 'on' : 'off';

        if ($request->expectsJson()) {
            return response()->json(['is_active' => $template->is_active, 'message' => "“{$template->name}” turned {$state}."]);
        }

        return back()->with('status', "“{$template->name}” turned {$state}.");
    }

    /** Rendered preview, filled with example values so it reads like a real message. */
    public function preview(Request $request, EmailTemplate $template)
    {
        $this->can($request, 'templates');

        $rendered = $template->renderFor($this->sampleData($template));

        return $request->boolean('text')
            ? response($rendered['text'], 200, ['Content-Type' => 'text/plain; charset=utf-8'])
            : response($rendered['html'])->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** Queue a real copy of this template so it can be checked in an actual inbox. */
    public function sendTest(Request $request, EmailTemplate $template, EmailDispatcher $dispatcher)
    {
        $this->can($request, 'templates');

        $data = $request->validate(['to' => ['required', 'email']]);
        $rendered = $template->renderFor($this->sampleData($template));

        $log = $dispatcher->send($data['to'], '[Test] '.$rendered['subject'], $rendered['html'], $rendered['text'], [
            'template_id' => $template->id,
            'module' => 'email',
            'dedupe' => false,
        ]);

        return back()->with(
            $log ? 'status' : 'error',
            $log
                ? "Test queued to {$data['to']} — it sends on the next queue run."
                : 'Could not queue the test. The address may be suppressed, or no SMTP account is active.',
        );
    }

    /** Every variable this template can use: the global ones plus whatever it declares. */
    private function variablesFor(EmailTemplate $template): array
    {
        $declared = collect(explode(',', (string) $template->variables))
            ->map(fn ($v) => trim($v))->filter()->values();

        return [
            'global' => EmailTemplate::GLOBAL_VARIABLES,
            'declared' => $declared->all(),
            'used' => $template->usedVariables(),
        ];
    }

    /** Believable stand-ins, so a preview shows layout problems rather than empty gaps. */
    private function sampleData(EmailTemplate $template): array
    {
        $samples = [
            'customer_name' => 'Rahim Uddin',
            'invoice_number' => 'INV-2026-014',
            'invoice_total' => 'BDT 85,000',
            'amount_paid' => 'BDT 85,000',
            'refund_amount' => 'BDT 12,000',
            'due_date' => now()->addDays(14)->format('d M Y'),
            'invoice_url' => config('app.url').'/invoice/pay/example',
            'order_number' => 'ORD-2026-231',
            'order_total' => 'BDT 42,000',
            'order_url' => config('app.url').'/orders/example',
            'product_name' => 'Ready eCommerce',
            'license_key' => 'RS-XXXX-XXXX-XXXX-XXXX',
            'download_url' => config('app.url').'/downloads/example',
            'plan_name' => 'Business',
            'renews_on' => now()->addYear()->format('d M Y'),
            'end_date' => now()->addDays(3)->format('d M Y'),
            'renew_url' => config('app.url').'/billing',
            'upgrade_url' => config('app.url').'/billing',
            'ticket_number' => 'TKT-1043',
            'ticket_subject' => 'Payment gateway not responding',
            'ticket_url' => config('app.url').'/support/1043',
            'reply_body' => 'We have reproduced the problem and a fix is going out today.',
            'project_name' => 'Website Redesign',
            'project_url' => config('app.url').'/projects/example',
            'update_note' => 'The design phase is complete and development has started.',
            'meeting_date' => now()->addDays(2)->format('d M Y'),
            'meeting_time' => '3:00 PM',
            'meeting_link' => 'https://meet.google.com/example',
            'verification_url' => config('app.url').'/verify/example',
            'reset_url' => config('app.url').'/reset/example',
            'newsletter_subject' => 'What we shipped this month',
            'newsletter_body' => '<p>This is where the newsletter content goes.</p>',
            'campaign_subject' => 'A note from our team',
            'campaign_body' => '<p>This is where the campaign content goes.</p>',
            'maintenance_date' => now()->addDays(5)->format('d M Y'),
            'maintenance_window' => '11:00 PM – 1:00 AM',
            'maintenance_note' => 'No action is needed from you.',
        ];

        // Anything the template uses that has no sample gets a readable placeholder rather than
        // being blanked, so a missing variable is visible in the preview. The globals are skipped:
        // renderFor() supplies real values for those, and a placeholder here would hide them.
        $globals = array_keys(EmailTemplate::GLOBAL_VARIABLES);

        foreach ($template->usedVariables() as $name) {
            if (! in_array($name, $globals, true)) {
                $samples[$name] ??= '['.str_replace('_', ' ', $name).']';
            }
        }

        return $samples;
    }

    private function can(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission("email.{$action}"), 403);
    }
}
