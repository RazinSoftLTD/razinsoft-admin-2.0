<?php

namespace App\Console\Commands;

use App\Models\ClientInvoice;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Fills an empty install with invented data, for the public demo and for screenshots.
 *
 * Every name, company and email here is made up. That is the whole point: the alternative is
 * screenshotting production, which puts a real customer's name on a sales page.
 *
 * Refuses to run against a database that already has people in it — a demo seeder loose on a live
 * install is not a mistake anyone recovers from quietly.
 */
class SeedDemo extends Command
{
    protected $signature = 'smartdesk:demo-seed {--force : Seed even if the database is not empty}';

    protected $description = 'Fill this install with invented demo data';

    /** Invented companies, with the kind of names a screenshot should show. */
    private const COMPANIES = [
        ['Northwind Retail', 'Priya Raman', 'priya@northwind.example', 'Retail'],
        ['Bluepeak Studios', 'Daniel Okafor', 'daniel@bluepeak.example', 'Creative'],
        ['Harbour Logistics', 'Mei Lin Tan', 'mei@harbourlog.example', 'Logistics'],
        ['Verda Health', 'Sofia Marchetti', 'sofia@verdahealth.example', 'Healthcare'],
        ['Kestrel Fintech', 'Omar Haddad', 'omar@kestrel.example', 'Finance'],
        ['Alder & Co', 'Grace Whitfield', 'grace@alderco.example', 'Consulting'],
        ['Sunfield Farms', 'Tomas Nowak', 'tomas@sunfield.example', 'Agriculture'],
        ['Lumen Education', 'Aisha Bello', 'aisha@lumen.example', 'Education'],
    ];

    private const STAFF = [
        ['Ariana Cole', 'ariana@smartdesk.example', 'admin'],
        ['Marcus Reid', 'marcus@smartdesk.example', 'staff'],
        ['Yuki Tanaka', 'yuki@smartdesk.example', 'staff'],
        ['Ibrahim Farouk', 'ibrahim@smartdesk.example', 'staff'],
    ];

    public function handle(): int
    {
        if (User::count() > 1 && ! $this->option('force')) {
            $this->error('This database already has people in it. Run smartdesk:prepare-release first.');

            return self::FAILURE;
        }

        $staff = $this->staff();
        $clients = $this->clients();

        $this->leads();
        $this->deals($clients, $staff);
        $this->invoices($clients);
        $this->catalogue($clients);
        $this->whatsapp($clients, $staff);

        $this->newLine();
        $this->info('Demo data seeded. Every name in it is invented.');
        $this->line('  Sign in as ariana@smartdesk.example / demo1234');

        return self::SUCCESS;
    }

