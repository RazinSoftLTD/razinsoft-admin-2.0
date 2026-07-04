<?php
namespace Tests\Feature;
use App\Models\ClientInvoice; use App\Models\User; use Illuminate\Support\Str; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class InvoiceLogoTest extends TestCase {
    use RefreshDatabase;
    public function test_pdf_embeds_logo_image(): void {
        $admin = User::firstOrCreate(['email'=>'logo@test.local'],['name'=>'A','password'=>'password','role'=>'admin']);
        $inv = ClientInvoice::create(['invoice_number'=>'INV-LOGO-1','public_token'=>Str::random(40),'bill_to_name'=>'X','invoice_date'=>date('Y-m-d'),'currency'=>'USD','status'=>'sent','subtotal'=>100,'total'=>100,'notes'=>'Thanks for your business','terms'=>'Net 30 days','created_by'=>$admin->id]);
        $inv->items()->create(['description'=>'Item','qty'=>1,'unit_price'=>100,'amount'=>100]);

        // logo asset must exist so the PDF embeds an image (not the text fallback)
        $this->assertFileExists(public_path('images/razinsoft-logo-print.png'), 'print logo asset present');

        // rendered HTML carries the <img data:image/png;base64> logo
        $html = view('admin.invoices.pdf', ['invoice' => $inv->load('items','payments')])->render();
        $this->assertStringContainsString('data:image/png;base64,', $html, 'logo embedded as base64');
        $this->assertStringNotContainsString('class="brand">RazinSoft', $html, 'text brand fallback not used');

        // notes (left column) + terms (right column) side by side, both text left-aligned
        $this->assertStringContainsString('Thanks for your business', $html);
        $this->assertStringContainsString('Net 30 days', $html);
        $this->assertStringContainsString('width:50%;vertical-align:top;padding-left:12px"', $html, 'terms in the right column');
        $this->assertStringNotContainsString('padding-left:12px" class="right"', $html, 'terms text not right-aligned');

        // dompdf actually produces a PDF with the image without erroring
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
        $this->assertStringStartsWith('%PDF', $pdf, 'valid PDF generated');
    }
}
