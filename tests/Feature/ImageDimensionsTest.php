<?php
namespace Tests\Feature;
use App\Models\Author; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage; use Tests\TestCase;

class ImageDimensionsTest extends TestCase {
    use RefreshDatabase;

    public function test_specs_build_ratio_and_min_rules(): void {
        $this->assertSame('dimensions:ratio=3/2,min_width=900,min_height=600', \App\Support\ImageSpecs::rule('product'));
        $this->assertSame('dimensions:ratio=1/1,min_width=400,min_height=400', \App\Support\ImageSpecs::rule('avatar'));
        $this->assertStringContainsString('square', \App\Support\ImageSpecs::hint('avatar'));
    }

    public function test_product_thumbnail_must_be_3_2(): void {
        Storage::fake('public');
        $admin = User::create(['name'=>'A','email'=>'img-admin@test.local','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);

        // wrong ratio (square) → rejected
        $this->post('/admin/products', ['name'=>'Bad','thumbnail'=>UploadedFile::fake()->image('t.jpg', 800, 800)])
            ->assertSessionHasErrors('thumbnail');

        // too small (right ratio but under min) → rejected
        $this->post('/admin/products', ['name'=>'Small','thumbnail'=>UploadedFile::fake()->image('t.jpg', 300, 200)])
            ->assertSessionHasErrors('thumbnail');

        // correct 3:2 at/above min → accepted
        $this->post('/admin/products', ['name'=>'Good','thumbnail'=>UploadedFile::fake()->image('t.jpg', 1200, 800)])
            ->assertSessionDoesntHaveErrors('thumbnail');
        $this->assertDatabaseHas('products', ['name'=>'Good']);
    }

    public function test_author_photo_must_be_square(): void {
        Storage::fake('public');
        $admin = User::create(['name'=>'A','email'=>'img-admin2@test.local','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);

        // non-square → rejected
        $this->post('/admin/authors', ['name'=>'Jane','photo'=>UploadedFile::fake()->image('p.jpg', 800, 600)])
            ->assertSessionHasErrors('photo');

        // square ≥ 400 → accepted
        $this->post('/admin/authors', ['name'=>'Joe','photo'=>UploadedFile::fake()->image('p.jpg', 400, 400)])
            ->assertSessionDoesntHaveErrors('photo');
        $this->assertDatabaseHas('authors', ['name'=>'Joe']);
    }
}
