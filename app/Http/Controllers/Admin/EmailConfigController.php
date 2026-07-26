<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailConfig;
use App\Services\Email\EmailDispatcher;
use App\Services\Email\SmtpTester;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Email Settings → Email Configuration. The SMTP accounts the system sends through. */
class EmailConfigController extends Controller
{
    public function index(Request $request)
    {
        $this->can($request, 'configure');

        $configs = EmailConfig::withCount([
            'logs as sent_today' => fn ($q) => $q->whereDate('sent_at', today()),
            'logs as failed_today' => fn ($q) => $q->where('status', 'failed')->whereDate('updated_at', today()),
        ])->orderByDesc('is_default')->orderBy('priority')->orderBy('id')->get();

        return view('admin.email.configs.index', [
            'configs' => $configs,
            'providers' => EmailConfig::PROVIDERS,
        ]);
    }

    public function store(Request $request)
    {
        $this->can($request, 'configure');

        $config = EmailConfig::create($this->validated($request) + [
            'health' => 'unknown',
        ]);

        // The first account has to be the default, or nothing would ever send.
        if (EmailConfig::count() === 1 || $request->boolean('is_default')) {
            $config->makeDefault();
        }

        return redirect()->route('admin.email.configs')->with('status', "SMTP account “{$config->name}” added.");
    }

    public function update(Request $request, EmailConfig $config)
    {
        $this->can($request, 'configure');

        $data = $this->validated($request, $config);

        // A blank password means "leave the stored one alone" — the form never echoes it back.
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        $config->update($data);

        if ($request->boolean('is_default')) {
            $config->makeDefault();
        }

        return back()->with('status', 'SMTP account updated.');
    }

    public function destroy(Request $request, EmailConfig $config)
    {
        $this->can($request, 'configure');

        // Deleting the only way to send mail should be a deliberate act, not a stray click.
        if ($config->is_default && EmailConfig::where('is_active', true)->count() > 1) {
            return back()->with('error', 'Make another account the default before deleting this one.');
        }

        $config->delete();

        return back()->with('status', 'SMTP account removed.');
    }

    public function makeDefault(Request $request, EmailConfig $config)
    {
        $this->can($request, 'configure');
        $config->makeDefault();

        return back()->with('status', "“{$config->name}” is now the default sender.");
    }

    /** Open the connection and authenticate, without sending anything. */
    public function test(Request $request, EmailConfig $config, SmtpTester $tester)
    {
        $this->can($request, 'configure');

        $result = $tester->test($config);

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /** Queue a real test message through this account. */
    public function sendTest(Request $request, EmailConfig $config, EmailDispatcher $dispatcher)
    {
        $this->can($request, 'configure');

        $data = $request->validate(['to' => ['required', 'email']]);

        $html = view('emails.smtp-test', ['config' => $config])->render();

        $log = $dispatcher->send($data['to'], 'Test email from '.config('app.name'), $html, null, [
            'config_id' => $config->id,
            'module' => 'email',
            'dedupe' => false,          // testing twice in a row is the whole point
        ]);

        return back()->with(
            $log ? 'status' : 'error',
            $log
                ? "Test queued to {$data['to']}. It sends on the next queue run — watch Email Logs for the result."
                : 'Could not queue the test — the address may be suppressed.',
        );
    }

    private function validated(Request $request, ?EmailConfig $config = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', Rule::in(array_keys(EmailConfig::PROVIDERS))],
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => [$config ? 'nullable' : 'nullable', 'string', 'max:190'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'from_name' => ['nullable', 'string', 'max:120'],
            'from_email' => ['required', 'email', 'max:190'],
            'reply_to' => ['nullable', 'email', 'max:190'],
            'return_path' => ['nullable', 'email', 'max:190'],
            'priority' => ['nullable', 'integer', 'between:1,999'],
            'hourly_limit' => ['nullable', 'integer', 'min:1'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function can(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission("email.{$action}"), 403);
    }
}
