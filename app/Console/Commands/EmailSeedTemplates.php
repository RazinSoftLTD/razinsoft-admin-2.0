<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use App\Services\Email\DefaultTemplates;
use App\Services\Email\EmailBodyBuilder;
use Illuminate\Console\Command;

/**
 * Installs (or restores) the templates the system ships with.
 *
 * Safe to re-run: an existing template is left exactly as the admin edited it unless --force is
 * given, which resets it to the shipped wording.
 */
class EmailSeedTemplates extends Command
{
    protected $signature = 'email:seed-templates {--force : Overwrite templates that already exist}';

    protected $description = 'Create the default email templates';

    public function handle(): int
    {
        $created = $reset = $kept = 0;

        foreach (DefaultTemplates::all() as $definition) {
            $existing = EmailTemplate::where('key', $definition['key'])->first();

            if ($existing && ! $this->option('force')) {
                // Mark it as one of ours so the UI protects it, but leave the wording alone.
                $existing->forceFill(['is_system' => true])->save();
                $kept++;

                continue;
            }

            $html = DefaultTemplates::wrap($definition['body'], $definition['description']);

            $payload = [
                'name' => $definition['name'],
                'category' => $definition['category'],
                'subject' => $definition['subject'],
                'body' => $html,
                // Generated once at install so the plain-text half is never empty.
                'body_text' => EmailBodyBuilder::toPlainText($html),
                'description' => $definition['description'],
                'variables' => $definition['variables'],
                'is_system' => true,
            ];

            if ($existing) {
                $existing->update($payload);
                $reset++;
            } else {
                EmailTemplate::create($payload + ['key' => $definition['key'], 'is_active' => true]);
                $created++;
            }
        }

        $this->info("Templates — created: {$created}, reset: {$reset}, left untouched: {$kept}.");

        return self::SUCCESS;
    }
}
