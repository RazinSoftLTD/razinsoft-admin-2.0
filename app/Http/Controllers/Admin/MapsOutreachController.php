<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailConfig;
use App\Models\EmailTemplate;
use App\Models\MapsLead;
use App\Models\MapsOutreachSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The switches that make lead outreach run by itself.
 *
 * These settings previously existed only in the database, so turning the
 * pipeline on meant running tinker by hand — which also meant nobody could see
 * why nothing was sending. This screen owns them, and shows the readiness checks
 * next to them: without an SMTP account or a queue worker the switches do
 * nothing, and that is worth saying on the page rather than in a log file.
 */
class MapsOutreachController extends Controller
{
    public function edit(): View
    {
        $settings = MapsOutreachSetting::current();

        return view('admin.email.automation', [
            'settings' => $settings,
            'templates' => EmailTemplate::where('is_active', true)->orderBy('name')->get(['key', 'name']),
            'configs' => EmailConfig::orderBy('name')->get(['id', 'name', 'from_email', 'is_active']),
            'countries' => MapsLead::query()
                ->whereNotNull('search_country')->where('search_country', '!=', '')
                ->distinct()->orderBy('search_country')->pluck('search_country'),
            'checks' => $this->readiness($settings),
            'funnel' => $this->funnel(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'discover_emails' => ['nullable', 'boolean'],
            'auto_send' => ['nullable', 'boolean'],
            'template_key' => ['required', 'string', 'exists:email_templates,key'],
            'email_config_id' => ['nullable', 'integer', 'exists:email_configs,id'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:2000'],
            'min_gap_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'allowed_countries' => ['nullable', 'array'],
            'allowed_countries.*' => ['string', 'max:120'],
        ]);

        MapsOutreachSetting::current()->update([
            // Unchecked boxes are absent from the request, not false.
            'is_enabled' => $request->boolean('is_enabled'),
            'discover_emails' => $request->boolean('discover_emails'),
            'auto_send' => $request->boolean('auto_send'),
            'template_key' => $data['template_key'],
            'email_config_id' => $data['email_config_id'] ?: null,
            'daily_limit' => $data['daily_limit'],
            'min_gap_seconds' => $data['min_gap_seconds'],
            'allowed_countries' => array_values(array_filter($data['allowed_countries'] ?? [])),
        ]);

        return back()->with('status', 'Outreach settings saved.');
    }

    /**
     * Everything that has to be true before a single message can go out, each
     * with what to do about it. Ordered the way an operator would work through
     * them.
     *
     * @return array<int, array{ok: bool, label: string, detail: string}>
     */
    private function readiness(MapsOutreachSetting $settings): array
    {
        $smtp = EmailConfig::where('is_active', true)->count();
        $template = EmailTemplate::where('key', $settings->template_key)->where('is_active', true)->exists();
        $withEmail = MapsLead::whereNotNull('email')->where('email', '!=', '')->count();

        // A queue worker cannot be detected directly; a job sitting unclaimed for
        // a few minutes is the practical symptom of one not running.
        $stuck = \Illuminate\Support\Facades\DB::table('jobs')
            ->where('created_at', '<', now()->subMinutes(5)->timestamp)
            ->count();

        return [
            [
                'ok' => $smtp > 0,
                'label' => 'An active SMTP account',
                'detail' => $smtp > 0
                    ? "{$smtp} active"
                    : 'Without one the mailer refuses every message. Add one under SMTP Accounts.',
            ],
            [
                'ok' => $template,
                'label' => 'The chosen template is active',
                'detail' => $template
                    ? $settings->template_key
                    : "Template [{$settings->template_key}] is missing or switched off.",
            ],
            [
                'ok' => $stuck === 0,
                'label' => 'A queue worker is running',
                'detail' => $stuck === 0
                    ? 'No jobs are stuck waiting.'
                    : "{$stuck} job(s) queued over 5 minutes ago. Run: php artisan queue:work --queue=maps-leads,default",
            ],
            [
                'ok' => $withEmail > 0,
                'label' => 'Leads with an email address',
                'detail' => $withEmail > 0
                    ? "{$withEmail} reachable"
                    : 'Turn on "Look up email addresses" and collect some leads; addresses come from their websites.',
            ],
        ];
    }

    /**
     * How far the collected leads have travelled. Answers "why is nothing
     * sending" and "is this working" from one row of numbers.
     *
     * @return array<string, int>
     */
    private function funnel(): array
    {
        return [
            'collected' => MapsLead::count(),
            'with_website' => MapsLead::whereNotNull('website')->where('website', '!=', '')->count(),
            'with_email' => MapsLead::whereNotNull('email')->where('email', '!=', '')->count(),
            'contacted' => MapsLead::whereNotNull('outreach_sent_at')->count(),
            'opened' => MapsLead::whereHas('emailLogs', fn ($q) => $q->whereNotNull('first_opened_at'))->count(),
            'clicked' => MapsLead::whereHas('emailLogs', fn ($q) => $q->whereNotNull('first_clicked_at'))->count(),
        ];
    }
}
