<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One saved billing address belonging to a customer. Exactly one of theirs is the default. */
class BillingAddress extends Model
{
    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
