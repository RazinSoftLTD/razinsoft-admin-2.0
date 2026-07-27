<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaCapiSetting;
use App\Services\Meta\ConversionsApi;
use Illuminate\Http\Request;

/** Settings → Meta Conversions API. */
class MetaCapiController extends Controller
{
    public function index(Request $request)
    {
        $this->can($request);

        return view('admin.settings.meta-capi', ['settings' => MetaCapiSetting::current()]);
    }

    public function update(Request $request)
    {
        $this->can($request);

        $settings = MetaCapiSetting::current();

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'pixel_id' => ['nullable', 'string', 'max:64'],
            // Required only until one is stored — after that a blank box means "leave it alone".
            'access_token' => [$settings->access_token ? 'nullable' : 'nullable', 'string', 'max:1000'],
            'test_event_code' => ['nullable', 'string', 'max:64'],
            'api_version' => ['nullable', 'string', 'max:12'],
            'events' => ['array'],
            'events.*' => ['string', 'in:'.implode(',', array_keys(MetaCapiSetting::EVENTS))],
        ]);

        $settings->fill([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'pixel_id' => $data['pixel_id'] ?? null,
            'test_event_code' => $data['test_event_code'] ?? null,
            'api_version' => $data['api_version'] ?: 'v21.0',
            'events' => $data['events'] ?? [],
        ]);

        if (filled($data['access_token'] ?? null)) {
            $settings->access_token = $data['access_token'];
        }

        $settings->save();

        return back()->with('status', 'Conversions API settings saved.');
    }

    /**
     * Send one real event so Events Manager can be watched while it lands.
     *
     * Deliberately a Lead with a throwaway id: it is the cheapest event to see arrive, and with a
     * Test Event Code set it never touches real reporting.
     */
    public function test(Request $request)
    {
        $this->can($request);

        $settings = MetaCapiSetting::current();

        if (! $settings->isConfigured()) {
            return back()->with('error', 'Add the Pixel ID and Access Token first.');
        }

        $ok = (new ConversionsApi($settings))->send('Lead', 'test-'.now()->timestamp, [
            'content_name' => 'Conversions API test',
        ], ['email' => $request->user()->email], $request);

        return $ok
            ? back()->with('status', 'Test event sent. Open Events Manager → Test Events to see it arrive.')
            : back()->with('error', 'Meta did not accept it: '.($settings->fresh()->last_error ?: 'unknown error').
                ($settings->is_enabled ? '' : ' (the integration is switched off — turn it on first)'));
    }

    private function can(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}