    /** @return array<int, User> */
    private function staff(): array
    {
        $made = [];

        foreach (self::STAFF as [$name, $email, $role]) {
            $made[] = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('demo1234'),
                'role' => $role,
                'status' => 'active',
                'job_title' => $role === 'admin' ? 'Operations Lead' : 'Account Manager',
            ]);
        }

        $this->line('  '.count($made).' staff');

        return $made;
    }

    /** @return array<int, User> */
    private function clients(): array
    {
        $made = [];

        foreach (self::COMPANIES as $i => [$company, $person, $email, $industry]) {
            $made[] = User::firstOrCreate(['email' => $email], [
                'name' => $person,
                'password' => Hash::make(str()->random(32)),
                'role' => User::ROLE_CUSTOMER,
                'status' => 'active',
                'company' => $company,
                // Documentation range, so a screenshot never shows a number anyone can ring.
                'phone' => '+1 555 0'.str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT),
                'country' => 'United States',
                'created_at' => now()->subDays(60 - $i * 5),
            ]);
        }

        $this->line('  '.count($made).' clients');

        return $made;
    }

    private function leads(): void
    {
        $sources = ['Website', 'Referral', 'WhatsApp', 'CodeCanyon', 'Cold outreach'];
        $stages = ['new', 'contacted', 'qualified', 'proposal'];

        foreach (self::COMPANIES as $i => [$company, $person, $email, $industry]) {
            Lead::firstOrCreate(['email' => 'lead.'.$email], [
                'full_name' => $person,
                'phone' => '+1 555 0'.str_pad((string) (200 + $i), 3, '0', STR_PAD_LEFT),
                'company_name' => $company,
                'industry' => $industry,
                'lead_source' => $sources[$i % count($sources)],
                'lead_status' => $stages[$i % count($stages)],
                'created_at' => now()->subDays(30 - $i * 3),
            ]);
        }

        $this->line('  '.count(self::COMPANIES).' leads');
    }

    /** @param array<int, User> $clients @param array<int, User> $staff */
    private function deals(array $clients, array $staff): void
    {
        $titles = [
            'Website rebuild', 'CRM rollout', 'Warehouse integration', 'Patient portal',
            'Payments migration', 'Reporting dashboard',
        ];
        $stages = ['proposal', 'negotiation', 'won', 'qualified', 'proposal', 'won'];

        foreach ($titles as $i => $title) {
            $deal = Deal::firstOrCreate(['title' => $title], [
                'client_id' => $clients[$i % count($clients)]->id,
                'stage' => $stages[$i],
                'value' => [4500, 12000, 8200, 26000, 15400, 6800][$i],
                'currency' => 'USD',
                'assigned_to' => $staff[($i % (count($staff) - 1)) + 1]->id,
                'expected_close_date' => now()->addDays(10 + $i * 6),
                'created_at' => now()->subDays(25 - $i * 3),
            ]);

            if ($deal->wasRecentlyCreated && Schema::hasTable('deal_milestones')) {
                foreach ([['Discovery & scope', 0.2], ['Build', 0.5], ['Launch', 0.3]] as $n => [$name, $share]) {
                    $deal->milestones()->create([
                        'title' => $name,
                        'amount' => round($deal->value * $share, 2),
                        'due_date' => now()->addDays(14 + $n * 21),
                        'position' => $n + 1,
                        'status' => $n === 0 ? 'completed' : 'pending',
                        'completed_at' => $n === 0 ? now()->subDays(4) : null,
                    ]);
                }
            }
        }

        $this->line('  '.count($titles).' deals with milestones');
    }

    /** @param array<int, User> $clients */
    private function invoices(array $clients): void
    {
        foreach ([['4,500.00', 'paid'], ['12,000.00', 'sent'], ['8,200.00', 'partial'], ['26,000.00', 'sent']] as $i => [$total, $status]) {
            $client = $clients[$i];

            ClientInvoice::firstOrCreate(['invoice_number' => 'INV-2026-0'.($i + 1)], [
                'client_id' => $client->id,
                'bill_to_name' => $client->name,
                'bill_to_company' => $client->company,
                'bill_to_email' => $client->email,
                'bill_to_address' => '400 Market Street, Springfield, 62704, United States',
                'currency' => 'USD',
                'total' => (float) str_replace(',', '', $total),
                'subtotal' => (float) str_replace(',', '', $total),
                'status' => $status,
                'invoice_date' => now()->subDays(20 - $i * 4),
                'due_date' => now()->addDays(10 + $i * 4),
            ]);
        }

        $this->line('  4 invoices');
    }

    /**
     * Products and paid orders, spread across the last year.
     *
     * Without these the dashboard is a wall of zeros, which is the one screen a buyer looks at
     * first — an empty demo says the product does nothing.
     *
     * @param array<int, User> $clients
     */
    private function catalogue(array $clients): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('orders')) {
            return;
        }

        $catalogue = [
            ['Retail POS Suite', 'retail-pos-suite', 'Point of sale and stock, in one', 249],
            ['Fleet Tracker', 'fleet-tracker', 'Live vehicle tracking and trip logs', 189],
            ['Clinic Manager', 'clinic-manager', 'Appointments, patients and billing', 299],
            ['Campus LMS', 'campus-lms', 'Courses, grading and attendance', 349],
        ];

        $products = [];

        foreach ($catalogue as $i => [$name, $slug, $tagline, $price]) {
            $products[] = DB::table('products')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name, 'tagline' => $tagline, 'category' => 'Business',
                    'status' => 'published', 'is_featured' => $i < 2, 'version' => '2.'.$i,
                    'price' => $price, 'ext_price' => $price * 5, 'currency' => 'USD',
                    'rating' => 4.6 + ($i % 3) / 10, 'sort_order' => $i + 1,
                    'created_at' => now()->subMonths(10 - $i), 'updated_at' => now(),
                ],
            );
        }

        $ids = DB::table('products')->pluck('id', 'slug');

        // A demo gets reset and reseeded; orders are insert-only, so stop if they are already here
        // rather than colliding on the order number.
        if (DB::table('orders')->exists()) {
            $this->line('  '.count($catalogue).' products; orders already present');

            return;
        }

        // Spread across twelve months so the revenue chart has a shape rather than one spike.
        $orders = 0;

        foreach (range(0, 11) as $month) {
            foreach (range(0, ($month % 3) + 1) as $n) {
                $when = now()->subMonths(11 - $month)->addDays($n * 6 + 3);
                $item = $catalogue[($month + $n) % count($catalogue)];
                $client = $clients[($month + $n) % count($clients)];
                $total = $item[3];

                $orderId = DB::table('orders')->insertGetId([
                    'order_number' => 'RS-'.$when->format('ym').str_pad((string) (++$orders), 3, '0', STR_PAD_LEFT),
                    'user_id' => $client->id,
                    'status' => 'completed',
                    'subtotal' => $total, 'discount' => 0, 'total' => $total, 'currency' => 'USD',
                    'payment_gateway' => $n % 2 ? 'stripe' : 'paypal',
                    'paid_at' => $when, 'created_at' => $when, 'updated_at' => $when,
                ]);

                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $ids[$item[1]] ?? null,
                    'product_name' => $item[0],
                    'license_type' => 'regular',
                    'unit_price' => $total, 'quantity' => 1, 'line_total' => $total,
                    'created_at' => $when, 'updated_at' => $when,
                ]);
            }
        }

        $this->line('  '.count($catalogue).' products, '.$orders.' paid orders across 12 months');
    }

    /** @param array<int, User> $clients @param array<int, User> $staff */
    private function whatsapp(array $clients, array $staff): void
    {
        if (! Schema::hasTable('whatsapp_accounts')) {
            return;
        }

        // A gateway URL so the panel does not show "not connected yet" — in a demo that banner
        // reads as a broken product rather than an unconfigured one. It points nowhere; nothing
        // in the demo actually sends.
        \App\Models\WhatsappSetting::current()->update(['gateway_url' => 'http://127.0.0.1:8090']);

        $account = WhatsappAccount::firstOrCreate(['session_key' => 'demo-support'], [
            'name' => 'Support',
            'driver' => 'baileys',
            'color' => '#25d366',
            'display_number' => '15550100',
            'is_connected' => true,
            'session_state' => 'connected',
            'position' => 1,
        ]);

        $account->users()->syncWithoutDetaching(collect($staff)->pluck('id')->all());

        $threads = [
            ['Priya Raman', 'Is the reporting module included in the base licence?', 'It is — reporting is part of every install.'],
            ['Daniel Okafor', 'Can we import our existing client list?', 'Yes, CSV import is under Clients › Import.'],
            ['Mei Lin Tan', 'Do you support multiple WhatsApp numbers?', 'As many as you like, all in one inbox.'],
        ];

        foreach ($threads as $i => [$name, $incoming, $reply]) {
            $chat = WhatsappChat::firstOrCreate(
                ['wa_id' => '1555010'.($i + 1), 'account_id' => $account->id],
                [
                    'profile_name' => $name,
                    'client_id' => $clients[$i]->id,
                    'status' => 'open',
                    'unread_count' => 0,
                    'last_message_at' => now()->subMinutes(30 - $i * 8),
                    'last_message_preview' => $reply,
                ],
            );

            if ($chat->messages()->exists()) {
                continue;
            }

            $chat->messages()->createMany([
                ['direction' => 'in', 'type' => 'text', 'body' => $incoming, 'status' => 'received', 'sent_at' => now()->subMinutes(34 - $i * 8)],
                ['direction' => 'out', 'type' => 'text', 'body' => $reply, 'status' => 'read', 'agent_id' => $staff[1]->id, 'sent_at' => now()->subMinutes(30 - $i * 8)],
            ]);
        }

        $this->line('  1 WhatsApp number, '.count($threads).' conversations');
    }
}
