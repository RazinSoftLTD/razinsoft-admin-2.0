<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a@a.com', 'password' => Hash::make('x'), 'role' => 'admin']);
    }

    public function test_admin_can_create_user_with_hashed_password(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/users', [
            'name' => 'New Customer', 'email' => 'new@cust.com', 'phone' => '+880123',
            'role' => 'customer', 'password' => 'secret123',
        ])->assertRedirect('/admin/users');

        $user = User::where('email', 'new@cust.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_admin_can_create_a_coupon(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/coupons', ['code' => 'razin15', 'type' => 'percent', 'value' => 15, 'is_active' => '1'])
            ->assertRedirect('/admin/coupons');

        $this->assertDatabaseHas('coupons', ['code' => 'RAZIN15', 'value' => 15]);
    }

    public function test_manual_order_issues_invoice_and_license(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->actingAs($this->admin());

        $customer = User::create(['name' => 'Buyer', 'email' => 'buyer@x.com', 'password' => Hash::make('x'), 'role' => 'customer']);
        $product = Product::create([
            'name' => 'Ready X', 'slug' => 'ready-x', 'tagline' => 'X', 'status' => 'published',
            'price' => 49, 'ext_price' => 249, 'version' => '1.0', 'category' => 'eCommerce',
        ]);
        $plan = Plan::create(['product_id' => $product->id, 'name' => 'Extended', 'price' => 599, 'perks' => ['Unlimited domains']]);

        $this->post('/admin/orders', [
            'user_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'plan_id' => $plan->id, 'qty' => 1]],
            'mark_paid' => '1',
        ])->assertRedirect();

        $order = $customer->orders()->with('items.license', 'invoice')->first();
        $this->assertNotNull($order);
        $this->assertSame('completed', $order->status);
        $this->assertSame('manual', $order->payment_gateway);
        $this->assertNotNull($order->invoice);
        $this->assertSame('Extended', $order->items->first()->license?->plan_name);
    }

    public function test_admin_can_create_a_product(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/products', ['name' => 'New SaaS', 'status' => 'draft', 'currency' => 'USD'])
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['slug' => 'new-saas', 'name' => 'New SaaS', 'status' => 'draft']);
    }

    public function test_admin_can_upload_gallery_image_source_and_thumbnail(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->actingAs($this->admin());

        $product = Product::create([
            'name' => 'Media P', 'slug' => 'media-p', 'tagline' => 'T', 'status' => 'draft',
            'price' => 0, 'ext_price' => 0, 'version' => '1.0', 'category' => 'eCommerce',
        ]);
        $group = $product->galleryGroups()->create(['name' => 'Website']);

        // Gallery image upload
        $this->post("/admin/products/{$product->id}/gallery-images", [
            'gallery_group_id' => $group->id,
            'image' => UploadedFile::fake()->image('shot.png'),
        ])->assertRedirect();
        $this->assertSame(1, $group->images()->count());
        Storage::disk('public')->assertExists($group->images()->first()->image);

        // Source zip upload + is_latest
        $this->post("/admin/products/{$product->id}/files", [
            'version' => '2.0', 'is_latest' => '1', 'file' => UploadedFile::fake()->create('src.zip', 100),
        ])->assertRedirect();
        $file = $product->files()->first();
        $this->assertTrue((bool) $file->is_latest);
        Storage::disk('local')->assertExists($file->file_path);

        // Thumbnail on update
        $this->put("/admin/products/{$product->id}", [
            'name' => 'Media P', 'status' => 'published', 'currency' => 'USD',
            'thumbnail' => UploadedFile::fake()->image('thumb.png'),
        ])->assertRedirect();
        $this->assertNotNull($product->fresh()->thumbnail);
        Storage::disk('public')->assertExists($product->fresh()->thumbnail);
    }

    public function test_admin_can_add_a_plan_to_a_product(): void
    {
        $this->actingAs($this->admin());
        $product = Product::create([
            'name' => 'Ready Y', 'slug' => 'ready-y', 'tagline' => 'Y', 'status' => 'published',
            'price' => 0, 'ext_price' => 0, 'version' => '1.0', 'category' => 'eCommerce',
        ]);

        $this->post("/admin/products/{$product->id}/plans", [
            'name' => 'Standard', 'price' => 299, 'perks' => "Perk A\nPerk B", 'is_popular' => '1',
        ])->assertRedirect();

        $plan = $product->plans()->first();
        $this->assertSame('Standard', $plan->name);
        $this->assertEquals(['Perk A', 'Perk B'], $plan->perks);
        $this->assertTrue((bool) $plan->is_popular);
    }
}
