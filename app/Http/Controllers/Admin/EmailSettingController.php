<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use App\Support\TemplateMailer;
use Illuminate\Http\Request;

class EmailSettingController extends Controller
{
    public function index()
    {
        $settings = EmailSetting::current();
        $templates = EmailTemplate::orderBy('name')->get();

        return view('admin.email-settings.index', compact('settings', 'templates'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mailer' => ['required', 'in:smtp,log,sendmail'],
            'host' => ['nullable', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
            'encryption' => ['nullable', 'in:tls,ssl,none'],
            'from_address' => ['nullable', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $settings = EmailSetting::current();

        $settings->mailer = $data['mailer'];
        $settings->host = $data['host'] ?? null;
        $settings->port = $data['port'];
        $settings->username = $data['username'] ?? null;
        // Only overwrite the password when a new one is typed (blank leaves it unchanged).
        if ($request->filled('password')) {
            $settings->password = $data['password'];
        }
        $settings->encryption = ($data['encryption'] ?? 'none') === 'none' ? null : $data['encryption'];
        $settings->from_address = $data['from_address'] ?? null;
        $settings->from_name = $data['from_name'] ?? null;
        $settings->is_enabled = $request->boolean('is_enabled');
        $settings->save();

        return back()->with('status', 'Email settings saved.');
    }

    public function sendTest(Request $request)
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);

        try {
            TemplateMailer::sendTest($data['test_email']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Test failed: '.$e->getMessage());
        }

        return back()->with('status', 'Test email sent to '.$data['test_email'].'.');
    }

    public function editTemplate(EmailTemplate $template)
    {
        return view('admin.email-settings.template', compact('template'));
    }

    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->update([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => clean($data['body']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.email-settings')->with('status', 'Template “'.$template->name.'” saved.');
    }
}
