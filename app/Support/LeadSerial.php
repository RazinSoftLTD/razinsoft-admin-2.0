<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Running serial for lead codes, LD-{2-digit year}{4-digit sequence} (e.g. LD-260001).
 * Its own per-year counter (independent of the RS- invoice serial), row-locked and unique.
 */
class LeadSerial
{
    public static function next(): string
    {
        $year = now()->format('Y');
        $yy = now()->format('y');

        return DB::transaction(function () use ($year, $yy) {
            if (! DB::table('lead_sequences')->where('year', $year)->exists()) {
                DB::table('lead_sequences')->insertOrIgnore(['year' => $year, 'last_seq' => self::seed($yy)]);
            }

            $row = DB::table('lead_sequences')->where('year', $year)->lockForUpdate()->first();
            $next = ((int) $row->last_seq) + 1;
            DB::table('lead_sequences')->where('year', $year)->update(['last_seq' => $next]);

            return sprintf('LD-%s%04d', $yy, $next);
        });
    }

    /** Highest existing LD-{yy}#### in the leads table (0 if none), so the serial never collides. */
    protected static function seed(string $yy): int
    {
        $last = DB::table('leads')->where('lead_code', 'like', "LD-{$yy}%")->orderByDesc('lead_code')->value('lead_code');

        return $last ? (int) substr($last, strlen("LD-{$yy}")) : 0;
    }
}
