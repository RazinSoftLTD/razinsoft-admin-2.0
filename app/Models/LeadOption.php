<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A configurable CRM lead taxonomy value (a source or a department). */
class LeadOption extends Model
{
    protected $fillable = ['type', 'label', 'sort_order'];

    /** The configurable list types, with their display labels. */
    public const TYPES = [
        'source' => 'Lead Source',
        'department' => 'Lead Department',
        'product' => 'Product',
        'deal_stage' => 'Deal Stage',
    ];

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type)->orderBy('sort_order')->orderBy('label');
    }

    /** Ordered list of labels for a type (e.g. ['WhatsApp', 'Website', ...]). */
    public static function labels(string $type): array
    {
        return static::ofType($type)->pluck('label')->all();
    }
}
