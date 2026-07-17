<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappLabel;
use App\Models\WhatsappQuickReply;
use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;

/** Settings › WhatsApp API — credentials, webhook details, test connection, labels & quick replies. */
class WhatsappSettingController extends Controller
{
    public function index()
    {
        return view('admin.settings.whatsapp', [
            'settings' => WhatsappSetting::current(),
            'labels' => WhatsappLabel::orderBy('position')->get(),
            'quickReplies' => WhatsappQuickReply::orderBy('shortcut')->get(),
            'webhookUrl' => url('/api/whatsapp/webhook'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'driver' => ['required', 'in:baileys,cloud_api'],
            'gateway_url' => ['nullable', 'url', 'max:255'],
            'gateway_secret' => ['nullable', 'string', 'max:255'],
            'phone_number_id' => ['nullable', 'string', 'max:100'],
            'business_account_id' => ['nullable', 'string', 'max:100'],
            'access_token' => ['nullable', 'string'],
            'app_secret' => ['nullable', 'string', 'max:200'],
            'api_version' => ['nullable', 'string', 'max:10'],
            'interest_options' => ['nullable', 'string'],
        ]);

        $settings = WhatsappSetting::current();
        // Keep stored secrets if their field was left blank (so editing never wipes them).
        foreach (['access_token', 'app_secret', 'gateway_secret'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }
        // Custom interest/product options: one per line → clean array.
        $data['interest_options'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['interest_options'] ?? '')))
            ->map(fn ($v) => trim($v))->filter()->unique()->values()->all();

        $settings->update($data + ['api_version' => $data['api_version'] ?: 'v21.0']);

        return back()->with('status', 'WhatsApp settings saved.');
    }

    public function test()
    {
        [$ok, $message] = app(WhatsappService::class)->testConnection();

        return back()->with($ok ? 'status' : 'error', $message);
    }

    // ---- QR connection (Baileys driver) ----

    public function connection()
    {
        return view('admin.whatsapp.connection', [
            'settings' => WhatsappSetting::current(),
        ]);
    }

    /** JSON status poll for the connection page (QR + state). */
    public function connectionStatus()
    {
        return response()->json(app(WhatsappService::class)->status());
    }

    public function connect()
    {
        return response()->json(app(WhatsappService::class)->connect());
    }

    public function logout()
    {
        app(WhatsappService::class)->disconnect();

        return response()->json(['ok' => true]);
    }

    // ---- labels ----

    public function labelStore(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40'], 'color' => ['nullable', 'string', 'max:20']]);
        WhatsappLabel::create(['name' => $data['name'], 'color' => $data['color'] ?: '#6366f1', 'position' => (int) WhatsappLabel::max('position') + 1]);

        return back()->with('status', 'Label added.');
    }

    public function labelDestroy(WhatsappLabel $label)
    {
        \DB::table('whatsapp_chat_label')->where('label_id', $label->id)->delete();
        $label->delete();

        return back()->with('status', 'Label removed.');
    }

    // ---- quick replies ----

    public function quickStore(Request $request)
    {
        $data = $request->validate(['shortcut' => ['nullable', 'string', 'max:40'], 'body' => ['required', 'string', 'max:2000']]);
        WhatsappQuickReply::create($data);

        return back()->with('status', 'Quick reply added.');
    }

    public function quickDestroy(WhatsappQuickReply $quickReply)
    {
        $quickReply->delete();

        return back()->with('status', 'Quick reply removed.');
    }
}
