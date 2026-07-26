<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailConfig;
use App\Models\EmailLog;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Services\Email\EmailDispatcher;
use Illuminate\Http\Request;

/**
 * Email Settings → Queue and Logs.
 *
 * Both screens read the same table: the queue is the pending/sending end of it and the log is
 * everything. Keeping them on one model means a message can never appear in one and not the other.
 */
class EmailLogController extends Controller
{
    /** The queue: what is waiting, stuck or scheduled. */
    public function queue(Request $request)
    {
        $this->can($request, 'queue');

        $logs = $this->filtered($request)
            ->whereIn('status', ['pending', 'sending', 'failed'])
            ->orderByRaw("CASE status WHEN 'failed' THEN 0 WHEN 'sending' THEN 1 ELSE 2 END")
            ->orderBy('priority')
            ->orderBy('id')
            ->paginate(30)->withQueryString();

        return view('admin.email.queue', [
            'logs' => $logs,
            'counts' => $this->queueCounts(),
            'workerRunning' => $this->workerLooksAlive(),
            'templates' => EmailTemplate::orderBy('name')->pluck('name', 'id'),
            'configs' => EmailConfig::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** The full log, with the filters the spec lists. */
    public function index(Request $request)
    {
        $this->can($request, 'logs');

        $logs = $this->filtered($request)->latest('id')->paginate(30)->withQueryString();

        return view('admin.email.logs.index', [
            'logs' => $logs,
            'templates' => EmailTemplate::orderBy('name')->pluck('name', 'id'),
            'configs' => EmailConfig::orderBy('name')->pluck('name', 'id'),
            'modules' => EmailLog::whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
        ]);
    }

    /** One message: what was sent, which account carried it, and everything that happened after. */
    public function show(Request $request, EmailLog $log)
    {
        $this->can($request, 'logs');

        $log->load('config', 'template', 'attachments', 'opens', 'clicks', 'creator');

        return view('admin.email.logs.show', ['log' => $log]);
    }

    /** The rendered message, exactly as it went out — shown in an iframe on the detail page. */
    public function body(Request $request, EmailLog $log)
    {
        $this->can($request, 'logs');

        return $request->boolean('text')
            ? response((string) $log->body_text, 200, ['Content-Type' => 'text/plain; charset=utf-8'])
            : response((string) $log->body_html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function retry(Request $request, EmailLog $log, EmailDispatcher $dispatcher)
    {
        $this->can($request, 'queue');

        if (! $log->isRetryable()) {
            return back()->with('error', 'Only failed or cancelled messages can be retried.');
        }

        $dispatcher->retry($log);

        return back()->with('status', 'Message queued again.');
    }

    /** Retry everything that failed, so one provider outage is one click to recover from. */
    public function retryAll(Request $request, EmailDispatcher $dispatcher)
    {
        $this->can($request, 'queue');

        $failed = EmailLog::where('status', 'failed')->get();

        foreach ($failed as $log) {
            $dispatcher->retry($log);
        }

        return back()->with('status', $failed->count()
            ? "Re-queued {$failed->count()} failed message(s)."
            : 'Nothing was waiting to retry.');
    }

    /** Stop a message that has not gone yet. */
    public function cancel(Request $request, EmailLog $log)
    {
        $this->can($request, 'queue');

        if (! in_array($log->status, ['pending', 'sending'], true)) {
            return back()->with('error', 'That message has already been sent.');
        }

        $log->forceFill(['status' => 'cancelled', 'error' => 'Cancelled by '.$request->user()->name])->save();

        return back()->with('status', 'Message cancelled — the worker will skip it.');
    }

    public function destroy(Request $request, EmailLog $log)
    {
        $this->can($request, 'logs');

        $log->delete();

        return redirect()->route('admin.email.logs')->with('status', 'Log entry deleted.');
    }

    /** Send this exact message again — a new row, so the original record is kept. */
    public function resend(Request $request, EmailLog $log, EmailDispatcher $dispatcher)
    {
        $this->can($request, 'send');

        $new = $dispatcher->send($log->to_email, $log->subject, (string) $log->body_html, $log->body_text, [
            'to_name' => $log->to_name,
            'config_id' => $log->email_config_id,
            'template_id' => $log->email_template_id,
            'module' => $log->module,
            'user_id' => $log->user_id,
            'dedupe' => false,          // resending is the whole point
        ]);

        return $new
            ? redirect()->route('admin.email.logs.show', $new)->with('status', 'Queued a fresh copy.')
            : back()->with('error', 'Could not resend — the address may now be suppressed.');
    }

    /** Suppression list: the addresses nothing may mail again. */
    public function suppressions(Request $request)
    {
        $this->can($request, 'logs');

        return view('admin.email.suppressions', [
            'suppressions' => EmailSuppression::latest('id')->paginate(30)->withQueryString(),
            'reasons' => EmailSuppression::REASONS,
        ]);
    }

    public function addSuppression(Request $request)
    {
        $this->can($request, 'configure');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        EmailSuppression::add($data['email'], 'manual', $data['note'] ?? null);

        return back()->with('status', "{$data['email']} will no longer receive email.");
    }

    /** Removing an address lets mail flow to it again — deliberate, and worth logging. */
    public function removeSuppression(Request $request, EmailSuppression $suppression)
    {
        $this->can($request, 'configure');

        $email = $suppression->email;
        $suppression->delete();

        return back()->with('status', "{$email} can receive email again.");
    }

    // ---------------------------------------------------------------- internals

    /** The shared filter set, used by both the queue and the log. */
    private function filtered(Request $request)
    {
        return EmailLog::with('config:id,name', 'template:id,name')
            ->search($request->query('q'))
            ->status($request->query('status'))
            ->when($request->query('template'), fn ($q, $v) => $q->where('email_template_id', $v))
            ->when($request->query('config'), fn ($q, $v) => $q->where('email_config_id', $v))
            ->when($request->query('module'), fn ($q, $v) => $q->where('module', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->query('opened') === 'yes', fn ($q) => $q->whereNotNull('first_opened_at'))
            ->when($request->query('opened') === 'no', fn ($q) => $q->whereNull('first_opened_at')->where('status', 'sent'))
            ->when($request->query('clicked') === 'yes', fn ($q) => $q->whereNotNull('first_clicked_at'))
            ->when($request->boolean('bounced'), fn ($q) => $q->where('bounced', true));
    }

    private function queueCounts(): array
    {
        $rows = EmailLog::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'pending' => (int) ($rows['pending'] ?? 0),
            'sending' => (int) ($rows['sending'] ?? 0),
            'failed' => (int) ($rows['failed'] ?? 0),
            'sent' => (int) ($rows['sent'] ?? 0),
            'cancelled' => (int) ($rows['cancelled'] ?? 0),
            'scheduled' => EmailLog::where('status', 'pending')->whereNotNull('scheduled_at')
                ->where('scheduled_at', '>', now())->count(),
        ];
    }

    /**
     * Whether a worker seems to be running.
     *
     * There is no reliable way to ask the queue that, so this infers it: anything due to go out
     * and still sitting there after five minutes means nothing is draining the queue. Getting
     * that wrong is why "the email system is broken" usually turns out to be a stopped worker.
     */
    private function workerLooksAlive(): bool
    {
        $stuck = EmailLog::due()->where('queued_at', '<', now()->subMinutes(5))->exists();

        return ! $stuck;
    }

    private function can(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission("email.{$action}"), 403);
    }
}
