<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One phone number found by a crawl, in full international form. */
class ScrapedNumber extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_whatsapp' => 'boolean',
        'imported_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(EmailScrapeRun::class, 'run_id');
    }

    public function importedClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_client_id');
    }
}
