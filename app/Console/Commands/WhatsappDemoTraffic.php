<?php

namespace App\Console\Commands;

use Database\Seeders\WhatsappInquiryDemoSeeder;
use Illuminate\Console\Command;

/**
 * Sample WhatsApp traffic for trying the module out.
 *
 * A command rather than a plain seeder so clearing is one flag away — demo rows that cannot be
 * removed easily end up living on a production database.
 */
class WhatsappDemoTraffic extends Command
{
    protected $signature = 'wa:demo {--clear : Remove the demo enquiries instead of creating them}';

    protected $description = 'Seed (or clear) 30 days of sample WhatsApp traffic';

    public function handle(): int
    {
        $seeder = new WhatsappInquiryDemoSeeder;
        $seeder->setCommand($this);

        if ($this->option('clear')) {
            $seeder->clear();

            return self::SUCCESS;
        }

        if (app()->isProduction() && ! $this->confirm('This is production. Really add demo enquiries?', false)) {
            $this->info('Nothing done.');

            return self::SUCCESS;
        }

        $seeder->run();

        return self::SUCCESS;
    }
}
