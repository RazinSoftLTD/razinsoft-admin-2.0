<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Permanently remove clients that have sat in the Bin for more than 30 days. */
class PurgeClientBin extends Command
{
    protected $signature = 'clients:purge-bin {--days=30}';

    protected $description = 'Permanently delete clients trashed more than N days ago (default 30).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $stale = User::onlyTrashed()->where('role', User::ROLE_CUSTOMER)->where('deleted_at', '<', $cutoff)->get();
        foreach ($stale as $client) {
            if ($client->photo) {
                Storage::disk('public')->delete($client->photo);
            }
            $client->passwordHistories()->delete();
            $client->forceDelete();
        }

        $this->info("Purged {$stale->count()} client(s) from the Bin older than {$days} days.");

        return self::SUCCESS;
    }
}
