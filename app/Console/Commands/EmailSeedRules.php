<?php

namespace App\Console\Commands;

use App\Models\EmailNotificationRule;
use App\Models\EmailTemplate;
use Illuminate\Console\Command;

/**
 * Installs the notification rules the system ships with.
 *
 * Safe to re-run: an existing rule keeps whatever the admin set, so re-running never silently
 * turns a notification back on.
 */
class EmailSeedRules extends Command
{
    protected $signature = 'email:seed-rules';

    protected $description = 'Create the default email notification rules';

    public function handle(): int
    {
        $templates = EmailTemplate::pluck('id', 'key');
        $created = $kept = 0;

        foreach (EmailNotificationRule::defaults() as $rule) {
            $existing = EmailNotificationRule::where('key', $rule['key'])->first();

            if ($existing) {
                $kept++;

                continue;
            }

            EmailNotificationRule::create([
                'key' => $rule['key'],
                'name' => $rule['name'],
                'group' => $rule['group'],
                'description' => $rule['description'],
                'email_template_id' => $templates[$rule['template']] ?? null,
                'is_enabled' => true,
            ]);
            $created++;
        }

        $this->info("Notification rules — created: {$created}, left untouched: {$kept}.");

        return self::SUCCESS;
    }
}
