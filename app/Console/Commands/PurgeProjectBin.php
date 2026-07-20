<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Permanently remove projects that have sat in the Trash for more than 30 days. */
class PurgeProjectBin extends Command
{
    protected $signature = 'projects:purge-bin {--days=30}';

    protected $description = 'Permanently delete projects trashed more than N days ago (default 30).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $stale = Project::onlyTrashed()->where('deleted_at', '<', now()->subDays($days))->get();

        foreach ($stale as $project) {
            Storage::disk('public')->deleteDirectory('projects/'.$project->id);
            $project->forceDelete();
        }

        $this->info("Purged {$stale->count()} project(s) from the Trash older than {$days} days.");

        return self::SUCCESS;
    }
}
