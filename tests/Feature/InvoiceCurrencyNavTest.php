<?php
namespace Tests\Feature;
use App\Models\User; use App\Models\ClientInvoice; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class InvoiceCurrencyNavTest extends TestCase {
    use RefreshDatabase;
    public function test_currency_dropdown_and_grouped_sidebar(): void {
        $admin = User::firstOrCreate(['email'=>'nav@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);

        // invoice create form: currency <select> lists every supported currency
        $form = $this->get('/admin/invoices/create')->assertOk();
        foreach (array_keys(ClientInvoice::CURRENCIES) as $code) {
            $form->assertSee('value="'.$code.'"', false);
        }
        // new ones beyond the original four are present
        $form->assertSee('value="INR"', false)->assertSee('value="JPY"', false);

        // sidebar: invoice items live inside a collapsible "Invoices" group (not flat)
        $form->assertSee('All Invoices')->assertSee('Templates');
    }
}
