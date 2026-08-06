<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use libphonenumber\PhoneNumberUtil;

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
     * Customer prices per product key: first-year registration, yearly renewal, and transfer.
     *
     * Their response nests price by action and then by tenure in years, as strings. The renewal
     * price matters as much as the first year's — a $3.99 name that renews at $34 is the classic
     * surprise, and showing both up front is what stops it being one.
     *
     * @return array<string, array{register?: float, renew?: float, transfer?: float}>
     */
    public function prices(): array
    {
        if (! $this->configured()) {
            return [];
        }

        return Cache::remember('resellerclub.prices.v2', self::PRICE_TTL, function () {
            $raw = $this->get('products/customer-price.json');

            $prices = [];
            foreach ($raw as $classkey => $actions) {
                if (! is_array($actions)) {
                    continue;
                }
                $row = [];
                foreach (['register' => 'addnewdomain', 'renew' => 'renewdomain', 'transfer' => 'addtransferdomain'] as $ours => $theirs) {
                    $year = $actions[$theirs]['1'] ?? null;
                    if (is_numeric($year)) {
                        $row[$ours] = round((float) $year, 2);
                    }
                }
                if ($row) {
                    $prices[$classkey] = $row;
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
        $prices = $this->prices();

        return array_map(function ($r) use ($prices) {
            $p = $r['classkey'] !== null ? ($prices[$r['classkey']] ?? []) : [];

            return [
                'domain' => $r['domain'],
                'tld' => $r['tld'],
                'available' => $r['available'],
                // Null, not zero: a missing price means we do not know it, and 0.00 reads as free.
                'price' => $p['register'] ?? null,
                'renew' => $p['renew'] ?? null,
                'transfer' => $p['transfer'] ?? null,
            ];
        }, $rows);
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

    /**
     * The ResellerClub customer for this email, created if they are new.
     *
     * Every registration hangs off a customer record on their side. One per email: registering a
     * second domain for the same person must land on the same customer, or their renewals and
     * transfers end up scattered across accounts nobody can find.
     */
    public function ensureCustomer(string $email, string $name, array $address): string
    {
        $existing = $this->get('customers/details.json', ['username' => $email]);
        if (isset($existing['customerid'])) {
            return (string) $existing['customerid'];
        }

        [$phoneCc, $phone] = $this->splitPhone($address['phone']);

        $created = $this->post('customers/v2/signup.json', [
            'username' => $email,
            // They require a password; the customer never logs in to ResellerClub, so it is
            // random and thrown away. Access goes through us.
            'passwd' => Str::password(15, symbols: false).'9a',
            'name' => $name,
            'company' => $address['company'] ?: 'None',
            'address-line-1' => $address['address'],
            'city' => $address['city'],
            'state' => $address['state'] ?? 'Other',
            'other-state' => $address['state'] ?? 'Other',
            'country' => strtoupper($address['country']),
            'zipcode' => $address['zip'],
            'phone-cc' => $phoneCc,
            'phone' => $phone,
            'lang-pref' => 'en',
        ]);

        $id = $created['customerid'] ?? (is_scalar($created['raw'] ?? null) ? $created['raw'] : null);

        if (! $id) {
            throw new \RuntimeException('ResellerClub did not return a customer id: '.json_encode($created));
        }

        return (string) $id;
    }

    /** A registrant contact under the customer — the name a domain is legally registered to. */
    public function createContact(string $customerId, string $email, string $name, array $address): string
    {
        [$phoneCc, $phone] = $this->splitPhone($address['phone']);

        $created = $this->post('contacts/add.json', [
            'customer-id' => $customerId,
            'type' => 'Contact',
            'email' => $email,
            'name' => $name,
            'company' => $address['company'] ?: 'None',
            'address-line-1' => $address['address'],
            'city' => $address['city'],
            'country' => strtoupper($address['country']),
            'zipcode' => $address['zip'],
            'phone-cc' => $phoneCc,
            'phone' => $phone,
        ]);

        $id = is_scalar($created['raw'] ?? null) ? $created['raw'] : ($created['contactid'] ?? null);

        if (! $id) {
            throw new \RuntimeException('ResellerClub did not return a contact id: '.json_encode($created));
        }

        return (string) $id;
    }

    /**
     * Register the domain. Returns their order id.
     *
     * invoice-option NoInvoice: the money was already taken on our site, so their side must not
     * raise a second bill for the same registration.
     */
    public function registerDomain(string $domain, int $years, string $customerId, string $contactId): string
    {
        $result = $this->post('domains/register.json', [
            'domain-name' => $domain,
            'years' => $years,
            'ns' => $this->nameservers(),
            'customer-id' => $customerId,
            'reg-contact-id' => $contactId,
            'admin-contact-id' => $contactId,
            'tech-contact-id' => $contactId,
            'billing-contact-id' => $contactId,
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => false,
        ]);

        $status = strtolower((string) ($result['actionstatus'] ?? $result['status'] ?? ''));

        if (! in_array($status, ['success', 'pendingexecution'], true)) {
            throw new \RuntimeException('ResellerClub refused the registration: '.json_encode($result));
        }

        return (string) ($result['entityid'] ?? $result['eaqid'] ?? 'unknown');
    }

    /** @return string[] */
    public function nameservers(): array
    {
        $ns = array_values(array_filter(array_map('trim', explode(',', (string) config('services.resellerclub.nameservers')))));

        // Their own OrderBox DNS — free with every registration, so a domain never sits without
        // working nameservers just because none were configured on our side.
        return $ns ?: ['mercury.orderbox-dns.com', 'venus.orderbox-dns.com'];
    }

    /** ["880", "1711…"] via libphonenumber; a number that will not parse keeps a plain split. */
    private function splitPhone(string $raw): array
    {
        $digits = preg_replace('/\D/', '', $raw);

        try {
            $util = PhoneNumberUtil::getInstance();
            $proto = $util->parse('+'.ltrim($digits, '0'), null);
            if ($util->isValidNumber($proto)) {
                return [(string) $proto->getCountryCode(), (string) $proto->getNationalNumber()];
            }
        } catch (\Throwable) {
        }

        return ['1', $digits ?: '0000000'];
    }

    /**
     * POST for the calls that change things. Their errors still arrive as HTTP 200 with a status
     * of ERROR — but unlike reads, a mutation must throw: returning [] would read as success.
     *
     * @return array<string, mixed>
     */
    private function post(string $path, array $params): array
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post($this->base().'/'.$path.'?'.http_build_query([
                'auth-userid' => $this->userId,
                'api-key' => $this->apiKey,
            ]), $params);

        $data = $response->json();

        if (! $response->successful() || ! is_array($data) && ! is_scalar($data)) {
            throw new \RuntimeException("ResellerClub {$path} failed: HTTP {$response->status()}");
        }

        // A bare scalar (a customer id, a contact id) is a valid answer for some calls.
        if (is_scalar($data)) {
            return ['raw' => $data];
        }

        if (strtoupper((string) ($data['status'] ?? '')) === 'ERROR') {
            throw new \RuntimeException('ResellerClub error: '.($data['message'] ?? json_encode($data)));
        }

        return $data;
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
