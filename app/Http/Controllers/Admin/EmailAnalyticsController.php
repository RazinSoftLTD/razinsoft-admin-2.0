<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailNotificationRule;
use App\Models\EmailTemplate;
use App\Services\Email\EmailAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Email Settings → Analytics and Notification Rules. */
class EmailAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('email.analytics'), 403);

        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $from = today()->subDays($days - 1);
        $analytics = new EmailAnalytics($from, today());

        return view('admin.email.analytics', [
            'days' => $days,
            'summary' => $analytics->summary(),
            'periods' => $analytics->periods(),
            'daily' => $analytics->daily($days),
            'topTemplates' => $analytics->topTemplates(),
            'topConfigs' => $analytics->topConfigs(),
        ]);
    }

    public function rules(Request $request)
    {
        abort_unless($request->user()->hasPermission('email.rules'), 403);

        return view('admin.email.rules', [
            'grouped' => EmailNotificationRule::with('template:id,name,is_active')
                ->orderBy('id')->get()->groupBy('group'),
            'templates' => EmailTemplate::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function updateRules(Request $request)
    {
        abort_unless($request->user()->hasPermission('email.rules'), 403);

        $data = $request->validate([
            'enabled' => ['nullable', 'array'],
            'template' => ['nullable', 'array'],
            'template.*' => ['nullable', 'integer', 'exists:email_templates,id'],
        ]);

        $enabled = collect($data['enabled'] ?? [])->keys()->all();

        foreach (EmailNotificationRule::all() as $rule) {
            $rule->update([
                'is_enabled' => in_array((string) $rule->id, array_map('strval', $enabled), true),
                'email_template_id' => $data['template'][$rule->id] ?? $rule->email_template_id,
            ]);
        }

        return back()->with('status', 'Notification rules saved.');
    }
}
