<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappLabel;
use App\Models\WhatsappQuickReply;
use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Settings › WhatsApp Config — gateway config, connected numbers (accounts), labels & quick replies. */
class WhatsappSettingController extends Controller
{
    public function index(Request $request)
    {
        WhatsappAccount::purgeExpiredBin(); // auto-remove numbers binned over a month

        // Quick replies are limited to the numbers this user has access to (admins see all).
        $quickAccounts = $this->quickAccounts($request->user());
        $quickIds = $quickAccounts->pluck('id')->all() ?: [0];

        return view('admin.settings.whatsapp', [
            'settings' => WhatsappSetting::current(),
            'accounts' => WhatsappAccount::with('users:id,name')->orderBy('position')->orderBy('id')->get(),
            'quickAccounts' => $quickAccounts,
            'chatCounts' => WhatsappChat::selectRaw('account_id, count(*) chats')->groupBy('account_id')->pluck('chats', 'account_id'),
            'panelUsers' => User::assignable()->orderBy('name')->get(['id', 'name']),
            'labels' => WhatsappLabel::orderBy('position')->get(),
            'quickReplies' => WhatsappQuickReply::with('account:id,name')->whereIn('account_id', $quickIds)
                ->orderBy('account_id')->orderBy('shortcut')->get(),
            'webhookUrl' => url('/api/whatsapp/webhook'),
        ]);
    }

    /** WhatsApp numbers this user may manage quick replies for (admins: all; others: assigned). */
    private function quickAccounts(User $user)
    {
        $q = $user->isAdmin() ? WhatsappAccount::query() : WhatsappAccount::accessibleBy($user);

        return $q->orderBy('position')->orderBy('id')->get();
    }

    /** Restore a number from the bin. */
    public function accountRestore(WhatsappAccount $account)
    {
        WhatsappAccount::onlyTrashed()->whereKey($account->id)->restore();

        return back()->with('status', 'Number restored from the Trash. Reconnect it by scanning the QR.');
    }

    /** Permanently delete a binned number and all its conversations. */
    public function accountForceDelete(WhatsappAccount $account)
    {
        WhatsappAccount::withTrashed()->findOrFail($account->id)->wipe();

        return back()->with('status', 'Number and its conversations permanently deleted.');
    }

    // ---- accounts (WhatsApp numbers) ----

