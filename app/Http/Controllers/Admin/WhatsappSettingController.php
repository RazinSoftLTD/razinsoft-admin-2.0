<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappLabel;
use App\Models\WhatsappQuickReply;
use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Settings › WhatsApp Config — gateway config, connected numbers (accounts), labels & quick replies. */
class WhatsappSettingController extends Controller
{
    public function index()
    {
        return view('admin.settings.whatsapp', [
            'settings' => WhatsappSetting::current(),
            'accounts' => WhatsappAccount::with('users:id,name')->orderBy('position')->orderBy('id')->get(),
            'panelUsers' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'labels' => WhatsappLabel::orderBy('position')->get(),
            'quickReplies' => WhatsappQuickReply::orderBy('shortcut')->get(),
            'webhookUrl' => url('/api/whatsapp/webhook'),
        ]);
    }

    // ---- accounts (WhatsApp numbers) ----

    public function accountStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:9'],
            'members' => ['array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);
        $account = WhatsappAccount::create([
            'name' => $data['name'],
            'color' => $data['color'] ?: '#25d366',
            'session_key' => 'acc-'.Str::lower(Str::random(10)),
            'position' => (int) WhatsappAccount::max('position') + 1,
        ]);
        $account->users()->sync($data['members'] ?? []);

        return back()->with('status', 'WhatsApp number added. Now connect it by scanning the QR.');
    }

    public function accountUpdate(Request $request, WhatsappAccount $account)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:9'],
            'members' => ['array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);
        $account->update(['name' => $data['name'], 'color' => $data['color'] ?: $account->color]);
        $account->users()->sync($data['members'] ?? []);

        return back()->with('status', 'Number updated.');
    }

    public function accountDestroy(WhatsappAccount $account)
    {
        try {
            WhatsappService::for($account)->disconnect();
        } catch (\Throwable) {
        }
        $account->delete(); // chats cascade via account_id? keep chats but null out

        return back()->with('status', 'Number removed.');
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

    // ---- QR connection (Baileys driver), per account ----

    public function connection(WhatsappAccount $account)
    {
        return view('admin.whatsapp.connection', [
            'settings' => WhatsappSetting::current(),
            'account' => $account,
        ]);
    }

    /** JSON status poll for the connection page (QR + state); persists state to the account. */
    public function connectionStatus(WhatsappAccount $account)
    {
        $status = WhatsappService::for($account)->status();
        $account->update([
            'session_state' => $status['state'] ?? 'disconnected',
            'is_connected' => ($status['state'] ?? '') === 'connected',
            'display_number' => $status['number'] ?: $account->display_number,
            'connected_at' => ($status['state'] ?? '') === 'connected' ? ($account->connected_at ?: now()) : $account->connected_at,
        ]);

        return response()->json($status);
    }

    public function connect(WhatsappAccount $account)
    {
        return response()->json(WhatsappService::for($account)->connect());
    }

    public function logout(WhatsappAccount $account)
    {
        WhatsappService::for($account)->disconnect();
        $account->update(['session_state' => 'disconnected', 'is_connected' => false]);

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
