<?php

namespace App\Services\Envato;

use App\Models\EnvatoSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the OFFICIAL Envato API (https://api.envato.com).
 * Every call is authenticated with a personal token — the API has no anonymous access.
 * Rate limits are dynamic and undocumented, so we honour 429 + Retry-After and cache reads.
 */
class EnvatoClient
{
    private const BASE = 'https://api.envato.com';

    /** Public catalog data barely moves; caching keeps us well under the dynamic rate limit. */
    private const CACHE_TTL = 1800; // 30 min

    public function __construct(private ?string $token = null)
    {
        $this->token = $token ?: EnvatoSetting::current()->personal_token;
    }

    public function configured(): bool
    {
        return filled($this->token);
    }

    /** Verify the token and return the username it belongs to. */
    public function verify(): string
    {
        return (string) ($this->get('/v1/market/private/user/username.json', [], false)['username'] ?? '');
    }

    /** Public profile of any author. */
    public function author(string $username): ?array
    {
        return $this->get('/v1/market/user:'.rawurlencode($username).'.json')['user'] ?? null;
    }

    public function authorBadges(string $username): array
    {
        return $this->get('/v1/market/user-badges:'.rawurlencode($username).'.json')['user-badges'] ?? [];
    }

    /** [site => item count] for an author. */
    public function authorItemCounts(string $username): array
    {
        $rows = $this->get('/v1/market/user-items-by-site:'.rawurlencode($username).'.json')['user-items-by-site'] ?? [];

        return collect($rows)->pluck('items', 'site')->map(fn ($n) => (int) $n)->all();
    }

    /** Full item record by id. */
    public function item(int $id): ?array
    {
        return $this->get('/v3/market/catalog/item', ['id' => $id]) ?: null;
    }

    /**
     * Search the catalog. The API caps this at page 60 × page_size 100 (6,000 results).
     * Pass username to walk one author's portfolio.
     */
    public function search(array $params = []): array
    {
        return $this->get('/v1/discovery/search/search/item', array_merge([
            'site' => 'codecanyon.net',
            'page_size' => 100,
            'sort_by' => 'sales',
            'sort_direction' => 'desc',
        ], $params));
    }

    /** Every CodeCanyon item for one author (paginated, respecting the API's 60-page ceiling). */
    public function authorItems(string $username, int $maxPages = 60): array
    {
        $all = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $res = $this->search(['username' => $username, 'page' => $page]);
            $matches = $res['matches'] ?? [];
            $all = array_merge($all, $matches);
            if (count($matches) < 100) {
                break;
            }
        }

        return $all;
    }

    /** Top sellers on a site (last week / last three months / top authors). */
    public function popular(string $site = 'codecanyon'): array
    {
        return $this->get('/v1/market/popular:'.$site.'.json')['popular'] ?? [];
    }

    public function categories(string $site = 'codecanyon'): array
    {
        return $this->get('/v1/market/categories:'.$site.'.json')['categories'] ?? [];
    }

    /** Buyer comments on an item — the API exposes no individual star reviews, only comments. */
    public function comments(int $itemId, int $page = 1): array
    {
        return $this->get('/v1/discovery/search/search/comment', ['item_id' => $itemId, 'page' => $page]);
    }

    // ------------------------------------------------------------------ internals

    private function get(string $path, array $query = [], bool $cache = true): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Envato personal token is not set. Add it under Settings → CodeCanyon Config.');
        }

        $key = 'envato:'.md5($path.serialize($query));
        if ($cache && ($hit = Cache::get($key)) !== null) {
            return $hit;
        }

        $response = $this->request($path, $query);
        $data = $response->json() ?? [];

        if ($cache) {
            Cache::put($key, $data, self::CACHE_TTL);
        }

        return $data;
    }

    /** One call, retrying on 429 for as long as Retry-After asks (bounded). */
    private function request(string $path, array $query, int $attempt = 1): Response
    {
        $response = Http::withToken($this->token)
            ->acceptJson()
            ->withUserAgent('RazinSoft Admin — CodeCanyon market analysis')
            ->timeout(20)
            ->get(self::BASE.$path, $query);

        if ($response->status() === 429 && $attempt <= 3) {
            $wait = min(60, max(1, (int) $response->header('Retry-After')));
            sleep($wait);

            return $this->request($path, $query, $attempt + 1);
        }

        if ($response->status() === 403 || $response->status() === 401) {
            throw new RuntimeException('Envato rejected the token (HTTP '.$response->status().'). Check it under Settings → CodeCanyon Config.');
        }
        if ($response->failed()) {
            throw new RuntimeException('Envato API error (HTTP '.$response->status().').');
        }

        return $response;
    }
}
