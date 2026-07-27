<?php

namespace Tests\Feature;

use App\Models\BillingAddress;
use App\Models\ClientInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Adding a billing address from the public payment link. */
class InvoicePayBillingAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_address_lands_on_the_invoice_and_on_the_client(): void
    {
        [$client, $invoice] = $this->invoice();

        $this->postJson("/api/invoice/pay/{$invoice->public_token}/billing-address", [
            'label' => 'office', 'full_name' => 'Rahim Uddin',
            'address' => '1st Floor, RMR Center', 'city' => 'Dhaka', 'state' => 'Dhaka',
            'zip' => '1207', 'country' => 'Bangladesh',
        ])->assertOk();

        // On the invoice, as the one line the PDF and the panel show.
        $this->assertSame(
            '1st Floor, RMR Center, Dhaka, Dhaka, 1207, Bangladesh',
            $invoice->fresh()->bill_to_address,
        );

        // And kept against the client, so nobody is asked a second time.
        $saved = BillingAddress::where('user_id', $client->id)->firstOrFail();
        $this->assertSame('office', $saved->label);
        $this->assertSame('1207', $saved->zip);
        $this->assertTrue($saved->is_default, 'Their first address is their default.');
    }

    public function test_an_address_already_on_the_invoice_cannot_be_rewritten(): void
    {
        [, $invoice] = $this->invoice(['bill_to_address' => 'Somewhere already']);

        $this->postJson("/api/invoice/pay/{$invoice->public_token}/billing-address", [
            'address' => 'Elsewhere', 'zip' => '1207', 'country' => 'Bangladesh',
        ])->assertStatus(422);

        // A link must not be able to edit what is already on a financial record.
        $this->assertSame('Somewhere already', $invoice->fresh()->bill_to_address);
    }

    public function test_a_settled_invoice_is_left_alone(): void
    {
        [, $invoice] = $this->invoice(['status' => 'paid', 'amount_paid' => 1000]);

        $this->postJson("/api/invoice/pay/{$invoice->public_token}/billing-address", [
            'address' => 'Too late', 'zip' => '1207', 'country' => 'Bangladesh',
        ])->assertStatus(422);
    }

    public function test_a_wrong_token_finds_nothing(): void
    {
        $this->postJson('/api/invoice/pay/not-a-real-token/billing-address', [
            'address' => 'x', 'zip' => '1', 'country' => 'Bangladesh',
        ])->assertNotFound();
    }

    /** @return array{0: User, 1: ClientInvoice} */
    private function invoice(array $attributes = []): array
    {
        $client = User::create([
            'name' => 'Rahim', 'email' => 'rahim'.uniqid().'@example.com',
            'password' => bcrypt('secret123'), 'role' => 'customer', 'status' => 'active',
        ]);

        return [$client, ClientInvoice::create($attributes + [
            'invoice_number' => 'INV-'.uniqid(),
            'client_id' => $client->id,
            'bill_to_name' => 'Rahim',
            'bill_to_email' => $client->email,
            'currency' => 'BDT',
            'total' => 1000,
            'status' => 'sent',
            'invoice_date' => now(),
        ])];
    }
}
