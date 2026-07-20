<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\Project;
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
            'projects' => Project::onlyTrashed()->with('client:id,name')->latest('deleted_at')->get(),
            'whatsappAccounts' => \App\Models\WhatsappAccount::onlyTrashed()->latest('deleted_at')->get(),
            'whatsappCounts' => \App\Models\WhatsappChat::selectRaw('account_id, count(*) chats')->groupBy('account_id')->pluck('chats', 'account_id'),
            'retentionDays' => self::RETENTION_DAYS,
        ]);
    }

    // ---- Projects ----
    public function restoreProject(int $id)
    {
        $this->guard();
        $project = Project::onlyTrashed()->findOrFail($id);
        $project->restore();

        return back()->with('status', "Project “{$project->name}” restored.");
    }

    public function forceDeleteProject(int $id)
    {
        $this->guard();
        $project = Project::onlyTrashed()->findOrFail($id);
        $name = $project->name;
        $this->wipeProject($project);

        return back()->with('status', "Project “{$name}” permanently deleted.");
    }

    public function emptyProjects()
    {
        $this->guard();
        $projects = Project::onlyTrashed()->get();
        foreach ($projects as $project) {
            $this->wipeProject($project);
        }

        return back()->with('status', 'Permanently deleted '.$projects->count().' project(s).');
    }

    /** Remove a project for good, along with the files it owns on disk. */
    private function wipeProject(Project $project): void
    {
        Storage::disk('public')->deleteDirectory('projects/'.$project->id);
        $project->forceDelete();
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
            $invoice->logActivity('restored', 'Invoice restored from the Trash.');
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

    // ---- Empty the Trash (permanently delete EVERYTHING in a tab) ----
    public function emptyClients()
    {
        $this->guard();
        $clients = User::onlyTrashed()->where('role', User::ROLE_CUSTOMER)->get();
        foreach ($clients as $client) {
            $this->wipeClient($client);
        }

        return back()->with('status', 'Trash emptied — '.$clients->count().' client(s) permanently deleted.');
    }

    public function emptyInvoices()
    {
        $this->guard();
        $invoices = ClientInvoice::onlyTrashed()->get();
        foreach ($invoices as $invoice) {
            if ($invoice->attachment) {
                Storage::disk('public')->delete($invoice->attachment);
            }
            $invoice->forceDelete();
        }

        return back()->with('status', 'Trash emptied — '.$invoices->count().' invoice(s) permanently deleted.');
    }

    public function emptyWhatsapp()
    {
        $this->guard();
        $accounts = \App\Models\WhatsappAccount::onlyTrashed()->get();
        foreach ($accounts as $account) {
            \App\Models\WhatsappAccount::withTrashed()->find($account->id)?->wipe();
        }

        return back()->with('status', 'Trash emptied — '.$accounts->count().' WhatsApp number(s) permanently deleted.');
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
