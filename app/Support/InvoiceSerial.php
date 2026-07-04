<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Single running serial shared by orders AND CRM invoices, in the order-number format
 * RS-{2-digit year}{5-digit sequence} (e.g. RS-2600001). Row-locked so it stays unique,
 * and restarts each calendar year.
 */
class InvoiceSerial
{
    public static function next(): string
    {
        $year = now()->format('Y');
        $yy = now()->format('y');

        return DB::transaction(function () use ($year, $yy) {
            if (! DB::table('invoice_sequences')->where('year', $year)->exists()) {
                DB::table('invoice_sequences')->insertOrIgnore(['year' => $year, 'last_seq' => self::seed($yy)]);
            }

            $row = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->first();
            $next = ((int) $row->last_seq) + 1;
            DB::table('invoice_sequences')->where('year', $year)->update(['last_seq' => $next]);

            return sprintf('RS-%s%05d', $yy, $next);
        });
    }

    /**
     * The number next() WOULD return, without consuming it. For display only (e.g. the
     * create form) — the real number is allocated at save time, so this is just a hint.
     */
    public static function peek(): string
    {
        $year = now()->format('Y');
        $yy = now()->format('y');

        $row = DB::table('invoice_sequences')->where('year', $year)->first();
        $last = $row ? (int) $row->last_seq : self::seed($yy);

        return sprintf('RS-%s%05d', $yy, $last + 1);
    }

    /** Highest existing RS-{yy}##### across orders + both invoice tables, so the serial never collides. */
    protected static function seed(string $yy): int
    {
        $prefix = "RS-{$yy}";
        $max = 0;

        foreach ([['orders', 'order_number'], ['client_invoices', 'invoice_number'], ['invoices', 'invoice_number']] as [$table, $col]) {
            $last = DB::table($table)->where($col, 'like', "{$prefix}%")->orderByDesc($col)->value($col);
            if ($last) {
                $max = max($max, (int) substr($last, strlen($prefix)));
            }
        }

        return $max;
    }
}
