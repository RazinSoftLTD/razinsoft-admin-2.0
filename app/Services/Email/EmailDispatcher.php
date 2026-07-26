<?php

namespace App\Services\Email;

use App\Jobs\SendQueuedEmail;
use App\Models\EmailConfig;
use App\Models\EmailNotificationRule;
use App\Models\EmailLog;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * The one way mail leaves this application.
 *
 * Everything — a template, a raw message, a campaign — comes through here, which is what makes the
 * log complete, the suppression list enforceable, and duplicate sends preventable. Nothing sends
 * inline: a row is written, a job is queued, and the worker does the SMTP conversation.
 */
class EmailDispatcher
{
    /**
     * Queue a message built from a stored template.
     *
     * @param  array<string, mixed>  $data       variables for the template
     * @param  array<string, mixed>  $options    to_name, cc, bcc, config_id, module, related,
     *                                           user_id, priority, scheduled_at, attachments,
     *                                           created_by, dedupe
     */
    public function sendTemplate(string $templateKey, string $to, array $data = [], array $options = []): ?EmailLog
    {
        // An event switched off in Notification Rules must not send, whatever the template says.
        if (($event = $options['event'] ?? null) && ! EmailNotificationRule::allows($event)) {
            Log::info("Email skipped — the [{$event}] notification is turned off.");

            return null;
        }

        $template = EmailTemplate::where('key', $templateKey)->first();

        if (! $template || ! $template->is_active) {
            // A disabled template is a deliberate "don't send this", not an error.
            Log::info("Email template [{$templateKey}] is missing or inactive — nothing sent.");

            return null;
        }

        $rendered = $template->renderFor($data);

        return $this->send(
            $to,
            $rendered['subject'],
            $rendered['html'],
            $rendered['text'],
            $options + ['template_id' => $template->id],
        );
    }

    /**
     * Queue a message. Returns the log row, or null when the send was refused (suppressed
     * address, invalid address, or an exact duplicate).
     */
    public function send(string $to, string $subject, string $html, ?string $text = null, array $options = []): ?EmailLog
    {
        $to = mb_strtolower(trim($to));

        if (! $this->isSendable($to)) {
            return null;
        }

        $config = EmailConfig::pick($options['config_id'] ?? null);

        if (! $config) {
            Log::warning('No active SMTP account — email not queued.', ['to' => $to, 'subject' => $subject]);

            return null;
        }

        // Same message, same recipient, still in flight → don't send it twice. Callers that
        // genuinely want a repeat (a resend) pass dedupe => false.
        $fingerprint = $this->fingerprint($to, $subject, $html);
        if (($options['dedupe'] ?? true) && $this->isDuplicate($fingerprint)) {
            Log::info('Duplicate email suppressed.', ['to' => $to, 'subject' => $subject]);

            return null;
        }

        $related = $options['related'] ?? null;

        $log = EmailLog::create([
            'email_config_id' => $config->id,
            'email_template_id' => $options['template_id'] ?? null,
            'module' => $options['module'] ?? null,
            'related_type' => $related instanceof Model ? $related::class : null,
            'related_id' => $related instanceof Model ? $related->getKey() : null,
            'user_id' => $options['user_id'] ?? null,
            'to_email' => $to,
            'to_name' => $options['to_name'] ?? null,
            'cc' => $options['cc'] ?? null,
            'bcc' => $options['bcc'] ?? null,
            'subject' => $subject,
            'body_html' => $html,
            // Every message goes out multipart: a text part is what keeps it out of spam filters.
            'body_text' => $text ?: EmailBodyBuilder::toPlainText($html),
            'status' => 'pending',
            'priority' => $options['priority'] ?? 10,
            'scheduled_at' => $options['scheduled_at'] ?? null,
            'queued_at' => now(),
            'fingerprint' => $fingerprint,
            'created_by' => $options['created_by'] ?? auth()->id(),
        ]);

        foreach ($options['attachments'] ?? [] as $attachment) {
            $log->attachments()->create($attachment);
        }

        $this->dispatchJob($log);

        return $log;
    }

    /** Put a pending log row back on the queue — used by retry and by the scheduler. */
    public function dispatchJob(EmailLog $log): void
    {
        $job = SendQueuedEmail::dispatch($log->id);

        if ($log->scheduled_at && $log->scheduled_at->isFuture()) {
            $job->delay($log->scheduled_at);
        }
    }

    /** Queue a failed/cancelled message again, clearing the previous error. */
    public function retry(EmailLog $log): void
    {
        $log->forceFill([
            'status' => 'pending',
            'error' => null,
            'queued_at' => now(),
            'scheduled_at' => null,
        ])->save();

        $this->dispatchJob($log);
    }

    /**
     * Refuse addresses that would damage the sending domain: malformed ones, and anything on the
     * suppression list (hard bounce, complaint, unsubscribe).
     */
    private function isSendable(string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('Email refused — not a valid address.', ['to' => $email]);

            return false;
        }

        if (EmailSuppression::has($email)) {
            Log::info('Email refused — address is suppressed.', ['to' => $email]);

            return false;
        }

        return true;
    }

    /**
     * A duplicate is the same fingerprint already pending or sending, or already sent in the last
     * few minutes — which catches a double-clicked button without blocking a deliberate resend
     * hours later.
     */
    private function isDuplicate(string $fingerprint): bool
    {
        return EmailLog::where('fingerprint', $fingerprint)
            ->where(fn ($q) => $q->whereIn('status', ['pending', 'sending'])
                ->orWhere(fn ($w) => $w->where('status', 'sent')->where('sent_at', '>=', now()->subMinutes(5))))
            ->exists();
    }

    private function fingerprint(string $to, string $subject, string $html): string
    {
        return hash('sha256', $to.'|'.$subject.'|'.$html);
    }
}
