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
}
