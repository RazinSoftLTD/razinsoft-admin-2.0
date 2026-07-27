<?php

namespace Tests\Feature;

use App\Models\MetaCapiSetting;
use App\Services\Meta\ConversionsApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaCapiTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_sent_while_the_integration_is_off(): void
    {
        Http::fake();
        $this->settings(['is_enabled' => false]);

        $this->assertFalse(ConversionsApi::make()->send('Purchase', 'order-1', ['value' => 10]));

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_for_an_event_that_was_switched_off(): void
    {
        Http::fake();
        $this->settings(['events' => ['Lead']]);

        $this->assertFalse(ConversionsApi::make()->send('Purchase', 'order-1', ['value' => 10]));

        Http::assertNothingSent();
    }

    public function test_personal_details_are_hashed_before_they_leave(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $this->settings();

        ConversionsApi::make()->send('Purchase', 'order-99', ['value' => 49.5, 'currency' => 'USD'], [
            'email' => '  Rahim@Example.COM ', 'phone' => '+880 1711-257497', 'first_name' => 'Rahim',
        ]);

        Http::assertSent(function ($request) {
            $user = $request['data'][0]['user_data'];

            // Lower-cased and trimmed before hashing, per Meta's matching rules.
            $this->assertSame(hash('sha256', 'rahim@example.com'), $user['em']);
            // Digits only, country code kept, no plus.
            $this->assertSame(hash('sha256', '8801711257497'), $user['ph']);
            // And nothing raw slipped through.
            $this->assertStringNotContainsString('Rahim@Example', $request->body());
            $this->assertStringNotContainsString('257497', $request->body());

            return true;
        });
    }

    public function test_the_event_id_travels_so_meta_can_deduplicate(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $this->settings();

        ConversionsApi::make()->send('Purchase', 'order-RS-2600016', ['value' => 10]);

        Http::assertSent(fn ($request) => $request['data'][0]['event_id'] === 'order-RS-2600016');
    }

    public function test_a_rejection_is_recorded_rather_than_thrown(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 400)]);
        $this->settings();

        // Tracking must never break the thing it is tracking.
        $this->assertFalse(ConversionsApi::make()->send('Lead', 'contact-1'));
        $this->assertSame('failed', MetaCapiSetting::current()->last_status);
        $this->assertStringContainsString('Invalid token', MetaCapiSetting::current()->last_error);
    }

    private function settings(array $attributes = []): MetaCapiSetting
    {
        return tap(MetaCapiSetting::current())->update($attributes + [
            'is_enabled' => true,
            'pixel_id' => '1234567890',
            'access_token' => 'token',
            'events' => array_keys(MetaCapiSetting::EVENTS),
        ]);
    }
}
