<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ResellerClub's HTTP API — availability, suggestions and what a name costs.
 *
 * Credentials live here and only here. The website never calls ResellerClub itself: the api-key is
 * a reseller account key, and anything the browser can read, anyone can read.
 *
 * Prices are the *customer* price list, not the reseller one. Quoting what we pay would undercut
 * the margin on every sale, and the two calls differ by a single word in the path — worth being
 * explicit about which one this is.
 */
class ResellerClub
{
    /** Their live and demo hosts. The demo one takes the same calls against a sandbox account. */
    private const LIVE = 'https://httpapi.com/api';

    private const DEMO = 'https://test.httpapi.com/api';

    /** Price lists change rarely and the call is slow; availability is never cached. */
    private const PRICE_TTL = 3600;

    public function __construct(
        private ?string $userId = null,
        private ?string $apiKey = null,
        private bool $demo = false,
    ) {
        $this->userId ??= config('services.resellerclub.user_id');
        $this->apiKey ??= config('services.resellerclub.api_key');
        $this->demo = $this->demo ?: (bool) config('services.resellerclub.demo');
    }

    /** Whether the panel has been given credentials at all. */
    public function configured(): bool
    {
        return filled($this->userId) && filled($this->apiKey);
    }

    /** The TLDs offered on the website, in the order they should be shown. */
    public function tlds(): array
    {
        return array_values(array_filter(array_map(
            fn ($t) => ltrim(trim($t), '.'),
            explode(',', (string) config('services.resellerclub.tlds')),
        )));
    }

    /**
     * Availability for one label across several TLDs.
     *
     * @param  string  $label  the name without a dot — "razinsoft"
     * @return array<int, array{domain: string, tld: string, available: bool, status: string, classkey: ?string}>
     */
    public function availability(string $label, array $tlds): array
    {
        $label = $this->normaliseLabel($label);

        if ($label === '' || ! $tlds || ! $this->configured()) {
            return [];
        }

        $response = $this->get('domains/available.json', [
            'domain-name' => $label,
            'tlds' => array_values($tlds),
        ]);

        $out = [];
        foreach ($tlds as $tld) {
            $domain = $label.'.'.$tld;
            $row = $response[$domain] ?? null;

            // A TLD their API does not answer for is left out rather than shown as taken —
            // "unavailable" would be a claim we cannot support.
            if (! is_array($row) || ! isset($row['status'])) {
                continue;
            }

            $out[] = [
                'domain' => $domain,
                'tld' => $tld,
                'available' => $row['status'] === 'available',
                'status' => (string) $row['status'],
                'classkey' => $row['classkey'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Customer price to register each product key, for one year, keyed by classkey.
     *
     * Their response nests price by action and then by tenure in years, as strings. Only the
     * first-year registration price is taken — that is the number a search result shows.
     *
     * @return array<string, float>
     */
    public function registrationPrices(): array
    {
        if (! $this->configured()) {
            return [];
        }

        return Cache::remember('resellerclub.prices', self::PRICE_TTL, function () {
            $raw = $this->get('products/customer-price.json');

            $prices = [];
            foreach ($raw as $classkey => $actions) {
                $year = $actions['addnewdomain']['1'] ?? null;
                if (is_numeric($year)) {
                    $prices[$classkey] = round((float) $year, 2);
                }
            }

            return $prices;
        });
    }

    /**
     * Availability with the price already attached, ready to show.
     *
     * @return array<int, array{domain: string, tld: string, available: bool, price: ?float}>
     */
    public function search(string $label, ?array $tlds = null): array
    {
        $tlds ??= $this->tlds();
        $rows = $this->availability($label, $tlds);
        $prices = $this->registrationPrices();

        return array_map(fn ($r) => [
            'domain' => $r['domain'],
            'tld' => $r['tld'],
            'available' => $r['available'],
            // Null, not zero: a missing price means we do not know it, and 0.00 reads as free.
            'price' => $r['classkey'] !== null ? ($prices[$r['classkey']] ?? null) : null,
        ], $rows);
    }

    /**
     * Strip a name down to the label ResellerClub expects.
     *
     * People paste "https://www.MyShop.com/" into a domain box constantly. Sending that through
     * returns nothing at all, which reads as "unavailable" to whoever typed it.
     */
    public function normaliseLabel(string $input): string
    {
        $value = Str::lower(trim($input));
        $value = preg_replace('#^[a-z]+://#', '', $value);   // scheme
        $value = preg_replace('#[/?].*$#', '', $value);      // path or query
        $value = preg_replace('#^www\.#', '', $value);
        $value = Str::before($value, '.');                   // the label only

        return preg_replace('/[^a-z0-9-]/', '', (string) $value);
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $query = []): array
    {
        $response = Http::timeout(15)
            ->retry(2, 200, throw: false)
            ->get($this->base().'/'.$path, array_merge([
                'auth-userid' => $this->userId,
                'api-key' => $this->apiKey,
            ], $query));

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();

        // Their errors come back with HTTP 200 and a status of ERROR, so the body has to be read
        // rather than the status code.
        if (! is_array($data) || (($data['status'] ?? null) === 'ERROR')) {
            return [];
        }

        return $data;
    }

    private function base(): string
    {
        // Overridable so the integration can be pointed at a stand-in during testing. Server
        // config only — nothing user-supplied ever reaches it.
        return rtrim((string) config('services.resellerclub.base_url'), '/')
            ?: ($this->demo ? self::DEMO : self::LIVE);
    }
}
