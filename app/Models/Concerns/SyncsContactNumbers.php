<?php

namespace App\Models\Concerns;

use App\Models\ContactNumber;

/**
 * Mirrors the model's built-in phone columns into `contact_numbers`, which is what
 * lead ↔ client matching joins on. Numbers added by hand in the UI are left alone —
 * only the rows that mirror a column are created/removed as that column changes.
 */
trait SyncsContactNumbers
{
    /** column => label used when creating the mirrored row. */
    protected function mirroredPhoneColumns(): array
    {
        return ['phone' => 'mobile', 'mobile' => 'mobile', 'office_phone' => 'office'];
    }

    public static function bootSyncsContactNumbers(): void
    {
        static::saved(function ($model) {
            $model->syncContactNumbersFromColumns();
        });
    }

    public function syncContactNumbersFromColumns(): void
    {
        $columns = array_filter(
            $this->mirroredPhoneColumns(),
            fn ($col) => array_key_exists($col, $this->getAttributes()),
            ARRAY_FILTER_USE_KEY
        );
        if (! $columns) {
            return;
        }

        $dial = $this->dial_code ?? null;
        $country = $this->country ?? null;
        $existing = $this->contactNumbers()->get();
        $keyOf = fn (?string $n) => ContactNumber::toE164($n, $dial, $country) ?: mb_strtolower(trim((string) $n));

        foreach ($columns as $col => $label) {
            $new = trim((string) ($this->{$col} ?? ''));
            $old = trim((string) ($this->getOriginal($col) ?? ''));

            // The column's previous value no longer belongs to this contact — drop its row.
            if ($old !== '' && $old !== $new) {
                $oldKey = $keyOf($old);
                $stale = $existing->first(fn ($r) => $keyOf($r->number) === $oldKey);
                // Never remove a row that still matches another column.
                $stillUsed = collect($columns)->keys()->contains(fn ($c) => $keyOf($this->{$c} ?? '') === $oldKey);
                if ($stale && ! $stillUsed) {
                    $stale->delete();
                    $existing = $existing->reject(fn ($r) => $r->is($stale));
                }
            }

            if ($new === '') {
                continue;
            }

            $newKey = $keyOf($new);
            if ($existing->contains(fn ($r) => $keyOf($r->number) === $newKey)) {
                continue;                                   // already recorded (typed by hand or unchanged)
            }

            $row = $this->contactNumbers()->create([
                'label' => $label,
                'dial_code' => $dial,
                'number' => $new,
                'is_primary' => $existing->isEmpty(),
                'is_whatsapp' => $col === 'phone' && (bool) ($this->is_whatsapp ?? false),
                'position' => $existing->count(),
            ]);
            $existing->push($row);
        }
    }
}
