<?php
namespace Tests\Feature;
use App\Models\Product; use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage; use Tests\TestCase;

class HomeAndRatingTest extends TestCase {
    use RefreshDatabase;

    private function product(array $attr = []): Product {
        return Product::create(array_merge(['name'=>'P'.uniqid(),'slug'=>'p-'.uniqid(),'tagline'=>'x','status'=>'published','price'=>10,'ext_price'=>20,'version'=>'1.0','category'=>'eCommerce'], $attr));
    }

    public function test_home_endpoint_returns_only_for_home_products(): void {
        $home = $this->product(['name'=>'Home One','for_home'=>true]);
        $this->product(['name'=>'Not Home','for_home'=>false]);

        $slugs = collect($this->getJson('/api/products?for_home=1')->assertOk()->json('data'))->pluck('slug');
        $this->assertContains($home->slug, $slugs->all());
        $this->assertCount(1, $slugs, 'only the for_home product is returned');
    }

    public function test_home_falls_back_when_nothing_flagged(): void {
        $this->product(['for_home'=>false]);
        $this->product(['for_home'=>false]);
        // no for_home products → API ignores the filter so the homepage is never empty
        $this->assertGreaterThan(0, count($this->getJson('/api/products?for_home=1')->assertOk()->json('data')));
    }

    public function test_admin_can_save_a_fractional_rating_and_for_home(): void {
        Storage::fake('public');
        $admin = User::create(['name'=>'A','email'=>'pr-admin@test.local','password'=>'password','role'=>'admin']);
        $p = $this->product();
        $this->actingAs($admin);

        $this->put("/admin/products/{$p->id}", [
            'name'=>'Rated','status'=>'published','currency'=>'USD',
            'rating'=>4.5, 'for_home'=>'1', 'sort_order'=>7,
            'thumbnail'=>UploadedFile::fake()->image('t.jpg', 1200, 800),
        ])->assertRedirect();

        $p->refresh();
        $this->assertSame('4.5', (string) $p->rating, 'fractional rating saved');
        $this->assertTrue($p->for_home);
        $this->assertSame(7, $p->sort_order, 'serial (sort_order) saved from admin');
    }

    public function test_storefront_lists_products_in_serial_order(): void {
        $second = $this->product(['name'=>'Beta','sort_order'=>2]);
        $first = $this->product(['name'=>'Alpha','sort_order'=>1]);

        // Default (unsorted) list uses sort_order ascending → the serial the admin sets.
        $slugs = collect($this->getJson('/api/products')->assertOk()->json('data'))->pluck('slug')->all();
        $this->assertLessThan(
            array_search($second->slug, $slugs, true),
            array_search($first->slug, $slugs, true),
            'lower serial appears first',
        );
    }
}
