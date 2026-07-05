<?php
namespace Tests\Feature;
use App\Models\Product; use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage; use Tests\TestCase;

class DemoIconTest extends TestCase {
    use RefreshDatabase;

    public function test_admin_uploads_a_demo_icon_and_api_returns_its_url(): void {
        Storage::fake('public');
        $admin = User::create(['name'=>'A','email'=>'demo-admin@test.local','password'=>'password','role'=>'admin']);
        $product = Product::create(['name'=>'Demo P','slug'=>'demo-p','tagline'=>'x','status'=>'published','price'=>10,'ext_price'=>20,'version'=>'1.0','category'=>'eCommerce']);
        $this->actingAs($admin);

        // add a demo with an uploaded icon
        $this->post("/admin/products/{$product->id}/demos", [
            'type'=>'live', 'title'=>'Live Demo', 'url'=>'https://demo.example.com',
            'icon'=>UploadedFile::fake()->image('rocket.png', 64, 64),
        ])->assertRedirect();

        $demo = $product->demos()->first();
        $this->assertNotNull($demo->icon, 'icon path stored');
        Storage::disk('public')->assertExists($demo->icon);

        // API returns the icon URL
        $res = $this->getJson("/api/products/{$product->slug}")->assertOk();
        $this->assertNotNull($res->json('data.demos.0.icon'), 'API exposes the demo icon URL');
    }

    public function test_demo_without_icon_returns_null_for_website_fallback(): void {
        $admin = User::create(['name'=>'A','email'=>'demo-admin2@test.local','password'=>'password','role'=>'admin']);
        $product = Product::create(['name'=>'Demo Q','slug'=>'demo-q','tagline'=>'x','status'=>'published','price'=>10,'ext_price'=>20,'version'=>'1.0','category'=>'eCommerce']);
        $this->actingAs($admin);

        $this->post("/admin/products/{$product->id}/demos", [
            'type'=>'download', 'title'=>'APK', 'url'=>'https://demo.example.com/app.apk',
        ])->assertRedirect();

        $res = $this->getJson("/api/products/{$product->slug}")->assertOk();
        $this->assertNull($res->json('data.demos.0.icon'), 'no icon → null so the website uses the type preset');
        $this->assertSame('download', $res->json('data.demos.0.type'));
    }
}
