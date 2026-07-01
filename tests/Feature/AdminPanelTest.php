<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a@a.com', 'password' => Hash::make('x'), 'role' => 'admin']);
    }

    public function test_admin_pages_render(): void
    {
        $product = Product::create([
            'name' => 'Sample', 'slug' => 'sample', 'tagline' => 'A sample',
            'status' => 'published', 'price' => 10, 'ext_price' => 20, 'version' => '1.0', 'category' => 'eCommerce',
        ]);
        $product->plans()->create(['name' => 'Basic', 'price' => 99, 'perks' => ['One thing']]);

        $this->actingAs($this->admin());

        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/orders', '/admin/orders/create', '/admin/coupons', '/admin/coupons/create', '/admin/users', '/admin/users/create'] as $url) {
            $this->get($url)->assertOk();
        }

        // Product edit page (tabs + relation sections)
        $this->get("/admin/products/{$product->id}/edit")->assertOk();
    }

    public function test_non_admin_is_redirected(): void
    {
        $customer = User::create(['name' => 'Cust', 'email' => 'c@c.com', 'password' => Hash::make('x'), 'role' => 'customer']);

        $this->actingAs($customer)->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_login_rejects_non_admin(): void
    {
        User::create(['name' => 'Cust', 'email' => 'c2@c.com', 'password' => Hash::make('secret'), 'role' => 'customer']);

        $this->post('/admin/login', ['email' => 'c2@c.com', 'password' => 'secret'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
