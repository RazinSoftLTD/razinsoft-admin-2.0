<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One saved billing address belonging to a customer. Exactly one of theirs is the default. */
class BillingAddress extends Model
{
    /** The only labels a customer can pick. Free text let every address be named differently. */
    public const LABELS = ['home' => 'Home', 'office' => 'Office', 'other' => 'Other'];

    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

    public function labelName(): string
    {
        return self::LABELS[$this->label] ?? self::LABELS['other'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Display names for one customer's addresses, keyed by id: "Home", "Office" — and "Home 1",
     * "Home 2" once a type repeats, so two homes are still tellable apart in a dropdown.
     *
     * Numbered by id, not by the display order, so a name does not change when another address
     * is made the default.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $addresses
     * @return array<int, string>
     */
    public static function displayNames($addresses): array
    {
        $byLabel = $addresses->sortBy('id')->groupBy(fn (self $a) => $a->label ?: 'other');
        $names = [];

        foreach ($byLabel as $group) {
            $many = $group->count() > 1;
            foreach ($group->values() as $i => $a) {
                $names[$a->id] = $a->labelName().($many ? ' '.($i + 1) : '');
            }
        }

        return $names;
    }

    /** "12 Gulshan Ave, Dhaka, Dhaka, 1212, Bangladesh" — one line, blanks skipped. */
    public function oneLine(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->zip, $this->country])
            ->filter()->join(', ');
    }

    /** Make this the customer's default, clearing the flag on their others. */
    public function makeDefault(): void
    {
        static::where('user_id', $this->user_id)->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }

    protected static function booted(): void
    {
        // The first address a customer saves is their default; deleting the default promotes another.
        static::creating(function (self $a) {
            if (! static::where('user_id', $a->user_id)->exists()) {
                $a->is_default = true;
            }
        });

        static::created(fn (self $a) => $a->is_default ? $a->makeDefault() : null);

        static::deleted(function (self $a) {
            if ($a->is_default) {
                static::where('user_id', $a->user_id)->oldest('id')->first()?->makeDefault();
            }
        });
    }
}
