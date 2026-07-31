<?php

namespace App\Services\Envato;

use App\Models\EnvatoProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns the daily snapshots into "how many did this sell that day".
 *
 * The sync stores a running total, not a daily figure, so a day's sales is the
 * difference between two consecutive snapshots. Doing that in one pass here
 * keeps every comparison screen reading the same numbers.
 *
 * Days with no snapshot are simply absent rather than zero: a missed sync is not
 * a day of no sales, and charting it as one would be a lie.
 */
class SalesCompare
{
    /**
     * Daily sales per product over a window.
     *
     * @param  Collection<int, EnvatoProduct>|array<int, int>  $products
     * @return Collection<int, array{product_id: int, date: string, sold: int, total: int}>
     */
    public function daily($products, Carbon $from, Carbon $to): Collection
    {
        $ids = $products instanceof Collection
            ? $products->pluck('id')->all()
            : (array) $products;

        if (! $ids) {
            return collect();
        }

        // One extra day at the front: the first day in range needs the snapshot
        // before it to have anything to subtract from.
        //
        // whereDate, not whereBetween: the column is declared `date` but the model's
        // date cast writes "Y-m-d 00:00:00", and on SQLite that is compared as a
        // string — a bare "Y-m-d" upper bound would silently drop the newest day.
        $rows = DB::table('envato_snapshots')
            ->whereIn('envato_product_id', $ids)
            ->whereDate('captured_on', '>=', $from->copy()->subDay()->toDateString())
            ->whereDate('captured_on', '<=', $to->toDateString())
            ->orderBy('envato_product_id')
            ->orderBy('captured_on')
            ->get(['envato_product_id', 'captured_on', 'number_of_sales']);

        $out = collect();
        $previous = [];

        foreach ($rows as $row) {
            $id = (int) $row->envato_product_id;
            $total = (int) $row->number_of_sales;
            $date = Carbon::parse($row->captured_on)->toDateString();

            if (isset($previous[$id]) && $date >= $from->toDateString()) {
                $out->push([
                    'product_id' => $id,
                    'date' => $date,
                    // A total that goes down means the item was reset or delisted;
                    // reporting a negative sale would be worse than reporting none.
                    'sold' => max(0, $total - $previous[$id]),
                    'total' => $total,
                ]);
            }

            $previous[$id] = $total;
        }

        return $out;
    }

    /**
     * Sales per day for a set of products, summed.
     *
     * @return array<string, int> date => units
     */
    public function dailyTotals($products, Carbon $from, Carbon $to): array
    {
        return $this->daily($products, $from, $to)
            ->groupBy('date')
            ->map(fn ($rows) => (int) $rows->sum('sold'))
            ->sortKeys()
            ->all();
    }

    /**
     * How much each product sold over the window, best first.
     *
     * @return array<int, int> product_id => units
     */
    public function perProduct($products, Carbon $from, Carbon $to): array
    {
        return $this->daily($products, $from, $to)
            ->groupBy('product_id')
            ->map(fn ($rows) => (int) $rows->sum('sold'))
            ->sortDesc()
            ->all();
    }

    /**
     * What each product has sold so far today, and where that is measured from.
     *
     * Preferring yesterday's close makes this a true full-day figure. Falling back
     * to today's opening covers the first day of tracking and any day after a
     * missed sync — partial, and flagged as such, but far better than a blank.
     *
     * @return array<int, array{sold: int, since: string, from: string}>
     *         keyed by product id; `since` is 'yesterday' or 'opening'
     */
    public function today($products): array
    {
        $ids = $products instanceof Collection ? $products->pluck('id')->all() : (array) $products;

        if (! $ids) {
            return [];
        }

        $rows = DB::table('envato_snapshots')
            ->whereIn('envato_product_id', $ids)
            ->whereDate('captured_on', '>=', today()->copy()->subDay()->toDateString())
            ->whereDate('captured_on', '<=', today()->toDateString())
            ->get(['envato_product_id', 'captured_on', 'number_of_sales', 'opening_sales', 'created_at']);

        $yesterday = today()->copy()->subDay()->toDateString();
        $closes = [];
        $todays = [];

        foreach ($rows as $row) {
            $id = (int) $row->envato_product_id;
            if (Carbon::parse($row->captured_on)->toDateString() === $yesterday) {
                $closes[$id] = (int) $row->number_of_sales;
            } else {
                $todays[$id] = $row;
            }
        }

        $out = [];
        foreach ($todays as $id => $row) {
            $baseline = $closes[$id] ?? $row->opening_sales;
            if ($baseline === null) {
                continue;
            }

            $out[$id] = [
                // A total that drops means a reset or delisting, not a negative sale.
                'sold' => max(0, (int) $row->number_of_sales - (int) $baseline),
                'since' => isset($closes[$id]) ? 'yesterday' : 'opening',
                'from' => (string) $row->created_at,
            ];
        }

        return $out;
    }

    /** Total sold today across a set of products. */
    public function soldToday($products): int
    {
        return array_sum(array_column($this->today($products), 'sold'));
    }

    /**
     * Whether we have any snapshot history at all yet.
     *
     * Worth checking before drawing an empty chart: "no sales" and "the sync has
     * only ever run once" look identical on screen but mean different things.
     */
    public function hasHistory(): bool
    {
        return DB::table('envato_snapshots')
            ->distinct()
            ->count('captured_on') > 1;
    }
}
