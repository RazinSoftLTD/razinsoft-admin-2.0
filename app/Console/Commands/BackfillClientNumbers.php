<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use libphonenumber\PhoneNumberUtil;

/**
 * Splits the dial code out of client mobile numbers, and names the country it belongs to.
 *
 * The Add Client form kept the country and its dial code in their own columns, but until the box
 * was fixed it only wrote them when the country was picked from the list. Almost every client
 * therefore has the whole number in `phone` — "+8801765584799" — with no dial code and no country.
 *
 * That reads as a doubled code the moment the two are shown together, which the client list does:
 * `trim($dial_code.' '.$phone)`. So the number is rewritten at the same time, or filling the code
 * in would make the list worse than it is now.
 *
 * libphonenumber decides, not a prefix table: +1 alone spans two dozen countries, and the split
 * between code and number is not the same length everywhere.
 */
class BackfillClientNumbers extends Command
{
    protected $signature = 'clients:backfill-numbers {--apply : Write the changes (otherwise only reports them)}';

    protected $description = 'Fill in client dial codes and countries from the mobile number already stored';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $util = PhoneNumberUtil::getInstance();

        // region code => the country as this panel names it, so the value matches what the form's
        // own list would have written.
        $names = collect(config('countries'))->keyBy('code');

        $clients = User::clients()
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->get(['id', 'name', 'phone', 'dial_code', 'country']);

        $changes = [];
        $skipped = ['unparseable' => 0, 'no country name' => 0, 'nothing to add' => 0];

        foreach ($clients as $c) {
            try {
                // Only a number carrying its own code can be split with any confidence. A bare
                // national number could belong to anywhere, and guessing would write a country
                // that is simply wrong — worse than the blank it replaces.
                $proto = $util->parse(str_starts_with(trim($c->phone), '+') ? trim($c->phone) : '+'.preg_replace('/\D/', '', $c->phone), null);
            } catch (\Throwable $e) {
                $skipped['unparseable']++;

                continue;
            }

            if (! $util->isValidNumber($proto)) {
                $skipped['unparseable']++;

                continue;
            }

            $region = $util->getRegionCodeForNumber($proto);
            $entry = $names->get($region);

            if (! $entry) {
                $skipped['no country name']++;

                continue;
            }

            $dial = '+'.$proto->getCountryCode();
            $national = (string) $proto->getNationalNumber();

            $new = [
                'dial_code' => $dial,
                'phone' => $national,
                // Never overwrite a country someone chose; only fill a blank one.
                'country' => filled($c->country) ? $c->country : $entry['name'],
            ];

            if ($new['dial_code'] === $c->dial_code && $new['phone'] === $c->phone && $new['country'] === $c->country) {
                $skipped['nothing to add']++;

                continue;
            }

            $changes[] = ['client' => $c, 'new' => $new];
        }

        $this->line('');
        $this->info('  clients with a phone:  '.$clients->count());
        $this->info('  would be filled in:    '.count($changes));
        foreach ($skipped as $why => $n) {
            $this->line("  left alone ($why): $n");
        }

        $this->line('');
        $this->line('  first 15:');
        $this->table(
            ['id', 'name', 'stored phone', '→ dial', '→ phone', '→ country'],
            collect($changes)->take(15)->map(fn ($c) => [
                $c['client']->id,
                Str::limit($c['client']->name, 22),
                $c['client']->phone,
                $c['new']['dial_code'],
                $c['new']['phone'],
                $c['new']['country'],
            ])->all(),
        );

        if (! $apply) {
            $this->line('');
            $this->comment('  Nothing written. Re-run with --apply to save.');

            return self::SUCCESS;
        }

        foreach ($changes as $c) {
            $c['client']->forceFill($c['new'])->save();
        }

        $this->line('');
        $this->info('  written: '.count($changes));

        return self::SUCCESS;
    }
}
