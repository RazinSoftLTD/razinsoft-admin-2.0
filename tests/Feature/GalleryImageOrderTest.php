<?php
namespace Tests\Feature;
use App\Models\GalleryImage; use App\Models\Product; use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage; use Tests\TestCase;

class GalleryImageOrderTest extends TestCase {
    use RefreshDatabase;

    private function setup3(): array {
        $product = Product::create(['name'=>'Gal','slug'=>'gal','tagline'=>'x','status'=>'published','price'=>10,'ext_price'=>20,'version'=>'1.0','category'=>'eCommerce']);
        $group = $product->galleryGroups()->create(['name'=>'Website']);
        $a = $group->images()->create(['image'=>'products/gallery/a.png','sort_order'=>0]);
        $b = $group->images()->create(['image'=>'products/gallery/b.png','sort_order'=>1]);
        $c = $group->images()->create(['image'=>'products/gallery/c.png','sort_order'=>2]);
        return [$product, $group, [$a,$b,$c]];
    }

    private function order($group): array {
        return $group->images()->pluck('image')->map(fn($p)=>basename($p,'.png'))->all();
    }

    public function test_uploaded_image_appends_to_the_end_of_the_serial(): void {
        Storage::fake('public');
        $admin = User::create(['name'=>'A','email'=>'gal-admin@test.local','password'=>'password','role'=>'admin']);
        [$product,$group] = $this->setup3();
        $this->actingAs($admin);

        $this->post("/admin/products/{$product->id}/gallery-images", [
            'gallery_group_id'=>$group->id, 'image'=>UploadedFile::fake()->image('d.png', 1280, 720),
        ])->assertRedirect();

        $this->assertSame(4, GalleryImage::where('gallery_group_id', $group->id)->count());
        $maxSort = (int) GalleryImage::where('gallery_group_id', $group->id)->max('sort_order');
        $this->assertSame(3, $maxSort, 'new image appended after the existing 3 (sort_order 0,1,2 → 3)');
    }

    public function test_admin_can_reorder_gallery_images_and_api_reflects_it(): void {
        $admin = User::create(['name'=>'A','email'=>'gal-admin2@test.local','password'=>'password','role'=>'admin']);
        [$product,$group,$imgs] = $this->setup3();
        $this->actingAs($admin);
        $this->assertSame(['a','b','c'], $this->order($group));

        // move the 3rd image up → b,a,c? no: move 'c' up swaps with 'b' → a,c,b
        $this->post("/admin/products/{$product->id}/gallery-images/{$imgs[2]->id}/move", ['direction'=>'up'])->assertRedirect();
        $this->assertSame(['a','c','b'], $this->order($group->fresh()));

        // move 'a' (first) down → c,a,b
        $this->post("/admin/products/{$product->id}/gallery-images/{$imgs[0]->id}/move", ['direction'=>'down'])->assertRedirect();
        $this->assertSame(['c','a','b'], $this->order($group->fresh()));

        // API returns the gallery in that exact serial
        $res = $this->getJson("/api/products/{$product->slug}")->assertOk();
        $apiImages = collect($res->json('data.gallery.0.images'))->pluck('image')->map(fn($u)=>basename(parse_url($u,PHP_URL_PATH),'.png'))->all();
        $this->assertSame(['c','a','b'], $apiImages, 'API gallery follows the admin serial');
    }
}
