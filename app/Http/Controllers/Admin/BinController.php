<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Settings → Bin: recoverable soft-deleted records (clients + invoices).
 * Super admin only. Items auto-purge 30 days after deletion.
 */
class BinController extends Controller
{
    public const RETENTION_DAYS = 30;

    private function guard(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
    }

    public function index()
    {
        $this->guard();
        \App\Models\WhatsappAccount::purgeExpiredBin(self::RETENTION_DAYS); // auto-remove expired WhatsApp numbers

        return view('admin.bin.index', [
            'clients' => User::onlyTrashed()->where('role', User::ROLE_CUSTOMER)->latest('deleted_at')->paginate(15, ['*'], 'clients_page'),
            'invoices' => ClientInvoice::onlyTrashed()->with('client:id,name')->latest('deleted_at')->paginate(15, ['*'], 'invoices_page'),
            'whatsappAccounts' => \App\Models\WhatsappAccount::onlyTrashed()->latest('deleted_at')->get(),
            'whatsappCounts' => \App\Models\WhatsappChat::selectRaw('account_id, count(*) chats')->groupBy('account_id')->pluck('chats', 'account_id'),
            'retentionDays' => self::RETENTION_DAYS,
        ]);
    }

    // ---- Clients ----
    public function restoreClient(int $id)
    {
        $this->guard();
        $client = User::onlyTrashed()->where('role', User::ROLE_CUSTOMER)->findOrFail($id);
        $client->restore();

        return back()->with('status', "Client “{$client->name}” restored.");
    }

    public function forceDeleteClient(int $id)
    {
        $this->guard();
        $client = User::onlyTrashed()->where('role', User::ROLE_CUSTOMER)->findOrFail($id);
        $this->wipeClient($client);

        return back()->with('status', 'Client permanently deleted.');
    }

    // ---- Clients: bulk ----
    public function bulkRestoreClients(Request $request)
    {
        $this->guard();
        $ids = $this->ids($request);
        $n = User::onlyTrashed()->where('role', User::ROLE_CUSTOMER)->whereIn('id', $ids)->get()
            ->each->restore()->count();

        return back()->with('status', "Restored {$n} client(s).");
    }

    public function bulkForceDeleteClients(Request $request)
    {
        $this->guard();
        $ids = $this->ids($request);
        $clients = User::onlyTrashed()->where('role', User::ROLE_CUSTOMER)->whereIn('id', $ids)->get();
        foreach ($clients as $client) {
            $this->wipeClient($client);
        }

        return back()->with('status', 'Permanently deleted '.$clients->count().' client(s).');
    }

    // ---- Invoices: bulk ----
    public function bulkRestoreInvoices(Request $request)
    {
        $this->guard();
        $ids = $this->ids($request);
        $invoices = ClientInvoice::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($invoices as $invoice) {
            $invoice->restore();
            $invoice->logActivity('restored', 'Invoice restored from the Bin.');
        }

        return back()->with('status', 'Restored '.$invoices->count().' invoice(s).');
    }

    public function bulkForceDeleteInvoices(Request $request)
    {
        $this->guard();
        $ids = $this->ids($request);
        $invoices = ClientInvoice::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($invoices as $invoice) {
            if ($invoice->attachment) {
                Storage::disk('public')->delete($invoice->attachment);
            }
            $invoice->forceDelete();
        }

        return back()->with('status', 'Permanently deleted '.$invoices->count().' invoice(s).');
    }

    private function ids(Request $request): array
    {
        return $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
    }

    private function wipeClient(User $client): void
    {
        if ($client->photo) {
            Storage::disk('public')->delete($client->photo);
        }
        $client->passwordHistories()->delete();
        $client->forceDelete();
    }
}