    public function accountStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:9'],
            'driver' => ['required', 'in:baileys,cloud_api'],
            'phone_number_id' => ['nullable', 'required_if:driver,cloud_api', 'string', 'max:100'],
            'business_account_id' => ['nullable', 'string', 'max:100'],
            'access_token' => ['nullable', 'required_if:driver,cloud_api', 'string', 'max:1000'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'api_version' => ['nullable', 'string', 'max:12'],
            'members' => ['array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $cloud = $data['driver'] === 'cloud_api';

        $account = WhatsappAccount::create([
            'name' => $data['name'],
            'driver' => $data['driver'],
            'color' => $data['color'] ?? '#25d366',
            'session_key' => ($cloud ? 'cloud-' : 'acc-').Str::lower(Str::random(10)),
            'position' => (int) WhatsappAccount::max('position') + 1,
            'phone_number_id' => $cloud ? $data['phone_number_id'] : null,
            'business_account_id' => $cloud ? ($data['business_account_id'] ?? null) : null,
            'access_token' => $cloud ? $data['access_token'] : null,
            'app_secret' => $cloud ? ($data['app_secret'] ?? null) : null,
            // Meta echoes this back during the webhook handshake, so each number needs its own.
            'verify_token' => $cloud ? Str::random(24) : null,
            'api_version' => $cloud ? ($data['api_version'] ?: 'v21.0') : null,
        ]);
        $account->users()->sync($data['members'] ?? []);

        return back()->with('status', $cloud
            ? 'Cloud API number added. Verify it, then point the Meta webhook at the URL shown.'
            : 'WhatsApp number added. Now connect it by scanning the QR.');
    }

    public function accountUpdate(Request $request, WhatsappAccount $account)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:9'],
            'driver' => ['required', 'in:baileys,cloud_api'],
            'phone_number_id' => ['nullable', 'required_if:driver,cloud_api', 'string', 'max:100'],
            'business_account_id' => ['nullable', 'string', 'max:100'],
            // Required only when there is nothing saved yet — otherwise an untouched box would be
            // rejected, and "leave it blank to keep the saved one" could never actually happen.
            'access_token' => [$account->access_token ? 'nullable' : 'required_if:driver,cloud_api', 'string', 'max:1000'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'api_version' => ['nullable', 'string', 'max:12'],
            'members' => ['array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $cloud = $data['driver'] === 'cloud_api';

        $account->fill([
            'name' => $data['name'],
            'driver' => $data['driver'],
            'color' => $data['color'] ?? $account->color,
        ]);

        if ($cloud) {
            $account->fill([
                'phone_number_id' => $data['phone_number_id'],
                'business_account_id' => $data['business_account_id'] ?? null,
                'app_secret' => $data['app_secret'] ?? null,
                'api_version' => $data['api_version'] ?: ($account->api_version ?: 'v21.0'),
                'verify_token' => $account->verify_token ?: Str::random(24),
            ]);

            // Left blank means "keep the saved one". The form does show the saved token back, so
            // this is only a backstop — but wiping a working number's credentials because a field
            // arrived empty is the one outcome worth ruling out entirely.
            if (filled($data['access_token'] ?? null)) {
                $account->access_token = $data['access_token'];
            }
        }

        $account->save();
        $account->users()->sync($data['members'] ?? []);

        return back()->with('status', 'Number updated.');
    }

    public function accountDestroy(WhatsappAccount $account)
    {
        try {
            WhatsappService::for($account)->disconnect();
        } catch (\Throwable) {
        }
        $account->delete(); // soft-delete → moves to the bin (kept for 1 month, then auto-purged)

        return back()->with('status', 'Number moved to the Trash. It will be auto-deleted after 1 month.');
    }

    public function update(Request $request)
    {
        // Only the QR gateway is global. The connection method, and Cloud API credentials, belong
        // to each number — that is what lets QR and Cloud API numbers run at the same time.
        $data = $request->validate([
            'gateway_url' => ['nullable', 'url', 'max:255'],
            'gateway_secret' => ['nullable', 'string', 'max:255'],
        ]);

        // Blank means "leave the saved one alone" — the form never shows a secret back, so an
        // empty field is an untouched field, not an instruction to wipe it.
        if (blank($data['gateway_secret'] ?? null)) {
            unset($data['gateway_secret']);
        }

        WhatsappSetting::current()->update($data);

        return back()->with('status', 'Gateway settings saved.');
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

    /** Persist the drag order (ids top to bottom). */
    public function labelOrder(Request $request)
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);

        $pos = 1;
        foreach ($data['order'] as $id) {
            WhatsappLabel::whereKey($id)->update(['position' => $pos++]);
        }

        return response()->json(['ok' => true]);
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
        $data = $request->validate([
            'shortcut' => ['nullable', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:2000'],
            'account_id' => ['required', 'exists:whatsapp_accounts,id'],   // each quick reply belongs to one number
        ]);
        // Can only add to a number this user has access to.
        abort_unless(in_array((int) $data['account_id'], $this->quickAccounts($request->user())->pluck('id')->all(), true), 403);
        WhatsappQuickReply::create($data);

        return back()->with('status', 'Quick reply added.');
    }

    public function quickUpdate(Request $request, WhatsappQuickReply $quickReply)
    {
        $data = $request->validate([
            'shortcut' => ['nullable', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:2000'],
            'account_id' => ['required', 'exists:whatsapp_accounts,id'],
        ]);
        // Both the reply's current number and the target number must be accessible.
        $accessible = $this->quickAccounts($request->user())->pluck('id')->all();
        abort_unless(in_array((int) $data['account_id'], $accessible, true) && in_array((int) $quickReply->account_id, $accessible, true), 403);
        $quickReply->update($data);

        return back()->with('status', 'Quick reply updated.');
    }

    public function quickDestroy(Request $request, WhatsappQuickReply $quickReply)
    {
        abort_unless(in_array((int) $quickReply->account_id, $this->quickAccounts($request->user())->pluck('id')->all(), true), 403);
        $quickReply->delete();

        return back()->with('status', 'Quick reply removed.');
    }
}
