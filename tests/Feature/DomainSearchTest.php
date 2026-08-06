<?php

namespace Tests\Feature;

use App\Services\ResellerClub;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Domain search against ResellerClub's response shapes.
 *
 * Their API is faked rather than called: the point of these is that we read what they send
 * correctly, including the two ways it can go wrong — an error returned with HTTP 200, and a TLD
 * they simply do not answer for.
 */
class DomainSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.resellerclub.user_id' => '123456',
            'services.resellerclub.api_key' => 'test-key',
            'services.resellerclub.tlds' => 'com,net,org',
        ]);
    }

    /** Availability keys the response by full domain; price comes from the classkey. */
    private function fakeApi(array $availability, array $prices = []): void
    {
        Http::fake([
            '*domains/available.json*' => Http::response($availability),
            '*products/customer-price.json*' => Http::response($prices ?: [
                'domcno' => ['addnewdomain' => ['1' => '10.99', '2' => '21.98']],
                'dotnet' => ['addnewdomain' => ['1' => '12.50']],
            ]),
        ]);
    }

    public function test_it_reports_availability_and_the_first_year_price(): void
    {
        $this->fakeApi([
            'razinsoft.com' => ['status' => 'available', 'classkey' => 'domcno'],
            'razinsoft.net' => ['status' => 'regthroughothers', 'classkey' => 'dotnet'],
            'razinsoft.org' => ['status' => 'available', 'classkey' => 'domcno'],
        ]);

        $res = $this->getJson('/api/domains/search?name=razinsoft')->assertOk()->json();

        $this->assertTrue($res['configured']);
        $this->assertSame('razinsoft', $res['label']);
        $this->assertCount(3, $res['results']);

        $byDomain = collect($res['results'])->keyBy('domain');
        $this->assertTrue($byDomain['razinsoft.com']['available']);
        $this->assertSame(10.99, $byDomain['razinsoft.com']['price']);
        $this->assertFalse($byDomain['razinsoft.net']['available']);
        $this->assertSame(12.5, $byDomain['razinsoft.net']['price']);
    }

    public function test_available_names_are_listed_first(): void
    {
        $this->fakeApi([
            'shop.com' => ['status' => 'regthroughothers', 'classkey' => 'domcno'],
            'shop.net' => ['status' => 'available', 'classkey' => 'dotnet'],
            'shop.org' => ['status' => 'available', 'classkey' => 'domcno'],
        ]);

        $res = $this->getJson('/api/domains/search?name=shop')->assertOk()->json();

        $this->assertTrue($res['results'][0]['available']);
        // Cheapest available leads: .org at 10.99 before .net at 12.50.
        $this->assertSame('shop.org', $res['results'][0]['domain']);
        $this->assertSame('shop.com', $res['results'][2]['domain']);
    }

    /** A pasted URL is what people actually type into a domain box. */
    public function test_it_takes_a_pasted_url(): void
    {
        $this->fakeApi(['myshop.com' => ['status' => 'available', 'classkey' => 'domcno']]);

        $res = $this->getJson('/api/domains/search?name='.urlencode('https://www.MyShop.com/pricing?a=1'))
            ->assertOk()->json();

        $this->assertSame('myshop', $res['label']);
    }

    /** Their errors arrive with HTTP 200, so the body has to be read, not the status code. */
    public function test_an_error_returned_as_200_yields_no_results_rather_than_a_wrong_answer(): void
    {
        Http::fake([
            '*domains/available.json*' => Http::response(['status' => 'ERROR', 'message' => 'Invalid API key']),
            '*products/customer-price.json*' => Http::response([]),
        ]);

        $res = $this->getJson('/api/domains/search?name=razinsoft')->assertOk()->json();

        $this->assertSame([], $res['results']);
    }

    /** A TLD they do not answer for must not be shown as taken — that is a claim we cannot make. */
    public function test_a_tld_with_no_answer_is_left_out(): void
    {
        $this->fakeApi([
            'razinsoft.com' => ['status' => 'available', 'classkey' => 'domcno'],
            // .net and .org missing from the response entirely
        ]);

        $res = $this->getJson('/api/domains/search?name=razinsoft')->assertOk()->json();

        $this->assertCount(1, $res['results']);
        $this->assertSame('razinsoft.com', $res['results'][0]['domain']);
    }

    /** A price we do not have is null, never 0.00 — zero reads as free. */
    public function test_an_unknown_price_is_null(): void
    {
        $this->fakeApi(
            ['razinsoft.com' => ['status' => 'available', 'classkey' => 'unknownkey']],
            ['domcno' => ['addnewdomain' => ['1' => '10.99']]],
        );

        $res = $this->getJson('/api/domains/search?name=razinsoft')->assertOk()->json();

        $this->assertNull($res['results'][0]['price']);
    }

    public function test_only_tlds_we_offer_are_searched(): void
    {
        $this->fakeApi(['razinsoft.com' => ['status' => 'available', 'classkey' => 'domcno']]);

        $this->getJson('/api/domains/search?name=razinsoft&tlds[]=com&tlds[]=rocks')->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'available.json')) {
                return true;
            }

            return str_contains($request->url(), 'tlds%5B0%5D=com') && ! str_contains($request->url(), 'rocks');
        });
    }

    public function test_without_credentials_it_says_so_instead_of_failing(): void
    {
        config(['services.resellerclub.user_id' => null, 'services.resellerclub.api_key' => null]);

        $res = $this->getJson('/api/domains/search?name=razinsoft')->assertOk()->json();

        $this->assertFalse($res['configured']);
        $this->assertSame([], $res['results']);
    }

    public function test_a_one_character_name_is_rejected(): void
    {
        $this->getJson('/api/domains/search?name=a')->assertStatus(422);
    }

    public function test_the_price_list_is_fetched_once_and_reused(): void
    {
        $this->fakeApi([
            'one.com' => ['status' => 'available', 'classkey' => 'domcno'],
        ]);

        $this->getJson('/api/domains/search?name=one')->assertOk();
        $this->getJson('/api/domains/search?name=two')->assertOk();

        $priceCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'customer-price.json'))
            ->count();

        $this->assertSame(1, $priceCalls, 'the price list should be cached between searches');
    }

    /** The reseller price is what we pay; quoting it would sell every domain at cost. */
    public function test_it_asks_for_the_customer_price_list(): void
    {
        $this->fakeApi(['razinsoft.com' => ['status' => 'available', 'classkey' => 'domcno']]);

        $this->getJson('/api/domains/search?name=razinsoft')->assertOk();

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'reseller-price.json'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'customer-price.json')
            || str_contains($request->url(), 'available.json'));
    }

    /** The api-key must never be handed to the browser. */
    public function test_the_api_key_is_not_in_the_response(): void
    {
        $this->fakeApi(['razinsoft.com' => ['status' => 'available', 'classkey' => 'domcno']]);

        $this->getJson('/api/domains/search?name=razinsoft')
            ->assertOk()
            ->assertDontSee('test-key')
            ->assertDontSee('123456');
    }

    public function test_the_service_reports_whether_it_is_configured(): void
    {
        $this->assertTrue(app(ResellerClub::class)->configured());

        config(['services.resellerclub.api_key' => null]);
        $this->assertFalse((new ResellerClub)->configured());
    }
}
