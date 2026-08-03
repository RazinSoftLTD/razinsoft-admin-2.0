<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappInquiry;
use Illuminate\Database\Seeder;

/**
 * Sample WhatsApp traffic, so the module can be judged before real enquiries exist.
 *
 * Deliberately uneven: the numbers pull different volumes, reply at different rates and attract
 * different products, because a seeder that spreads everything evenly makes the reports look
 * correct whether or not they are. Weekends are quieter and the top of the funnel is wider than
 * the bottom, which is what real traffic does.
 *
 *   php artisan wa:demo          seed 30 days of traffic
 *   php artisan wa:demo --clear   take it all back out
 *
 * Every row it writes carries a marker in remarks, so clearing removes only what it made.
 */
class WhatsappInquiryDemoSeeder extends Seeder
{
    private const MARKER = '[demo]';

    /** name => [share of traffic, reply rate, relevance rate] — four numbers behaving differently. */
    private const NUMBERS = [
        'Sales 97' => [0.40, 0.88, 0.55],
        'WA 89' => [0.28, 0.72, 0.40],
        'Tech Support' => [0.20, 0.94, 0.20],
        'Business V. 1' => [0.12, 0.55, 0.35],
    ];

    private const INTERESTS = [
        'Ready eCommerce' => 26, 'Ready POS' => 18, 'Ready Ride' => 14, 'Ready Grocery' => 11,
        'Ready LMS' => 9, 'SmartDesk' => 8, 'Ready Laundry' => 6, 'Rentdo' => 5,
        'Custom development' => 3,
    ];

    public function run(bool $clear = false): void
    {
        if ($clear) {
            $this->clear();

            return;
        }

        $accounts = $this->accounts();
        $user = User::first();
        $days = 30;
        $made = 0;

        for ($d = $days - 1; $d >= 0; $d--) {
            $date = now()->subDays($d);
            // A weekday brings roughly twice what a Friday does, and the run drifts a little so two
            // days are never identical.
            $base = in_array($date->dayOfWeek, [5, 6], true) ? 6 : 14;
            $count = max(2, $base + random_int(-4, 5));

            for ($i = 0; $i < $count; $i++) {
                [$name, $share] = $this->pickNumber();
                [, $replyRate, $relevantRate] = self::NUMBERS[$name];

                $started = $this->chance($replyRate);
                // Nothing is relevant if nobody ever replied — you cannot know.
                $relevant = $started && $this->chance($relevantRate);

                WhatsappInquiry::create([
                    'inquiry_date' => $date->toDateString(),
                    'client_number' => '+8801'.random_int(300000000, 999999999),
                    'client_name' => $this->chance(0.45) ? $this->personName() : null,
                    'whatsapp_account_id' => $accounts[$name]->id,
                    'company_number' => $accounts[$name]->display_number,
                    'conversation_started' => $started,
                    'is_relevant' => $relevant,
                    'interest' => $relevant ? $this->pickInterest() : ($this->chance(0.15) ? $this->pickInterest() : null),
                    'remarks' => self::MARKER.($this->chance(0.3) ? ' '.$this->remark() : ''),
                    'added_by' => $user?->id,
                    'created_at' => $date, 'updated_at' => $date,
                ]);
                $made++;
            }
        }

        $this->command?->info("Seeded {$made} demo enquiries over {$days} days across ".count($accounts).' numbers.');
        $this->command?->line('Remove them with: php artisan wa:demo --clear');
    }

    /** Takes back only what this seeder wrote. */
    public function clear(): void
    {
        $n = WhatsappInquiry::where('remarks', 'like', self::MARKER.'%')->forceDelete();
        $this->command?->info("Removed {$n} demo enquiries.");
    }

    /** @return array<string, WhatsappAccount> */
    private function accounts(): array
    {
        $out = [];
        $i = 0;
        foreach (array_keys(self::NUMBERS) as $name) {
            // Reuse a real account when one already carries this name, so demo rows sit against the
            // numbers the panel actually shows rather than inventing duplicates beside them.
            $out[$name] = WhatsappAccount::firstOrCreate(
                ['name' => $name],
                ['session_key' => 'demo-'.$i, 'driver' => 'cloud_api', 'position' => $i,
                    'display_number' => '+880 1'.str_pad((string) (700000000 + $i * 11111111), 9, '0', STR_PAD_LEFT)],
            );
            $i++;
        }

        return $out;
    }

    private function chance(float $p): bool
    {
        return random_int(1, 1000) <= $p * 1000;
    }

    /** @return array{0: string, 1: float} */
    private function pickNumber(): array
    {
        $roll = random_int(1, 100) / 100;
        $sum = 0;
        foreach (self::NUMBERS as $name => [$share]) {
            $sum += $share;
            if ($roll <= $sum) {
                return [$name, $share];
            }
        }

        return [array_key_first(self::NUMBERS), 0.0];
    }

    private function pickInterest(): string
    {
        $total = array_sum(self::INTERESTS);
        $roll = random_int(1, $total);
        $sum = 0;
        foreach (self::INTERESTS as $name => $weight) {
            $sum += $weight;
            if ($roll <= $sum) {
                return $name;
            }
        }

        return array_key_first(self::INTERESTS);
    }

    private function personName(): string
    {
        $first = ['Rahim', 'Karim', 'Nusrat', 'Tanvir', 'Sadia', 'Imran', 'Farhana', 'Joseph', 'Amina', 'Rakib', 'Priya', 'Hasan'];
        $last = ['Ahmed', 'Hossain', 'Islam', 'Chowdhury', 'Rahman', 'Akter', 'Khan', 'Das'];

        return $first[array_rand($first)].' '.$last[array_rand($last)];
    }

    private function remark(): string
    {
        $notes = [
            'Asked for a demo link.', 'Wanted pricing for 3 branches.', 'Only asked if we do websites.',
            'Following up next week.', 'Wrong number.', 'Asked about installation cost.',
            'Wants a custom feature quote.', 'Already using a competitor.',
        ];

        return $notes[array_rand($notes)];
    }
}
