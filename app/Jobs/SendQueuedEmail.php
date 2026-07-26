<?php

namespace App\Jobs;

use App\Models\EmailConfig;
use App\Models\EmailLog;
use App\Models\EmailSuppression;
use App\Services\Email\EmailBodyBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Does the SMTP conversation for one queued message.
 *
 * Only the id is carried, not the model: the row can change between queueing and running (an admin
 * may cancel it), and the job must act on what is true when it runs.
 */
class SendQueuedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Temporary SMTP failures are worth retrying; the backoff spreads them out. */
    public int $tries = 4;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $logId) {}

    public function handle(): void
    {
        $log = EmailLog::with('config', 'attachments')->find($this->logId);

        // Deleted, already handled, or cancelled while it sat in the queue.
        if (! $log || ! in_array($log->status, ['pending', 'sending'], true)) {
            return;
        }

        // The address may have bounced or complained since this was queued.
        if (EmailSuppression::has($log->to_email)) {
            $log->forceFill(['status' => 'cancelled', 'error' => 'Address is on the suppression list.'])->save();

            return;
        }

        $config = $this->pickConfig($log);

        if (! $config) {
            // No account has room right now — come back rather than burn a retry.
            $this->release(300);

            return;
        }

        $log->forceFill([
            'status' => 'sending',
            'email_config_id' => $config->id,
            'attempts' => $log->attempts + 1,
        ])->save();

        try {
            $this->deliver($log, $config);

            $log->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null])->save();
            $config->markHealthy();
        } catch (\Throwable $e) {
            $config->markFailing($e->getMessage());
            $this->fail($e);                       // hands control to failed(), below
        }
    }

    /**
     * The account to send with: the one chosen at queue time while it is healthy and inside its
     * limits, otherwise the next usable account. Falling back keeps mail flowing when one provider
     * is rate-limiting or down.
     */
    private function pickConfig(EmailLog $log): ?EmailConfig
    {
        if ($log->config && $log->config->is_active && $log->config->withinLimits()) {
            return $log->config;
        }

        return EmailConfig::usable()->get()->first(fn (EmailConfig $c) => $c->withinLimits());
    }

    private function deliver(EmailLog $log, EmailConfig $config): void
    {
        $mailer = $config->applyToMailer();
        $html = EmailBodyBuilder::withTracking($log->body_html ?? '', $log);
        $text = $log->body_text ?: EmailBodyBuilder::toPlainText($log->body_html ?? '');

        Mail::mailer($mailer)->send([], [], function ($message) use ($log, $config, $html, $text) {
            $message->to($log->to_email, $log->to_name ?: null)
                ->subject($log->subject)
                ->from($config->from_email, $config->from_name ?: config('app.name'))
                ->html($html)
                ->text($text);                     // multipart: HTML + plain text

            if ($config->reply_to) {
                $message->replyTo($config->reply_to);
            }
            if ($config->return_path) {
                $message->returnPath($config->return_path);
            }
            foreach ($log->cc ?? [] as $cc) {
                $message->cc($cc);
            }
            foreach ($log->bcc ?? [] as $bcc) {
                $message->bcc($bcc);
            }

            foreach ($log->attachments as $file) {
                $path = Storage::disk('public')->path($file->path);
                if (is_file($path)) {
                    $message->attach($path, array_filter(['as' => $file->name, 'mime' => $file->mime]));
                }
            }

            $headers = $message->getHeaders();

            // Message-ID is a structured header — Symfony rejects it as free text. Setting our own
            // means the id in the log matches the one on the message the recipient receives.
            $headers->addIdHeader('Message-ID', $log->tracking_id.'@'.EmailBodyBuilder::domain());

            foreach (EmailBodyBuilder::textHeadersFor($log) as $name => $value) {
                $headers->addTextHeader($name, $value);
            }
        });
    }

    /** Last attempt has failed — record why, so it shows on the log screen. */
    public function failed(\Throwable $e): void
    {
        EmailLog::whereKey($this->logId)->update([
            'status' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 2000),
            'updated_at' => now(),
        ]);
    }
}
