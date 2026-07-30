<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One business returned by a Places search. */
class PlaceLead extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rating' => 'decimal:1',
        'website_crawled_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(PlaceSearch::class, 'search_id');
    }

    public function importedClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_client_id');
    }

    /** The host, for handing to the email crawler and for showing a short label. */
    public function websiteHost(): ?string
    {
        return $this->website ? (parse_url($this->website, PHP_URL_HOST) ?: null) : null;
    }
}
