<?php
namespace Tests\Feature;
use App\Models\InvoiceTemplate; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class D5TemplateTest extends TestCase {
    use RefreshDatabase;
    public function test_templates(): void {
        $admin = User::create(['name'=>'A','email'=>'d5@test.local','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);
        // create template
        $this->post('/admin/invoice-templates',['name'=>'Bundle','currency'=>'USD','notes'=>'Thanks','items'=>[['description'=>'Website','qty'=>1,'unit_price'=>2500,'discount_percent'=>0,'tax_percent'=>10],['description'=>'Hosting','qty'=>1,'unit_price'=>150,'discount_percent'=>0,'tax_percent'=>10]]])->assertRedirect();
        $tpl = InvoiceTemplate::first();
        $this->assertEquals(2, count($tpl->items));
        // list
        $this->get('/admin/invoice-templates')->assertOk()->assertSee('Bundle');
        // create invoice prefilled from template
        $this->get("/admin/invoices/create?template={$tpl->id}")->assertOk()->assertSee('Website')->assertSee('Hosting')->assertSee('2500');
    }
}
