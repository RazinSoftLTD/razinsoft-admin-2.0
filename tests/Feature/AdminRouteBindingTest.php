<?php
namespace Tests\Feature;
use App\Models\Article; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;

class AdminRouteBindingTest extends TestCase {
    use RefreshDatabase;

    /** Regression: Article binds by slug, so the edit route must NOT be whereNumber-constrained. */
    public function test_admin_can_open_article_edit_by_slug(): void {
        $admin = User::create(['name'=>'A','email'=>'rb-admin@test.local','password'=>'password','role'=>'admin']);
        $article = Article::create(['title'=>'AI & Automation','slug'=>'ai-and-automation','status'=>'draft']);
        $this->actingAs($admin);

        $this->get(route('admin.articles.edit', $article))->assertOk();  // /admin/articles/{slug}/edit
        $this->assertStringContainsString('ai-and-automation', route('admin.articles.edit', $article));
    }
}
