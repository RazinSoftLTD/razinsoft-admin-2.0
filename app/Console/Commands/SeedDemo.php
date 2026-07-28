<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Attendance;
use App\Models\Author;
use App\Models\ChatMessage;
use App\Models\ClientInvoice;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmailCampaign;
use App\Models\EmailConfig;
use App\Models\EmailLog;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\JobOpening;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Leave;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\Subscriber;
use App\Models\Ticket;
use App\Models\TicketAgent;
use App\Models\TicketGroup;
use App\Models\TicketReply;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use App\Models\WhatsappSetting;
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

    /** name, email, role, employee code, job title. */
    private const STAFF = [
        ['Ariana Cole', 'ariana@smartdesk.example', 'admin', 'EMP-001', 'Operations Lead'],
        ['Marcus Reid', 'marcus@smartdesk.example', 'staff', 'EMP-002', 'Account Manager'],
        ['Yuki Tanaka', 'yuki@smartdesk.example', 'staff', 'EMP-003', 'Project Manager'],
        ['Ibrahim Farouk', 'ibrahim@smartdesk.example', 'staff', 'EMP-004', 'Support Engineer'],
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
        $this->followUps($staff);
        $this->invoices($clients);
        $this->catalogue($clients);
        $this->whatsapp($clients, $staff);
        $this->projects($clients, $staff);
        $this->messenger($staff);
        $this->finance($staff);
        $this->hr($staff);
        $this->support($clients, $staff);
        $this->meetings($clients, $staff);
        $this->enquiries();
        $this->marketing();
        $this->reviews($clients);
        $this->emailManager($clients, $staff);
        $this->remainingSections($clients, $staff);

        $this->newLine();
        $this->info('Demo data seeded. Every name in it is invented.');
        $this->line('  Sign in as ariana@smartdesk.example / demo1234');

        return self::SUCCESS;
    }

    /** @return array<int, User> */
    private function staff(): array
    {
        $made = [];

        foreach (self::STAFF as [$name, $email, $role, $code, $title]) {
            $made[] = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('demo1234'),
                'role' => $role,
                'status' => 'active',
                'employee_code' => $code,
                'job_title' => $title,
                'joining_date' => now()->subMonths(30 - count($made) * 6),
                // Screenshots have to be one theme. The column defaults to 'system', and headless
                // Chrome reports a dark preference, so half the gallery came back dark.
                'theme' => 'light',
            ]);
        }

        // Everyone reports to the first person on the list; an empty "Reporting to" column on the
        // employee screen makes the org look like four unconnected people.
        foreach (array_slice($made, 1) as $person) {
            $person->forceFill(['reporting_to' => $made[0]->id])->save();
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

    /**
     * CRM follow-ups: what someone owes each lead, and by when.
     *
     * Spread deliberately across pending / overdue / done / cancelled. "Overdue" is not a stored
     * status — it is a pending row whose scheduled_at has passed — so at least two of these sit in
     * the past, otherwise that state never appears on the screen.
     *
     * @param  array<int, User>  $staff
     */
    private function followUps(array $staff): void
    {
        if (! Schema::hasTable('lead_follow_ups') || LeadFollowUp::exists()) {
            return;
        }

        $leads = Lead::orderBy('id')->get();
        if ($leads->isEmpty()) {
            return;
        }

        $rows = [
            // type, priority, hours from now, status, note, completion note
            ['call', 'high', -30, 'pending', 'Chase the signed proposal — they went quiet after the pricing call.', null],
            ['whatsapp', 'medium', -6, 'pending', 'Send the migration checklist she asked for.', null],
            ['call', 'high', 3, 'pending', 'Discovery call. Ask who owns the hosting.', null],
            ['meeting', 'medium', 26, 'pending', 'Demo of the WhatsApp inbox for their support team.', null],
            ['email', 'low', 52, 'pending', 'Send the licence comparison — Regular vs Extended.', null],
            ['call', 'medium', 96, 'pending', 'Check in after the trial install.', null],
            ['email', 'medium', -120, 'done', 'Send pricing and the module list.', 'Sent. He replied asking about the Extended licence.'],
            ['call', 'high', -72, 'done', 'Follow up on the quote.', 'Spoke for 20 minutes — moving to proposal.'],
            ['sms', 'low', -200, 'cancelled', 'Reminder about the webinar.', 'Webinar moved; no longer relevant.'],
        ];

        foreach ($rows as $i => [$type, $priority, $hours, $status, $note, $done]) {
            $lead = $leads[$i % $leads->count()];
            $owner = $staff[($i % (count($staff) - 1)) + 1];
            $when = now()->addHours($hours);

            LeadFollowUp::create([
                'lead_id' => $lead->id,
                'user_id' => $owner->id,
                'created_by' => $staff[0]->id,
                'type' => $type,
                'priority' => $priority,
                'note' => $note,
                'scheduled_at' => $when,
                'status' => $status,
                'completion_note' => $done,
                'completed_at' => $status === 'done' ? $when->copy()->addHours(2) : null,
                'completed_by' => $status === 'done' ? $owner->id : null,
                'created_at' => $when->copy()->subDays(2),
                'updated_at' => $when->copy()->subDays(2),
            ]);
        }

        $overdue = collect($rows)->filter(fn ($r) => $r[3] === 'pending' && $r[2] < 0)->count();
        $this->line('  '.count($rows).' lead follow-ups ('.$overdue.' overdue)');
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
     * @param  array<int, User>  $clients
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
        WhatsappSetting::current()->update(['gateway_url' => 'http://127.0.0.1:8090']);

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

    /**
     * Projects with tasks spread across the board's columns.
     *
     * @param  array<int, User>  $clients
     * @param  array<int, User>  $staff
     */
    private function projects(array $clients, array $staff): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        $plan = [
            ['Website rebuild', 'Northwind Retail', 'in_progress', 'high', 62, 18000],
            ['Warehouse integration', 'Harbour Logistics', 'in_progress', 'medium', 34, 24500],
            ['Patient portal', 'Verda Health', 'in_progress', 'high', 78, 41000],
            ['Payments migration', 'Kestrel Fintech', 'on_hold', 'medium', 20, 15400],
            ['Brand refresh', 'Bluepeak Studios', 'completed', 'low', 100, 7200],
        ];

        // One task per column, so a screenshot of the board is never a single full lane.
        $columns = [
            ['backlog', 'Write the migration runbook'],
            ['todo', 'Collect brand assets from the client'],
            ['in_progress', 'Build the checkout flow'],
            ['review', 'Accessibility pass on the booking form'],
            ['completed', 'Kick-off call and scope sign-off'],
        ];

        $made = 0;

        foreach ($plan as $i => [$name, $company, $status, $priority, $progress, $budget]) {
            $client = collect($clients)->firstWhere('company', $company) ?? $clients[$i % count($clients)];

            $project = Project::firstOrCreate(['name' => $name], [
                'code' => 'PRJ-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'client_id' => $client->id,
                'status' => $status,
                'priority' => $priority,
                'progress' => $progress,
                // Otherwise the stored figure is ignored in favour of one computed from the
                // task board, and every bar in the screenshot reads 0%.
                'auto_progress' => false,
                'budget' => $budget,
                'currency' => 'USD',
                'start_date' => now()->subDays(70 - $i * 9),
                'deadline' => now()->addDays(25 + $i * 11),
                'project_manager_id' => $staff[($i % (count($staff) - 1)) + 1]->id,
                'created_by' => $staff[0]->id,
                'summary' => 'Demo project. Every name and figure here is invented.',
                'created_at' => now()->subDays(70 - $i * 9),
            ]);

            $made++;

            if (! $project->wasRecentlyCreated || ! Schema::hasTable('project_tasks')) {
                continue;
            }

            foreach ($columns as $n => [$state, $title]) {
                ProjectTask::create([
                    'project_id' => $project->id,
                    'title' => $title,
                    'status' => $state,
                    'priority' => ['high', 'medium', 'low'][$n % 3],
                    // Cycle through everyone including the admin: Tasks opens on "My Tasks",
                    // so leaving the signed-in user out makes the page look broken.
                    'assigned_to' => $staff[($i + $n) % count($staff)]->id,
                    'due_date' => now()->addDays(6 + $n * 8),
                    'sort_order' => $n + 1,
                    'created_by' => $staff[0]->id,
                    'completed_at' => $state === 'completed' ? now()->subDays(3) : null,
                ]);
            }
        }

        $this->line('  '.$made.' projects with tasks');
    }

    /**
     * A wallet, a bank account and a year of money moving through them.
     *
     * Without this the Finance dashboard is a wall of 0.00 — every balance, every chart, every
     * category. It is one of the better screens in the product and it looked broken.
     *
     * @param  array<int, User>  $staff
     */
    private function finance(array $staff): void
    {
        if (! Schema::hasTable('finance_accounts') || ! Schema::hasTable('finance_transactions')) {
            return;
        }

        $wallet = FinanceAccount::firstOrCreate(['name' => 'Company Wallet'], [
            'type' => FinanceAccount::TYPE_WALLET,
            'currency' => 'USD',
            'opening_balance' => 5000,
            'current_balance' => 5000,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $bank = FinanceAccount::firstOrCreate(['name' => 'Business Current Account'], [
            'type' => FinanceAccount::TYPE_BANK,
            'provider' => 'Meridian Bank',
            'currency' => 'USD',
            'account_number' => '•••• 4417',
            'opening_balance' => 42000,
            'current_balance' => 42000,
            'status' => 'active',
            'sort_order' => 2,
        ]);

        if (FinanceTransaction::exists()) {
            return;
        }

        // Twelve months, so the income-vs-expense chart has a shape rather than one bar. Income
        // climbs, costs stay flat-ish: the picture a growing business would actually show.
        $incomeNotes = ['Project invoice settled', 'Retainer', 'Licence sale', 'Support renewal'];
        $expenseNotes = ['Hosting & infrastructure', 'Salaries', 'Software subscriptions', 'Office rent'];

        $rows = [];

        for ($m = 11; $m >= 0; $m--) {
            $month = now()->subMonths($m);
            $income = 14000 + (11 - $m) * 900;
            $expense = 9000 + (($m % 3) * 450);

            $rows[] = [
                'type' => 'income', 'direction' => 'in',
                'account_id' => $m % 2 ? $bank->id : $wallet->id,
                'amount' => $income, 'currency' => 'USD', 'converted_amount' => $income,
                'occurred_on' => $month->copy()->day(8)->toDateString(),
                'reference' => 'INC-'.$month->format('Ym'),
                'notes' => $incomeNotes[$m % count($incomeNotes)],
                'source' => 'manual', 'created_by' => $staff[0]->id,
                'created_at' => $month, 'updated_at' => $month,
            ];

            $rows[] = [
                'type' => 'expense', 'direction' => 'out',
                'account_id' => $bank->id,
                'amount' => $expense, 'currency' => 'USD', 'converted_amount' => $expense,
                'occurred_on' => $month->copy()->day(20)->toDateString(),
                'reference' => 'EXP-'.$month->format('Ym'),
                'notes' => $expenseNotes[$m % count($expenseNotes)],
                'source' => 'manual', 'created_by' => $staff[0]->id,
                'created_at' => $month, 'updated_at' => $month,
            ];
        }

        FinanceTransaction::insert($rows);

        // Balances are stored, not derived, so they have to be brought in line with what we just
        // inserted or the cards disagree with the ledger below them.
        foreach ([$wallet, $bank] as $account) {
            $in = FinanceTransaction::where('account_id', $account->id)->where('direction', 'in')->sum('amount');
            $out = FinanceTransaction::where('account_id', $account->id)->where('direction', 'out')->sum('amount');
            $account->forceFill(['current_balance' => $account->opening_balance + $in - $out])->save();
        }

        $this->line('  2 finance accounts, '.count($rows).' transactions across 12 months');
    }

    /**
     * Internal messaging: one team channel and one direct thread.
     *
     * The inbox screen shows an empty right-hand half until a thread has messages in it, so both
     * of these are seeded with a short exchange rather than left as bare rooms.
     *
     * @param  array<int, User>  $staff
     */
    private function messenger(array $staff): void
    {
        if (! Schema::hasTable('conversations') || ! Schema::hasTable('conversation_user')) {
            return;
        }

        $threads = [
            [
                'group', 'Product team',
                [
                    [1, 'Morning — the Verda portal build is at 78%. Launch call is Thursday.'],
                    [2, 'Nice. I still need the copy for the consent screen before I can close mine.'],
                    [1, 'Sending it over this afternoon.'],
                    [3, 'Harbour have moved their cut-over to the 14th, so we have a week more there.'],
                ],
            ],
            [
                'direct', null,
                [
                    [2, 'Did Northwind come back on the invoice?'],
                    [1, 'Paid this morning. £4,500, cleared.'],
                    [2, 'Great — I will close the milestone.'],
                ],
            ],
        ];

        $made = 0;

        foreach ($threads as [$type, $name, $lines]) {
            $existing = Conversation::when($name, fn ($q) => $q->where('name', $name))
                ->when(! $name, fn ($q) => $q->where('type', 'direct'))
                ->first();

            if ($existing) {
                continue;
            }

            $convo = Conversation::create([
                'type' => $type,
                'name' => $name,
                'created_by' => $staff[0]->id,
                'last_message_at' => now()->subMinutes(6),
            ]);

            // A direct thread is exactly two people; the channel is everyone.
            $members = $type === 'direct' ? [$staff[0], $staff[1]] : $staff;
            $convo->members()->sync(collect($members)->pluck('id')->all());

            foreach ($lines as $n => [$who, $body]) {
                ChatMessage::create([
                    'conversation_id' => $convo->id,
                    'user_id' => $staff[$who % count($staff)]->id,
                    'body' => $body,
                    'created_at' => now()->subMinutes(40 - $n * 8),
                    'updated_at' => now()->subMinutes(40 - $n * 8),
                ]);
            }

            $made++;
        }

        $this->line('  '.$made.' internal message threads');
    }

    /**
     * HR: the org chart, then three weeks of attendance and a few leave requests.
     *
     * @param  array<int, User>  $staff
     */
    private function hr(array $staff): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        $departments = collect(['Operations', 'Delivery', 'Support', 'Sales'])
            ->mapWithKeys(fn ($n) => [$n => Department::firstOrCreate(['name' => $n])->id]);

        $designations = collect(['Operations Lead', 'Account Manager', 'Project Manager', 'Support Engineer'])
            ->mapWithKeys(fn ($n) => [$n => Designation::firstOrCreate(['name' => $n])->id]);

        $placement = [
            ['Operations', 'Operations Lead'],
            ['Sales', 'Account Manager'],
            ['Delivery', 'Project Manager'],
            ['Support', 'Support Engineer'],
        ];

        foreach ($staff as $i => $person) {
            [$dept, $role] = $placement[$i % count($placement)];
            $person->forceFill([
                'department_id' => $departments[$dept],
                'designation_id' => $designations[$role],
            ])->save();
        }

        // Three working weeks. Weekends are skipped rather than marked absent — a demo that shows
        // everybody missing every Saturday looks like a broken import, not a rest day.
        $days = 0;
        if (Schema::hasTable('attendances') && ! Attendance::exists()) {
            for ($back = 21; $back >= 1; $back--) {
                $date = now()->subDays($back);
                if ($date->isWeekend()) {
                    continue;
                }
                $days++;

                foreach ($staff as $i => $person) {
                    // One late arrival and one half day in the set, so the status column is not
                    // a single repeated value down the whole page.
                    $late = ($back + $i) % 9 === 0;
                    $half = ($back + $i) % 14 === 0;

                    $in = $date->copy()->setTime(9, $late ? 38 : 2);
                    $out = $date->copy()->setTime($half ? 13 : 18, $half ? 5 : 12);

                    Attendance::create([
                        'user_id' => $person->id,
                        'work_date' => $date->toDateString(),
                        'check_in_at' => $in,
                        'check_out_at' => $out,
                        'check_in_method' => Attendance::METHOD_WEB,
                        'check_out_method' => Attendance::METHOD_WEB,
                        'worked_minutes' => $in->diffInMinutes($out),
                        'late_minutes' => $late ? 38 : 0,
                        'overtime_minutes' => 0,
                        'status' => $half ? 'half_day' : ($late ? 'late' : 'present'),
                    ]);
                }
            }
        }

        if (Schema::hasTable('leaves') && ! Leave::exists()) {
            $requests = [
                [1, 'annual', -6, -4, 'approved', 'Family holiday, cover arranged with Yuki.'],
                [2, 'sick', -2, -2, 'approved', 'Migraine — will pick up the Verda tasks tomorrow.'],
                [3, 'casual', 9, 10, 'pending', 'Moving flat, need two days.'],
                [1, 'unpaid', 21, 25, 'pending', 'Extended trip, happy to discuss cover.'],
            ];

            foreach ($requests as [$who, $type, $from, $to, $status, $reason]) {
                Leave::create([
                    'user_id' => $staff[$who % count($staff)]->id,
                    'leave_type' => $type,
                    'from_date' => now()->addDays($from)->toDateString(),
                    'to_date' => now()->addDays($to)->toDateString(),
                    'reason' => $reason,
                    'status' => $status,
                    'reviewed_by' => $status === 'approved' ? $staff[0]->id : null,
                    'reviewed_at' => $status === 'approved' ? now()->subDays(7) : null,
                ]);
            }
        }

        if (Schema::hasTable('job_openings') && ! JobOpening::exists()) {
            foreach ([
                ['Senior Laravel Developer', 'Delivery', 'Full-time', 'Remote'],
                ['Customer Support Specialist', 'Support', 'Full-time', 'Hybrid'],
                ['Product Designer', 'Delivery', 'Contract', 'Remote'],
            ] as $i => [$title, $dept, $type, $location]) {
                JobOpening::create([
                    'title' => $title,
                    'slug' => str()->slug($title),
                    'department' => $dept,
                    'type' => $type,
                    'location' => $location,
                    'description' => 'Demo vacancy. Replace this copy with your own before you publish.',
                    'status' => $i === 2 ? 'draft' : 'published',
                    'published_at' => $i === 2 ? null : now()->subDays(10 - $i * 3),
                    'created_by' => $staff[0]->id,
                ]);
            }
        }

        $this->line('  HR: 4 departments, 4 designations, '.($days * count($staff)).' attendance records, 4 leave requests, 3 vacancies');
    }

    /**
     * Support: types, groups, agents, and tickets in every state with replies on them.
     *
     * @param  array<int, User>  $clients
     * @param  array<int, User>  $staff
     */
    private function support(array $clients, array $staff): void
    {
        if (! Schema::hasTable('tickets') || Ticket::exists()) {
            return;
        }

        $types = collect(['Question', 'Bug', 'Feature request'])
            ->map(fn ($n) => TicketType::firstOrCreate(['name' => $n]));
        $groups = collect(['General', 'Billing', 'Technical'])
            ->map(fn ($n) => TicketGroup::firstOrCreate(['name' => $n]));

        foreach (array_slice($staff, 1, 2) as $agent) {
            TicketAgent::firstOrCreate(['user_id' => $agent->id], ['status' => 'active']);
        }

        $tickets = [
            ['Invoice INV-2026-02 shows the wrong tax rate', 'open', 'high', 1, 1, 'The tax on this one came out at 5% but our account is set to 8%. Can you take a look?', 'Checked your invoice settings — the tax was set on the item rather than the account. Corrected and re-issued.'],
            ['Cannot connect our second WhatsApp number', 'pending', 'medium', 2, 2, 'The QR scans fine but the number goes back to disconnected after a few minutes.', 'That usually means the phone lost its data connection. Could you keep it on wifi while it pairs and tell me if it holds?'],
            ['Export contacts to CSV', 'open', 'low', 2, 0, 'Is there a way to get all our clients out as a spreadsheet?', null],
            ['Deal stage colours', 'closed', 'low', 0, 0, 'Can we change the colour of each stage in the pipeline?', 'Yes — CRM Settings, then Stages. Each one takes a colour. Closing this, but shout if it does not do what you need.'],
            ['Payment link expired before the client paid', 'closed', 'high', 1, 1, 'Our client clicked the link on Tuesday and it said expired.', 'Links run for 7 days by default. I have raised yours to 30 in Invoice Configuration and re-sent that one.'],
        ];

        foreach ($tickets as $i => [$subject, $status, $priority, $g, $t, $message, $reply]) {
            $client = $clients[$i % count($clients)];
            $agent = $staff[($i % 2) + 1];

            $ticket = Ticket::create([
                'ticket_number' => 'TKT-'.str_pad((string) (1001 + $i), 4, '0', STR_PAD_LEFT),
                'client_id' => $client->id,
                'subject' => $subject,
                'message' => $message,
                'status' => $status,
                'priority' => $priority,
                'group_id' => $groups[$g]->id,
                'type_id' => $types[$t]->id,
                'assigned_to' => $agent->id,
                'last_reply_at' => now()->subHours(3 + $i * 9),
                'created_at' => now()->subDays(12 - $i * 2),
                'updated_at' => now()->subHours(3 + $i * 9),
            ]);

            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $client->id,
                'is_admin' => false,
                'message' => $message,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->created_at,
            ]);

            if ($reply) {
                TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $agent->id,
                    'is_admin' => true,
                    'message' => $reply,
                    'created_at' => $ticket->last_reply_at,
                    'updated_at' => $ticket->last_reply_at,
                ]);
            }
        }

        $this->line('  Support: '.count($tickets).' tickets with replies, 3 types, 3 groups, 2 agents');
    }

    /**
     * @param  array<int, User>  $clients
     * @param  array<int, User>  $staff
     */
    private function meetings(array $clients, array $staff): void
    {
        if (! Schema::hasTable('meetings') || Meeting::exists()) {
            return;
        }

        $rows = [
            [0, 'Kick-off for the portal build', 2, 'pending', '10:00', '10:45'],
            [3, 'Quarterly review', 5, 'pending', '14:00', '15:00'],
            [1, 'Walkthrough of the new pipeline', -3, 'completed', '11:30', '12:15'],
            [4, 'Payments migration scoping', -9, 'completed', '16:00', '17:00'],
            [2, 'Warehouse integration follow-up', -1, 'cancelled', '09:30', '10:00'],
        ];

        foreach ($rows as $i => [$c, $name, $offset, $status, $from, $to]) {
            $client = $clients[$c];

            Meeting::create([
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'company' => $client->company,
                'notes' => $name,
                'date' => now()->addDays($offset)->toDateString(),
                'start_time' => $from,
                'end_time' => $to,
                'status' => $status,
                'assigned_to' => $staff[($i % (count($staff) - 1)) + 1]->id,
                'client_id' => $client->id,
                'meeting_link' => 'https://meet.example.com/smartdesk-demo-'.($i + 1),
                'created_at' => now()->subDays(max(1, 14 - $i * 2)),
            ]);
        }

        $this->line('  Booking: '.count($rows).' meetings');
    }

    /** Website contact-form enquiries, which land in Communication › Contact Us. */
    private function enquiries(): void
    {
        if (! Schema::hasTable('contact_messages') || ContactMessage::exists()) {
            return;
        }

        $rows = [
            ['Nora Petersen', 'nora@brightstack.example', 'Brightstack', 'Before I buy', 'Does the licence let me run this for two of my own companies, or is it one install per licence?', false],
            ['Rahul Desai', 'rahul@meridianlabs.example', 'Meridian Labs', 'Before I buy', 'Will it run on shared cPanel hosting, or do I need a VPS?', false],
            ['Chloe Baptiste', 'chloe@northgate.example', 'Northgate Studio', 'Licensing', 'We are building a SaaS on top of this. Regular or Extended?', true],
            ['Tom Alvarez', 'tom@alvarezco.example', 'Alvarez & Co', 'Something else', 'Do you offer paid help with the initial install and data import?', true],
        ];

        foreach ($rows as $i => [$name, $email, $company, $service, $message, $read]) {
            ContactMessage::create([
                'name' => $name,
                'email' => $email,
                'company' => $company,
                'service' => $service,
                'message' => $message,
                'is_read' => $read,
                'created_at' => now()->subDays(1 + $i * 2),
            ]);
        }

        $this->line('  '.count($rows).' website enquiries');
    }

    /** Marketing: an author, categories, published posts, subscribers and a search log. */
    private function marketing(): void
    {
        if (! Schema::hasTable('articles') || Article::exists()) {
            return;
        }

        $author = Author::firstOrCreate(['slug' => 'ariana-cole'], [
            'name' => 'Ariana Cole',
            'role' => 'Operations Lead',
            'bio' => 'Writes about running a small team without a stack of subscriptions.',
        ]);

        $categories = collect(['Product', 'Guides', 'Release notes'])
            ->mapWithKeys(fn ($n) => [$n => ArticleCategory::firstOrCreate(['slug' => str()->slug($n)], ['name' => $n])->id]);

        $posts = [
            ['Why we stopped paying per seat', 'Product', 'The tenth person on your team should not cost more than the ninth.', 8, 'why-we-stopped-paying-per-seat'],
            ['Setting up WhatsApp Cloud API, step by step', 'Guides', 'Meta\'s own docs assume you already know the vocabulary. This does not.', 22, 'setting-up-whatsapp-cloud-api'],
            ['Moving your invoices across without losing history', 'Guides', 'A CSV, a dry run, and the two columns everyone forgets.', 40, 'moving-your-invoices-across'],
            ['What shipped this quarter', 'Release notes', 'Deal milestones, retryable WhatsApp sends, and a dark theme.', 55, 'what-shipped-this-quarter'],
        ];

        foreach ($posts as $i => [$title, $cat, $excerpt, $daysAgo, $cover]) {
            Article::create([
                'title' => $title,
                'slug' => str()->slug($title),
                'excerpt' => $excerpt,
                // The blog card renders <img :src> with no fallback, so an article without a
                // cover shows a broken thumbnail rather than a tidy blank.
                'image' => $this->demoAsset('articles/'.$cover.'.png'),
                'image_alt' => $title,
                'content' => '<p>'.$excerpt.'</p><p>Demo post. Replace the body before you publish — this text ships with the package.</p>',
                'category_id' => $categories[$cat],
                'author_id' => $author->id,
                'status' => 'published',
                'is_featured' => $i === 0,
                'read_time' => 3 + $i,
                'published_at' => now()->subDays($daysAgo),
                'created_at' => now()->subDays($daysAgo),
            ]);
        }

        if (Schema::hasTable('subscribers')) {
            foreach ([
                'priya@northwind.example', 'daniel@bluepeak.example', 'mei@harbourlog.example',
                'nora@brightstack.example', 'rahul@meridianlabs.example', 'chloe@northgate.example',
            ] as $i => $email) {
                Subscriber::firstOrCreate(['email' => $email], [
                    'source' => $i % 2 ? 'blog' : 'footer',
                    'is_active' => $i !== 5,
                    'created_at' => now()->subDays(30 - $i * 4),
                ]);
            }
        }

        if (Schema::hasTable('search_logs')) {
            foreach ([['whatsapp cloud api', 3], ['invoice template', 5], ['payroll', 0], ['dark mode', 1], ['csv import', 2]] as $i => [$term, $hits]) {
                SearchLog::create([
                    'term' => $term,
                    'results_count' => $hits,
                    'source' => 'website',
                    'created_at' => now()->subDays(1 + $i * 3),
                ]);
            }
        }

        $this->line('  Marketing: '.count($posts).' articles, 3 categories, 6 subscribers, 5 searches');
    }

    /** @param array<int, User> $clients */
    private function reviews(array $clients): void
    {
        if (! Schema::hasTable('reviews') || Review::exists() || ! Schema::hasTable('products')) {
            return;
        }

        $products = DB::table('products')->pluck('id')->take(4)->values();
        if ($products->isEmpty()) {
            return;
        }

        $rows = [
            [5, 'Set it up on a Friday evening and had the team on it by Monday.'],
            [4, 'Does what it says. The WhatsApp inbox alone replaced two tools for us.'],
            [5, 'The source is readable, which is rarer than it should be.'],
            [4, 'Would like more report templates, but everything we needed was there.'],
        ];

        foreach ($rows as $i => [$rating, $comment]) {
            $client = $clients[$i % count($clients)];

            Review::create([
                'product_id' => $products[$i % $products->count()],
                'user_id' => $client->id,
                'author_name' => $client->name,
                'rating' => $rating,
                'comment' => $comment,
                'is_approved' => $i !== 3,
                'created_at' => now()->subDays(5 + $i * 6),
            ]);
        }

        $this->line('  '.count($rows).' product reviews');
    }

    /**
     * Email Manager: an SMTP account, every default template, a campaign, and a month of logs.
     *
     * The account points at example.com on purpose. The screens need something configured to look
     * alive, and a demo install must not be one click away from posting mail to a real relay.
     *
     * @param  array<int, User>  $clients
     * @param  array<int, User>  $staff
     */
    private function emailManager(array $clients, array $staff): void
    {
        if (! Schema::hasTable('email_configs')) {
            return;
        }

        $config = EmailConfig::firstOrCreate(['name' => 'Primary SMTP'], [
            'provider' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'demo@smartdesk.example',
            'password' => 'replace-me',
            'encryption' => 'tls',
            'from_name' => 'SmartDesk',
            'from_email' => 'hello@smartdesk.example',
            'reply_to' => 'support@smartdesk.example',
            'is_default' => true,
            'is_active' => true,
            'priority' => 1,
            'hourly_limit' => 200,
            'daily_limit' => 2000,
            'health' => 'unknown',
        ]);

        // Every default template, not the two the base seeder happens to install.
        $this->callSilently('email:seed-templates');
        if (Schema::hasTable('email_notification_rules')) {
            $this->callSilently('email:seed-rules');
        }

        if (Schema::hasTable('email_logs') && ! EmailLog::exists()) {
            $subjects = [
                ['Welcome to SmartDesk', 'welcome', 'sent'],
                ['Your invoice INV-2026-01', 'invoices', 'sent'],
                ['Payment received — thank you', 'invoices', 'sent'],
                ['Your ticket TKT-1001 has a reply', 'tickets', 'sent'],
                ['Meeting confirmed for Thursday', 'meetings', 'sent'],
                ['Your invoice INV-2026-03 is due in 3 days', 'invoices', 'pending'],
                ['Password reset', 'account', 'failed'],
            ];

            foreach ($subjects as $i => [$subject, $module, $status]) {
                $client = $clients[$i % count($clients)];
                $when = now()->subDays($i * 3 + 1);

                EmailLog::create([
                    'tracking_id' => (string) str()->uuid(),
                    'email_config_id' => $config->id,
                    'module' => $module,
                    'to_email' => $client->email,
                    'to_name' => $client->name,
                    'subject' => $subject,
                    'body_html' => '<p>Demo message.</p>',
                    'status' => $status,
                    'attempts' => $status === 'failed' ? 3 : 1,
                    'error' => $status === 'failed' ? 'Connection to smtp.example.com timed out' : null,
                    'queued_at' => $when,
                    'scheduled_at' => $status === 'pending' ? now()->addHours(6) : null,
                    'sent_at' => $status === 'sent' ? $when : null,
                    'delivered_at' => $status === 'sent' ? $when->copy()->addMinutes(1) : null,
                    // A believable spread: most opened, about half of those clicked.
                    'first_opened_at' => $status === 'sent' && $i % 3 !== 2 ? $when->copy()->addMinutes(40) : null,
                    'open_count' => $status === 'sent' && $i % 3 !== 2 ? 1 + ($i % 3) : 0,
                    'first_clicked_at' => $status === 'sent' && $i % 2 === 0 ? $when->copy()->addMinutes(52) : null,
                    'click_count' => $status === 'sent' && $i % 2 === 0 ? 1 : 0,
                    'created_at' => $when,
                    'updated_at' => $when,
                ]);
            }
        }

        if (Schema::hasTable('email_campaigns') && ! EmailCampaign::exists()) {
            foreach ([
                ['Spring product update', 'What shipped this quarter', 'sent', -12, 6],
                ['Licence renewal reminder', 'Your support window ends soon', 'scheduled', 4, 6],
                ['Onboarding tips', 'Five things to set up first', 'draft', null, 0],
            ] as [$name, $subject, $status, $offset, $recipients]) {
                EmailCampaign::create([
                    'name' => $name,
                    'subject' => $subject,
                    'email_config_id' => $config->id,
                    'body_html' => '<p>Demo campaign body.</p>',
                    'audience' => 'subscribers',
                    'status' => $status,
                    'scheduled_at' => $offset !== null ? now()->addDays($offset) : null,
                    'started_at' => $status === 'sent' ? now()->addDays($offset) : null,
                    'finished_at' => $status === 'sent' ? now()->addDays($offset)->addMinutes(4) : null,
                    'total_recipients' => $recipients,
                    'created_by' => $staff[0]->id,
                ]);
            }
        }

        if (Schema::hasTable('email_suppressions') && ! EmailSuppression::exists()) {
            foreach ([
                ['bounced@invalid.example', 'bounce', 'Mailbox does not exist'],
                ['unsubscribed@northgate.example', 'unsubscribe', 'Clicked unsubscribe in the spring update'],
                ['complaint@meridianlabs.example', 'complaint', 'Marked as spam'],
            ] as [$email, $reason, $note]) {
                EmailSuppression::create(['email' => $email, 'reason' => $reason, 'note' => $note]);
            }
        }

        $this->line('  Email: 1 SMTP account, '.EmailTemplate::count().' templates, 7 logs, 3 campaigns, 3 suppressions');
    }

    /**
     * The leftovers: the screens a full sweep of the sidebar found still empty.
     *
     * Each is small on its own, but a buyer clicking through the package meets every one of them,
     * and "No records yet" on eight screens reads as eight features that do not work.
     *
     * @param  array<int, User>  $clients
     * @param  array<int, User>  $staff
     */
    private function remainingSections(array $clients, array $staff): void
    {
        // ---- CRM Settings: the Product list was the one option group with nothing in it.
        if (Schema::hasTable('lead_options') && ! DB::table('lead_options')->where('type', 'product')->exists()) {
            foreach (['SmartDesk Licence', 'Installation & Setup', 'Data Migration', 'Custom Development'] as $i => $label) {
                DB::table('lead_options')->insert([
                    'type' => 'product', 'label' => $label, 'sort_order' => $i + 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // ---- Messenger: the Clients tab is its own conversation type, not a staff thread.
        if (Schema::hasTable('conversations') && ! Conversation::where('type', 'client')->exists()) {
            $client = $clients[0];
            $convo = Conversation::create([
                'type' => 'client',
                'name' => $client->name,
                'created_by' => $client->id,
                'last_message_at' => now()->subMinutes(18),
            ]);
            $convo->members()->sync([$client->id, $staff[1]->id]);

            foreach ([
                [$client->id, 'Hi — is the new pipeline live on our account yet?'],
                [$staff[1]->id, 'It went out this morning. You should see the new stages under Deals.'],
                [$client->id, 'Got it, thanks. Looks much clearer.'],
            ] as $n => [$who, $body]) {
                ChatMessage::create([
                    'conversation_id' => $convo->id,
                    'user_id' => $who,
                    'body' => $body,
                    'created_at' => now()->subMinutes(30 - $n * 6),
                    'updated_at' => now()->subMinutes(30 - $n * 6),
                ]);
            }
        }

        // ---- Sales › Installation Plans
        if (Schema::hasTable('installation_plans') && ! DB::table('installation_plans')->exists()) {
            $product = DB::table('products')->value('id');
            if ($product) {
                foreach ([
                    ['Self-install', 'You follow the guide', 0, null, false],
                    ['Standard setup', 'We install it on your server', 99, 79, true],
                    ['Setup & migration', 'Install plus your data brought across', 249, null, false],
                ] as $i => [$name, $tagline, $price, $sale, $popular]) {
                    DB::table('installation_plans')->insert([
                        'product_id' => $product, 'name' => $name, 'tagline' => $tagline,
                        'price' => $price, 'sale_price' => $sale, 'is_popular' => $popular,
                        'position' => $i + 1, 'status' => 'active',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // ---- Sales › Questions, with an answer on two of them.
        if (Schema::hasTable('product_questions') && ! DB::table('product_questions')->exists()) {
            $product = DB::table('products')->value('id');
            if ($product) {
                $rows = [
                    [0, 'Does this work on PHP 8.4?', 'Yes — 8.3 and above. We test on 8.4.'],
                    [1, 'Can I remove the modules I do not use?', 'You can hide them per role, and the source is yours if you want them gone entirely.'],
                    [2, 'Is there a mobile app?', null],
                ];
                foreach ($rows as $i => [$c, $question, $answer]) {
                    $asker = $clients[$c];
                    $qid = DB::table('product_questions')->insertGetId([
                        'product_id' => $product, 'user_id' => $asker->id, 'name' => $asker->name,
                        'question' => $question, 'is_public' => true,
                        'created_at' => now()->subDays(9 - $i * 3), 'updated_at' => now()->subDays(9 - $i * 3),
                    ]);

                    if ($answer && Schema::hasTable('product_answers')) {
                        DB::table('product_answers')->insert([
                            'product_question_id' => $qid, 'user_id' => $staff[0]->id, 'name' => $staff[0]->name,
                            'body' => $answer, 'is_admin' => true, 'is_public' => true,
                            'created_at' => now()->subDays(8 - $i * 3), 'updated_at' => now()->subDays(8 - $i * 3),
                        ]);
                    }
                }
            }
        }

        // ---- Marketing › Promotion. The image is a real file, copied into the demo's storage —
        // a banner row with a broken thumbnail looks worse than no banner at all.
        if (Schema::hasTable('promotions') && ! DB::table('promotions')->exists()) {
            $image = $this->demoAsset('promotions/demo-banner.png');

            foreach ([
                ['top_banner', 'active', true],
                ['popup', 'inactive', false],
            ] as [$type, $status, $countdown]) {
                DB::table('promotions')->insert([
                    'image' => $image, 'mobile_image' => $image, 'type' => $type, 'status' => $status,
                    'countdown_enabled' => $countdown,
                    'countdown_label' => $countdown ? 'Offer ends in' : null,
                    'starts_at' => now()->subDays(3), 'ends_at' => now()->addDays(11),
                    'published_at' => now()->subDays(3), 'created_by' => $staff[0]->id,
                    'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
                ]);
            }
        }

        // ---- Finance › Transfers and Conversions.
        // A transfer is two rows sharing a transfer_group: money out of one account, into another.
        if (Schema::hasTable('finance_transactions') && ! FinanceTransaction::where('type', 'transfer')->exists()) {
            $wallet = FinanceAccount::where('name', 'Company Wallet')->first();
            $bank = FinanceAccount::where('name', 'Business Current Account')->first();

            if ($wallet && $bank) {
                foreach ([[8000, 12], [5000, 34]] as $n => [$amount, $daysAgo]) {
                    $group = 'TRF-'.str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
                    $on = now()->subDays($daysAgo);

                    foreach ([['out', $wallet, $bank], ['in', $bank, $wallet]] as [$dir, $from, $to]) {
                        FinanceTransaction::create([
                            'type' => 'transfer', 'direction' => $dir,
                            'account_id' => $from->id, 'counter_account_id' => $to->id,
                            'amount' => $amount, 'currency' => 'USD', 'converted_amount' => $amount,
                            'occurred_on' => $on->toDateString(), 'transfer_group' => $group,
                            'reference' => $group, 'notes' => 'Wallet top-up to the bank account',
                            'source' => 'manual', 'created_by' => $staff[0]->id,
                            'created_at' => $on, 'updated_at' => $on,
                        ]);
                    }
                }

                // One currency conversion, USD out and EUR in at a stated rate.
                $on = now()->subDays(20);
                $group = 'CNV-0001';
                FinanceTransaction::create([
                    'type' => 'conversion', 'direction' => 'out',
                    'account_id' => $bank->id, 'counter_account_id' => $wallet->id,
                    'amount' => 3000, 'currency' => 'USD', 'converted_amount' => 2760, 'exchange_rate' => 0.92,
                    'occurred_on' => $on->toDateString(), 'transfer_group' => $group, 'reference' => $group,
                    'notes' => 'USD to EUR for the European hosting bill',
                    'source' => 'manual', 'created_by' => $staff[0]->id,
                    'created_at' => $on, 'updated_at' => $on,
                ]);
                FinanceTransaction::create([
                    'type' => 'conversion', 'direction' => 'in',
                    'account_id' => $wallet->id, 'counter_account_id' => $bank->id,
                    'amount' => 2760, 'currency' => 'EUR', 'converted_amount' => 2760, 'exchange_rate' => 0.92,
                    'occurred_on' => $on->toDateString(), 'transfer_group' => $group, 'reference' => $group,
                    'notes' => 'USD to EUR for the European hosting bill',
                    'source' => 'manual', 'created_by' => $staff[0]->id,
                    'created_at' => $on, 'updated_at' => $on,
                ]);
            }
        }

        // ---- Finance › VAT & Tax
        if (Schema::hasTable('finance_taxes') && ! DB::table('finance_taxes')->exists()) {
            foreach ([
                ['vat', 'VAT — Q1', 4820.00, 'Q1 2026', -35, 'paid'],
                ['vat', 'VAT — Q2', 5310.00, 'Q2 2026', 12, 'due'],
                ['income_tax', 'Corporation tax on account', 9200.00, 'FY 2026', 48, 'due'],
            ] as $i => [$kind, $title, $amount, $period, $offset, $status]) {
                DB::table('finance_taxes')->insert([
                    'kind' => $kind, 'title' => $title, 'amount' => $amount, 'currency' => 'USD',
                    'period' => $period, 'due_date' => now()->addDays($offset)->toDateString(),
                    'status' => $status, 'reference' => 'TAX-'.($i + 1),
                    'created_by' => $staff[0]->id, 'created_at' => now()->subDays(40 - $i * 10), 'updated_at' => now(),
                ]);
            }
        }

        // ---- Activity › Client and Blogs
        if (Schema::hasTable('client_activity_logs') && ! DB::table('client_activity_logs')->exists()) {
            $paths = [
                ['/', 'Home'], ['/#pricing', 'Pricing'], ['/blog', 'Blog'],
                ['/blog/why-we-stopped-paying-per-seat', 'Why we stopped paying per seat'],
                ['/blog/setting-up-whatsapp-cloud-api-step-by-step', 'Setting up WhatsApp Cloud API, step by step'],
                ['/contact-us', 'Contact'], ['/dashboard', 'Dashboard'],
            ];

            $rows = [];
            foreach ($clients as $i => $client) {
                foreach ($paths as $n => [$path, $title]) {
                    $rows[] = [
                        'client_id' => $client->id,
                        'path' => $path,
                        'title' => $title,
                        'referrer' => $n === 0 ? 'https://www.google.com/' : null,
                        'ip' => '203.0.113.'.(10 + $i),   // documentation range, not anyone's address
                        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                        'country' => ['United States', 'United Kingdom', 'Germany', 'Australia'][$i % 4],
                        'created_at' => now()->subDays($i)->subHours($n * 2),
                    ];
                }
            }
            DB::table('client_activity_logs')->insert($rows);
        }

        // ---- Branding. The screen falls back to config when the table is empty, which is correct
        // but means the demo never shows the thing being sold: that the panel is renameable.
        if (Schema::hasTable('brand_settings') && ! DB::table('brand_settings')->exists()) {
            DB::table('brand_settings')->insert([
                'product' => config('brand.product', 'SmartDesk'),
                'tagline' => config('brand.tagline', 'The business hub for growing teams'),
                'primary' => '#2563eb',
                'primary_hover' => '#1d4ed8',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->line('  Filled the remaining screens: settings options, client chat, installation plans, questions, promotions, transfers, conversions, tax, client activity');
    }

    /**
     * Puts a real file behind a demo image column and returns the stored path.
     *
     * The screens render these through the media helper, so a made-up path shows a broken
     * thumbnail. Reuses the brand mark rather than shipping stock art nobody has licensed.
     */
    private function demoAsset(string $path): string
    {
        $target = storage_path('app/public/'.$path);

        if (! is_file($target)) {
            @mkdir(dirname($target), 0755, true);

            // Prefer the file shipped for this exact path; fall back to the brand mark so a
            // missing asset still renders something rather than a broken image.
            foreach ([database_path('demo-assets/'.$path), public_path('images/smartdesk-logo.png')] as $source) {
                if (is_file($source)) {
                    @copy($source, $target);
                    break;
                }
            }
        }

        return $path;
    }
}
