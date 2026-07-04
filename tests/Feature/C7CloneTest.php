<?php
namespace Tests\Feature;
use App\Models\Product; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class C7CloneTest extends TestCase {
    use RefreshDatabase;
    public function test_clone_copies_relations_and_resets_counters(): void {
        $admin = User::create(['name'=>'A','email'=>'c7@test.local','password'=>'password','role'=>'admin']);
        $p = Product::create(['name'=>'Ready X','slug'=>'ready-x','tagline'=>'T','version'=>'1.0','status'=>'published','is_featured'=>true,'rating'=>4.8,'reviews_count'=>12,'sales_count'=>430,'currency'=>'USD']);
        $p->plans()->create(['name'=>'Basic','price'=>100,'sort_order'=>0]);
        $p->plans()->create(['name'=>'Pro','price'=>200,'sort_order'=>1]);
        $p->features()->create(['title'=>'Fast','sort_order'=>0]);
        $p->faqs()->create(['question'=>'Q1','answer'=>'A1','sort_order'=>0]);
        $g = $p->galleryGroups()->create(['name'=>'Web','sort_order'=>0]);
        $g->images()->create(['image'=>'a.jpg','sort_order'=>0]);
        $p->seo()->create(['seo_title'=>'X SEO']);
        // relations NOT cloned:
        $p->reviews()->create(['author_name'=>'R','rating'=>5,'is_approved'=>true]);
        $p->questions()->create(['name'=>'U','question'=>'hi','is_public'=>true]);

        $this->actingAs($admin)->post("/admin/products/{$p->id}/clone")->assertRedirect();

        $clone = Product::where('slug','ready-x-copy')->first();
        $this->assertNotNull($clone, 'clone created with -copy slug');
        $this->assertEquals('Ready X (Copy)', $clone->name);
        $this->assertEquals('draft', $clone->status, 'status reset to draft');
        $this->assertFalse((bool)$clone->is_featured, 'featured reset');
        $this->assertEquals(0, (int)$clone->reviews_count);
        $this->assertEquals(0, (int)$clone->sales_count);
        $this->assertEquals(0.0, (float)$clone->rating);
        // relations copied
        $this->assertEquals(2, $clone->plans()->count(), 'plans copied');
        $this->assertEquals(1, $clone->features()->count());
        $this->assertEquals(1, $clone->faqs()->count());
        $this->assertEquals(1, $clone->galleryGroups()->count());
        $this->assertEquals(1, $clone->galleryGroups->first()->images()->count(), 'gallery images copied');
        $this->assertNotNull($clone->seo, 'seo copied');
        // NOT copied
        $this->assertEquals(0, $clone->reviews()->count(), 'reviews NOT copied');
        $this->assertEquals(0, $clone->questions()->count(), 'questions NOT copied');
        // originals untouched
        $this->assertEquals(12, (int)$p->fresh()->reviews_count);
    }
}
