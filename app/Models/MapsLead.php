<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MapsLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Set explicitly. This app already ships an unrelated CRM `leads` table
     * (App\Models\Lead), so the Maps collector keeps its own `maps_*` tables
     * and never touches it.
     */
    protected $table = 'maps_leads';

    protected $fillable = [
        'place_key', 'name', 'maps_url', 'category', 'address', 'phone', 'website',
        'rating', 'review_count', 'latitude', 'longitude', 'plus_code', 'price_level',
        'business_status', 'opening_hours', 'source', 'search_country', 'search_city',
        'search_category', 'search_query', 'position', 'first_run_id', 'last_run_id',
        'times_seen', 'collected_at', 'status', 'notes', 'assigned_to',
        'email', 'email_source', 'email_status', 'email_checked_at', 'email_attempts',
        'outreach_status', 'outreach_sent_at', 'outreach_error',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'rating' => 'float',
        'review_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'position' => 'integer',
        'times_seen' => 'integer',
        'collected_at' => 'datetime',
        'email_checked_at' => 'datetime',
        'email_attempts' => 'integer',
        'outreach_sent_at' => 'datetime',
    ];

    public const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];

    /**
     * Free text search across the fields an operator actually types into.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('address', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('website', 'like', $like)
                ->orWhere('category', 'like', $like);
        });
    }

    /**
     * Apply the dashboard / export filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['country'] ?? null, fn (Builder $q, $v) => $q->where('search_country', $v))
            ->when($filters['city'] ?? null, fn (Builder $q, $v) => $q->where('search_city', $v))
            ->when($filters['category'] ?? null, fn (Builder $q, $v) => $q->where('category', 'like', "%{$v}%"))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['run_id'] ?? null, fn (Builder $q, $v) => $q->where('last_run_id', $v))
            ->when($filters['min_rating'] ?? null, fn (Builder $q, $v) => $q->where('rating', '>=', (float) $v))
            ->when($filters['min_reviews'] ?? null, fn (Builder $q, $v) => $q->where('review_count', '>=', (int) $v))
            // filled() rather than a null check: an "Any" dropdown submits an
            // empty string, which must mean "no filter" and not "has none".
            ->when(filled($filters['has_phone'] ?? null), function (Builder $q) use ($filters) {
                return filter_var($filters['has_phone'], FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereNotNull('phone')->where('phone', '!=', '')
                    : $q->where(fn (Builder $i) => $i->whereNull('phone')->orWhere('phone', ''));
            })
            ->when(filled($filters['has_website'] ?? null), function (Builder $q) use ($filters) {
                return filter_var($filters['has_website'], FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereNotNull('website')->where('website', '!=', '')
                    : $q->where(fn (Builder $i) => $i->whereNull('website')->orWhere('website', ''));
            })
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    public function run()
    {
        return $this->belongsTo(MapsImportRun::class, 'last_run_id', 'run_id');
    }

    /**
     * The foreign key is named explicitly. Eloquent would otherwise derive
     * `maps_lead_id` from the model name, but the column is `lead_id`.
     */
    public function logs()
    {
        return $this->hasMany(MapsCollectionLog::class, 'lead_id');
    }

    /**
     * The lead's unsubscribe handle, created on first use.
     *
     * Random rather than derived from the id, so one lead's opt-out link cannot
     * be edited into another lead's link.
     */
    public function unsubscribeToken(): string
    {
        if (blank($this->unsubscribe_token)) {
            $this->forceFill(['unsubscribe_token' => \Illuminate\Support\Str::random(40)])->save();
        }

        return $this->unsubscribe_token;
    }

    /** Has a usable address and has not been contacted yet. */
    public function awaitingOutreach(): bool
    {
        return filled($this->email) && ! $this->outreach_sent_at;
    }
}
