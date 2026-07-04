<?php
namespace Tests\Feature;
use App\Models\User; use App\Models\Currency; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class InvoiceCurrencyNavTest extends TestCase {
    use RefreshDatabase;
    public function test_currency_dropdown_and_grouped_sidebar(): void {
        $admin = User::firstOrCreate(['email'=>'nav@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);

        // invoice create form: currency <select> lists every ACTIVE managed currency
        $form = $this->get('/admin/invoices/create')->assertOk();
        foreach (Currency::options() as $c) {
            $form->assertSee('value="'.$c->code.'"', false);
        }
        $form->assertSee('value="INR"', false)->assertSee('value="JPY"', false);

        // an inactive currency is hidden from the dropdown
        Currency::where('code', 'JPY')->update(['is_active' => false]);
        Currency::flushMap();
        $this->get('/admin/invoices/create')->assertOk()->assertDontSee('value="JPY"', false);

        // sidebar: invoice items live inside a collapsible "Invoices" group (not flat)
        $form->assertSee('All Invoices')->assertSee('Create Invoice')->assertSee('Currencies');

        // invoice item rows have clear column headers
        $form->assertSee('Description')->assertSee('Unit Price')->assertSee('Disc %')->assertSee('Tax %')->assertSee('Amount');
    }

    public function test_can_add_and_delete_a_currency(): void {
        $admin = User::firstOrCreate(['email'=>'nav2@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);

        // manage page loads
        $this->get('/admin/currencies')->assertOk()->assertSee('USD')->assertSee('Add currency');

        // add a custom currency → shows up active in the dropdown
        $this->post('/admin/currencies', ['code' => 'nzd', 'symbol' => 'NZ$', 'name' => 'NZ Dollar'])->assertRedirect();
        $this->assertDatabaseHas('currencies', ['code' => 'NZD', 'symbol' => 'NZ$', 'is_active' => true]);

        // delete it
        $nzd = Currency::where('code', 'NZD')->first();
        $this->delete("/admin/currencies/{$nzd->id}")->assertRedirect();
        $this->assertDatabaseMissing('currencies', ['code' => 'NZD']);
    }
}
