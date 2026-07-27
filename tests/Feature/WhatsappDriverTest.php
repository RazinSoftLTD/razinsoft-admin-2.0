<?php

namespace Tests\Feature;

use App\Models\WhatsappAccount;
use App\Services\Whatsapp\BaileysProvider;
use App\Services\Whatsapp\CloudApiProvider;
use App\Services\Whatsapp\WhatsappManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The driver belongs to the number, so QR and Cloud API numbers can be connected at once. */
class WhatsappDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_number_gets_the_transport_it_was_configured_with(): void
    {
        $qr = $this->account(['name' => 'Support', 'driver' => 'baileys', 'session_key' => 'acc-1']);
        $cloud = $this->account([
            'name' => 'Sales', 'driver' => 'cloud_api', 'session_key' => 'cloud-1',
            'phone_number_id' => '123', 'access_token' => 'tok',
        ]);

        $manager = app(WhatsappManager::class);

        $this->assertInstanceOf(BaileysProvider::class, $manager->provider(null, $qr->session_key, $qr));
        $this->assertInstanceOf(CloudApiProvider::class, $manager->provider(null, $cloud->session_key, $cloud));
    }

    public function test_cloud_api_credentials_are_encrypted_at_rest(): void
    {
        $account = $this->account([
            'driver' => 'cloud_api', 'session_key' => 'cloud-2',
            'phone_number_id' => '123', 'access_token' => 'super-secret', 'app_secret' => 'app-secret',
        ]);

        $raw = \DB::table('whatsapp_accounts')->where('id', $account->id)->first();

        $this->assertNotSame('super-secret', $raw->access_token, 'The token must not be readable in the database.');
        $this->assertSame('super-secret', $account->fresh()->access_token);
    }

    public function test_the_webhook_handshake_matches_the_right_number(): void
    {
        $this->account(['driver' => 'cloud_api', 'session_key' => 'cloud-3', 'phone_number_id' => '1', 'verify_token' => 'token-a']);
        $this->account(['driver' => 'cloud_api', 'session_key' => 'cloud-4', 'phone_number_id' => '2', 'verify_token' => 'token-b']);

        // Either number's token is accepted — they share one callback URL.
        foreach (['token-a', 'token-b'] as $token) {
            $this->get("/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token={$token}&hub_challenge=ping")
                ->assertOk()->assertSee('ping');
        }

        $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=ping')
            ->assertForbidden();
    }

    public function test_a_qr_number_is_not_treated_as_cloud_api(): void
    {
        $account = $this->account(['driver' => 'baileys', 'session_key' => 'acc-2']);

        $this->assertFalse($account->isCloudApi());
        $this->assertFalse($account->isConnected(), 'No session state means not connected.');
    }

    private function account(array $attributes = []): WhatsappAccount
    {
        return WhatsappAccount::create($attributes + [
            'name' => 'Number', 'driver' => 'baileys', 'color' => '#25d366',
            'session_key' => 'acc-'.uniqid(), 'position' => 1,
        ]);
    }

    public function test_the_24_hour_window_only_applies_to_cloud_api_numbers(): void
    {
        $qr = $this->account(['driver' => 'baileys', 'session_key' => 'acc-w1']);
        $cloud = $this->account(['driver' => 'cloud_api', 'session_key' => 'cloud-w1', 'phone_number_id' => '1', 'access_token' => 't']);

        // A paired phone may write whenever it likes, even with nothing incoming at all.
        $this->assertFalse($this->chat($qr)->needsTemplate());

        // A Cloud API chat with no inbound message has never opened a window.
        $this->assertTrue($this->chat($cloud)->needsTemplate());
    }

    public function test_the_window_opens_on_an_inbound_message_and_closes_after_a_day(): void
    {
        $cloud = $this->account(['driver' => 'cloud_api', 'session_key' => 'cloud-w2', 'phone_number_id' => '1', 'access_token' => 't']);

        $fresh = $this->chat($cloud, '8801700000001');
        $fresh->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'hi', 'status' => 'received', 'sent_at' => now()->subHours(2)]);
        $this->assertFalse($fresh->fresh()->needsTemplate());

        $stale = $this->chat($cloud, '8801700000002');
        $stale->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'hi', 'status' => 'received', 'sent_at' => now()->subDays(2)]);
        $this->assertTrue($stale->fresh()->needsTemplate());

        // Our own replies do not reopen it — only the customer writing does.
        $stale->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'hello', 'status' => 'sent', 'sent_at' => now()]);
        $this->assertTrue($stale->fresh()->needsTemplate());
    }

    public function test_a_qr_number_has_no_templates_to_offer(): void
    {
        $qr = $this->account(['driver' => 'baileys', 'session_key' => 'acc-w2']);

        $this->assertSame([], app(WhatsappManager::class)->provider(null, $qr->session_key, $qr)->templates());
    }

    private function chat(WhatsappAccount $account, string $waId = '8801700000000'): \App\Models\WhatsappChat
    {
        return \App\Models\WhatsappChat::create([
            'wa_id' => $waId, 'account_id' => $account->id, 'status' => 'open', 'unread_count' => 0,
        ]);
    }

    public function test_a_cloud_api_number_works_with_no_qr_gateway_configured(): void
    {
        // No gateway URL anywhere — the old global check would have blocked this number.
        \App\Models\WhatsappSetting::current()->update(['gateway_url' => null]);

        $cloud = $this->account([
            'driver' => 'cloud_api', 'session_key' => 'cloud-g1',
            'phone_number_id' => '1', 'access_token' => 'tok',
        ]);
        $qr = $this->account(['driver' => 'baileys', 'session_key' => 'acc-g1']);

        $this->assertTrue($cloud->isConfigured(), 'A Cloud API number carries its own credentials.');
        $this->assertFalse($qr->isConfigured(), 'A QR number still needs the shared gateway.');
    }
}
