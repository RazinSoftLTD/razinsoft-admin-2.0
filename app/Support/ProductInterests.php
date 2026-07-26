<?php

namespace App\Support;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Saves the "Interested in" picks for a lead, deal or client. */
class ProductInterests
{
    /**
     * Sync from `interest_ids[]`. The picker also posts `interest_ids_sync`, so unticking the last
     * one still clears the set instead of being read as "the field wasn't submitted" — the same
     * problem the ticket agent picker has.
     */
    public static function syncFrom(Request $request, Model $model, string $field = 'interest_ids'): void
    {
        if (! $request->has($field) && ! $request->boolean($field.'_sync')) {
            return;
        }

        $ids = collect($request->input($field, []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique();

        // Drop anything that is not a real category, so a tampered form can't write junk rows.
        $valid = ProductCategory::whereIn('id', $ids)->pluck('id')->all();

        $model->interests()->sync($valid);
    }
}
