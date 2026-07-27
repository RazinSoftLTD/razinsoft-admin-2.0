<?php

namespace Tests\Feature;

use App\Models\BrandSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The operator's branding overriding what the software shipped with. */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_what_shipped_when_nothing_is_set(): void
    {
        $brand = BrandSetting::current();

        $this->assertSame(config('brand.product'), $brand->productName());
        $this->assertSame(config('brand.tagline'), $brand->taglineText());
    }

    public function test_the_operators_name_wins(): void
    {
        BrandSetting::create(['product' => 'Acme Hub']);

        $this->assertSame('Acme Hub', BrandSetting::current()->productName());
    }

    public function test_the_hover_and_tint_are_derived_from_the_one_colour(): void
    {
        $brand = new BrandSetting(['primary' => '#e11d48']);

        // Darker for hover, near-white for the tint — one picker is enough for anyone, and two
        // that can disagree is how you end up with a clashing hover state.
        $this->assertSame('#e11d48', $brand->primaryColour());
        $this->assertSame('#c6193f', $brand->primaryHoverColour());
        $this->assertSame('#fce8ec', $brand->primarySoftColour());
    }

    public function test_a_saved_change_is_visible_at_once(): void
    {
        BrandSetting::create(['product' => 'First']);
        $this->assertSame('First', BrandSetting::current()->productName());

        // Read on every page, so it is cached — and the cache must not outlive the change.
        BrandSetting::first()->update(['product' => 'Second']);

        $this->assertSame('Second', BrandSetting::current()->productName());
    }

    public function test_only_an_admin_may_change_it(): void
    {
        $staff = \App\Models\User::create([
            'name' => 'Staff', 'email' => 'brand-staff@example.com', 'password' => bcrypt('secret123'),
            'role' => 'staff', 'status' => 'active', 'permissions' => [],
        ]);

        $this->actingAs($staff)->get(route('admin.branding'))->assertForbidden();
    }
}
