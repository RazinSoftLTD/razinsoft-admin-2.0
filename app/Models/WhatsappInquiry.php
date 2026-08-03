<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A WhatsApp enquiry, recorded before anyone judges whether it is worth chasing.
 *
 * This sits in front of Leads on purpose. A lead list only contains what someone decided to keep,
 * so it cannot answer "how much traffic did this number bring?" — the enquiries that went nowhere
 * are exactly the ones that measure an ad.
 */
class WhatsappInquiry extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'inquiry_date' => 'date',
        'conversation_started' => 'boolean',
        'is_relevant' => 'boolean',
        'converted_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class, 'whatsapp_account_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function isConverted(): bool
    {
        return $this->lead_id !== null;
    }

    /** The number as it should be displayed — the account's own label, or whatever was typed. */
    public function companyNumberLabel(): string
    {
        return $this->account?->display_number ?: ($this->company_number ?: '—');
    }

    public function scopeOn(Builder $q, string|Carbon $date): Builder
    {
        return $q->whereDate('inquiry_date', $date);
    }

    /**
     * A day's figures in one pass.
     *
     * Written as conditional sums rather than four queries because the dashboard asks for all of
     * them at once, on every page load, for a table that grows by every ad click.
     *
     * @return array{total:int, started:int, relevant:int, converted:int}
     */
    public static function summaryFor(string|Carbon $date, ?int $accountId = null): array
    {
        $row = static::query()
            ->on($date)
            ->when($accountId, fn ($q) => $q->where('whatsapp_account_id', $accountId))
            ->selectRaw('
                COUNT(*) AS total,
                SUM(conversation_started = 1) AS started,
                SUM(is_relevant = 1) AS relevant,
                SUM(lead_id IS NOT NULL) AS converted
            ')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'started' => (int) ($row->started ?? 0),
            'relevant' => (int) ($row->relevant ?? 0),
            'converted' => (int) ($row->converted ?? 0),
        ];
    }

    /** Enquiries per number for a day, keyed by account id. */
    public static function byNumberFor(string|Carbon $date): \Illuminate\Support\Collection
    {
        return static::query()
            ->on($date)
            ->selectRaw('whatsapp_account_id, COUNT(*) AS total, SUM(conversation_started = 1) AS started, SUM(is_relevant = 1) AS relevant')
            ->groupBy('whatsapp_account_id')
            ->get()
            ->keyBy('whatsapp_account_id');
    }

    /** What people asked about, commonest first. Blank interests are not an answer, so they are out. */
    public static function interestsFor(string|Carbon $from, string|Carbon $to, int $limit = 8): \Illuminate\Support\Collection
    {
        return static::query()
            // See the controller: a between on a date column does not survive SQLite's storage.
            ->whereDate('inquiry_date', '>=', $from)->whereDate('inquiry_date', '<=', $to)
            ->whereNotNull('interest')
            ->where('interest', '!=', '')
            ->selectRaw('interest, COUNT(*) AS total')
            ->groupBy('interest')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }
}
