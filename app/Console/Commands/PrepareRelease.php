<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empties the vendor's own data so a copy can be shipped.
 *
 * This is the step where a mistake cannot be undone, so it is one command rather than a checklist
 * someone works through by hand at midnight. It refuses to run against production unless told
 * twice, and it says exactly what it will delete before it deletes anything.
 *
 * It does NOT touch .env — those credentials are the operator's to rotate, and a command that
 * rewrites .env is a command that can lock you out of your own installation.
 */
class PrepareRelease extends Command
{
    protected $signature = 'smartdesk:prepare-release {--force : Skip the confirmation}';

    protected $description = 'Empty all business and credential data so this copy can be shipped';

    /**
     * Everything that carries the vendor's data, grouped by why it has to go.
     *
     * @var array<string, string[]>
     */
    private const GROUPS = [
        'Credentials' => [
            'whatsapp_accounts', 'whatsapp_settings', 'email_configs', 'meta_capi_settings',
        ],
        'Conversations' => [
            'whatsapp_messages', 'whatsapp_notes', 'whatsapp_chat_label', 'whatsapp_chats',
            'whatsapp_labels', 'whatsapp_quick_replies',
            'email_logs', 'email_attachments', 'email_opens', 'email_clicks',
            'email_bounces', 'email_spam_reports', 'email_suppressions',
            'email_campaign_recipients', 'email_campaigns',
        ],
        'Business records' => [
            'deal_milestones', 'deal_activities', 'deal_follow_ups', 'deal_attachments', 'deals',
            'leads', 'lead_activities', 'lead_follow_ups',
            'invoice_payments', 'client_invoice_items', 'client_invoice_activities', 'client_invoices',
            'order_items', 'orders', 'licenses',
            'billing_addresses', 'client_import_batches',
            'tickets', 'ticket_replies',
            'projects', 'project_milestones', 'tasks',
            'contact_messages', 'meetings', 'subscribers',
        ],
        'People' => [
            'users',
        ],
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production. Use --force only if you are certain.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->warn('This empties every table below and cannot be undone.');
        $this->newLine();

        $total = 0;

        foreach (self::GROUPS as $group => $tables) {
            $rows = collect($tables)
                ->filter(fn ($t) => Schema::hasTable($t))
                ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])
                ->filter();

            if ($rows->isEmpty()) {
                continue;
            }

            $this->line("  <fg=yellow>{$group}</>");
            $rows->each(fn ($n, $t) => $this->line(sprintf('    %-32s %s rows', $t, number_format($n))));
            $total += $rows->sum();
        }

        if ($total === 0) {
            $this->info('Nothing to clear — this copy is already clean.');

            return self::SUCCESS;
        }

        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Delete all of the above?', false)) {
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        $this->wipe();
        $this->reseed();

        $this->newLine();
        $this->info('Done. Left to do by hand, because a command should not:');
        $this->line('  • rotate the keys in .env (Stripe, PayPal, Meta, Envato, mail)');
        $this->line('  • clear storage/app/public — uploaded logos, invoice PDFs, licence files');
        $this->line('  • create the first admin: php artisan smartdesk:admin');

        return self::SUCCESS;
    }

    private function wipe(): void
    {
        // Foreign keys are off for the duration: the tables are listed child-first, but a copy
        // that has been extended may have added its own, and a half-emptied database is worse
        // than a slower one.
        Schema::disableForeignKeyConstraints();

        foreach (self::GROUPS as $tables) {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->line('  Cleared.');
    }

    /** Put back the things a fresh install is expected to have. */
    private function reseed(): void
    {
        $this->callSilently('email:seed-templates', ['--force' => true]);
        $this->callSilently('email:seed-rules');

        // Branding and invoice settings go back to what the software ships with.
        foreach (['brand_settings', 'invoice_settings'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        $this->line('  Email templates and rules reseeded; branding reset to defaults.');
    }
}
