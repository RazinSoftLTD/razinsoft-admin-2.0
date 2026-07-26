<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers behind the analytics screen.
 *
 * Rates are worked out against what was actually delivered, not everything queued: an open rate
 * that counts messages which never left flatters itself and hides the real problem. Where a rate
 * has no meaningful denominator it returns null and the screen shows a dash rather than 0%.
 */
class EmailAnalytics
{
    public function __construct(
        private readonly ?Carbon $from = null,
        private readonly ?Carbon $to = null,
    ) {}

    /** Headline counters for the stat cards. */
    public function summary(): array
    {
        $base = $this->scope();

        $sent = (clone $base)->where('status', 'sent')->count();
        $failed = (clone $base)->where('status', 'failed')->count();
        $opened = (clone $base)->whereNotNull('first_opened_at')->count();
        $clicked = (clone $base)->whereNotNull('first_clicked_at')->count();
        $bounced = (clone $base)->where('bounced', true)->count();
        $complained = (clone $base)->where('complained', true)->count();

        // "Delivered" means it left and did not bounce — the honest denominator for engagement.
        $delivered = max(0, $sent - $bounced);

        return [
            'total' => (clone $base)->count(),
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $failed,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'complained' => $complained,
            'open_rate' => $this->rate($opened, $delivered),
            'click_rate' => $this->rate($clicked, $delivered),
            // Click-to-open: of the people who opened it, how many acted. A better read of the
            // content than click rate, which is dragged down by anything that stops opens.
            'click_to_open_rate' => $this->rate($clicked, $opened),
            'bounce_rate' => $this->rate($bounced, $sent),
            'complaint_rate' => $this->rate($complained, $sent),
            'failure_rate' => $this->rate($failed, (clone $base)->count()),
            'avg_delivery_seconds' => $this->averageDeliverySeconds(),
        ];
    }

    /** Counts for the periods the spec lists, independent of the chosen range. */
    public function periods(): array
    {
        return [
            'today' => EmailLog::whereDate('created_at', today())->count(),
            'week' => EmailLog::where('created_at', '>=', now()->startOfWeek())->count(),
            'month' => EmailLog::where('created_at', '>=', now()->startOfMonth())->count(),
            'year' => EmailLog::where('created_at', '>=', now()->startOfYear())->count(),
        ];
    }

    /**
     * One row per day for the charts: queued, sent, opened, failed.
     *
     * Grouped in PHP rather than SQL — date functions differ between SQLite and MySQL, and this
     * module has to behave the same on both.
     */
    public function daily(int $days = 30): Collection
    {
        $start = today()->subDays($days - 1);

        $rows = EmailLog::where('created_at', '>=', $start)
            ->get(['created_at', 'sent_at', 'status', 'first_opened_at', 'bounced']);

        $byDay = $rows->groupBy(fn (EmailLog $l) => $l->created_at->toDateString());

        return collect(range(0, $days - 1))->map(function (int $i) use ($start, $byDay) {
            $date = $start->copy()->addDays($i);
            $day = $byDay->get($date->toDateString(), collect());

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('d M'),
                'total' => $day->count(),
                'sent' => $day->where('status', 'sent')->count(),
                'opened' => $day->whereNotNull('first_opened_at')->count(),
                'failed' => $day->where('status', 'failed')->count(),
            ];
        });
    }

    /** Templates ranked by how much they are used, with how well each performs. */
    public function topTemplates(int $limit = 6): Collection
    {
        return $this->scope()
            ->whereNotNull('email_template_id')
            ->with('template:id,name')
            ->get(['email_template_id', 'status', 'first_opened_at', 'first_clicked_at', 'bounced'])
            ->groupBy('email_template_id')
            ->map(function (Collection $rows) {
                $sent = $rows->where('status', 'sent')->count();
                $delivered = max(0, $sent - $rows->where('bounced', true)->count());

                return [
                    'name' => $rows->first()->template?->name ?? 'Deleted template',
                    'total' => $rows->count(),
                    'sent' => $sent,
                    'opened' => $rows->whereNotNull('first_opened_at')->count(),
                    'open_rate' => $this->rate($rows->whereNotNull('first_opened_at')->count(), $delivered),
                ];
            })
            ->sortByDesc('total')->take($limit)->values();
    }

    /** SMTP accounts ranked by volume, with each one's failure rate. */
    public function topConfigs(int $limit = 6): Collection
    {
        return $this->scope()
            ->whereNotNull('email_config_id')
            ->with('config:id,name')
            ->get(['email_config_id', 'status'])
            ->groupBy('email_config_id')
            ->map(fn (Collection $rows) => [
                'name' => $rows->first()->config?->name ?? 'Deleted account',
                'total' => $rows->count(),
                'sent' => $rows->where('status', 'sent')->count(),
                'failed' => $rows->where('status', 'failed')->count(),
                'failure_rate' => $this->rate($rows->where('status', 'failed')->count(), $rows->count()),
            ])
            ->sortByDesc('total')->take($limit)->values();
    }

    /** How long the provider took to accept a message, on average. */
    private function averageDeliverySeconds(): ?int
    {
        $seconds = $this->scope()
            ->where('status', 'sent')
            ->whereNotNull('queued_at')->whereNotNull('sent_at')
            ->get(['queued_at', 'sent_at'])
            // Rows where sent_at precedes queued_at are nonsense (clock skew, imported data) and
            // would drag the average; they are dropped rather than counted as huge delays.
            ->map(fn (EmailLog $l) => $l->sent_at->getTimestamp() - $l->queued_at->getTimestamp())
            ->filter(fn (int $s) => $s >= 0);

        return $seconds->isEmpty() ? null : (int) round($seconds->avg());
    }

    /** A percentage, or null when there is nothing to divide by. */
    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    private function scope()
    {
        return EmailLog::query()
            ->when($this->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
    }
}
