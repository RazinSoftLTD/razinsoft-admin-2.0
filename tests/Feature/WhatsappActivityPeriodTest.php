<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The activity report's window: today by default, or a named span, or two dates. */
class WhatsappActivityPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Boss', 'email' => 'activity-admin@test.local',
            'password' => bcrypt('secret123'), 'role' => 'admin', 'status' => 'active',
        ]);
    }

    /** A chat's row is created the first time that number writes, so created_at is "met on". */
    private function chatMetOn(string $when, string $name): WhatsappChat
    {
        $account = WhatsappAccount::firstOrCreate(['session_key' => 'main'], ['name' => 'Main', 'driver' => 'baileys']);
        $chat = WhatsappChat::create([
            'account_id' => $account->id, 'wa_id' => '88017'.random_int(10000000, 99999999).'@s.whatsapp.net',
            'chat_type' => 'private', 'status' => 'open', 'name' => $name,
        ]);
        $chat->forceFill(['created_at' => $when])->save();

        return $chat;
    }

    public function test_each_window_counts_only_what_falls_inside_it(): void
    {
        $this->chatMetOn(now()->toDateTimeString(), 'Today Contact');
        $this->chatMetOn(now()->subDays(2)->toDateTimeString(), 'This Week Contact');
        $this->chatMetOn(now()->startOfMonth()->addDay()->toDateTimeString(), 'This Month Contact');
        $this->chatMetOn(now()->startOfYear()->addDay()->toDateTimeString(), 'This Year Contact');

        $this->actingAs($this->admin());

        // Default is today, and nothing older leaks in.
        $today = $this->get('/admin/whatsapp-activity')->assertOk();
        $today->assertSee('Today Contact')->assertDontSee('This Year Contact');

        // A wider window keeps everything the narrower one had.
        $this->get('/admin/whatsapp-activity?period=year')->assertOk()
            ->assertSee('Today Contact')->assertSee('This Year Contact');
    }

    public function test_a_custom_range_is_honoured_and_kept_sane(): void
    {
        $this->chatMetOn(now()->subDays(10)->toDateTimeString(), 'Ten Days Ago');
        $this->chatMetOn(now()->toDateTimeString(), 'Today Contact');
        $this->actingAs($this->admin());

        $from = now()->subDays(12)->format('Y-m-d');
        $to = now()->subDays(8)->format('Y-m-d');
        $this->get("/admin/whatsapp-activity?period=custom&from={$from}&to={$to}")->assertOk()
            ->assertSee('Ten Days Ago')->assertDontSee('Today Contact');

        // Dates the wrong way round is a slip — read them as the range they describe.
        $this->get("/admin/whatsapp-activity?period=custom&from={$to}&to={$from}")->assertOk()
            ->assertSee('Ten Days Ago');

        // Nonsense falls back to today rather than erroring.
        $this->get('/admin/whatsapp-activity?period=nonsense')->assertOk()->assertSee('Today Contact');
        $this->get('/admin/whatsapp-activity?period=custom&from=not-a-date')->assertOk();
    }

    /** The list pages, and its quality chips filter the query — not just the rows on screen. */
    public function test_the_list_pages_and_filters_by_quality(): void
    {
        foreach (range(1, 25) as $i) {
            $chat = $this->chatMetOn(now()->toDateTimeString(), "Contact {$i}");
            if ($i <= 3) {
                $chat->update(['lead_quality' => 'qualified']);
            }
        }
        $this->actingAs($this->admin());

        // 20 to a page, so the 25th is only reachable on page two. (The paginator wraps its
        // numbers in spans, so count the rows rather than matching its sentence.)
        $page1 = $this->get('/admin/whatsapp-activity')->assertOk()->assertSee('Showing')->getContent();
        $this->assertSame(20, substr_count($page1, 'onclick="window.location='));
        $page2 = $this->get('/admin/whatsapp-activity?page=2')->assertOk()->getContent();
        $this->assertSame(5, substr_count($page2, 'onclick="window.location='), 'Page two should hold the remainder.');

        // Filtering by quality is a real query: three qualified, whichever page you are on.
        $qualified = $this->get('/admin/whatsapp-activity?quality=qualified')->assertOk();
        $qualified->assertSee('Contact 1')->assertDontSee('Contact 25');

        $this->assertNotSame($page1, $page2);
    }

    /** A row leads to the conversation in the inbox, not to a dead end. */
    public function test_a_row_links_into_the_whatsapp_inbox(): void
    {
        $chat = $this->chatMetOn(now()->toDateTimeString(), 'Reachable Contact');

        $this->actingAs($this->admin())->get('/admin/whatsapp-activity')->assertOk()
            ->assertSee("admin/whatsapp?account={$chat->account_id}&amp;chat={$chat->id}", false);
    }

    /** Page size is the reader's choice, and a silly one falls back rather than breaking. */
    public function test_the_page_size_can_be_changed(): void
    {
        foreach (range(1, 25) as $i) {
            $this->chatMetOn(now()->toDateTimeString(), "Contact {$i}");
        }
        $this->actingAs($this->admin());

        $rows = fn (string $qs) => substr_count($this->get('/admin/whatsapp-activity'.$qs)->assertOk()->getContent(), 'onclick="window.location=');

        $this->assertSame(20, $rows(''));                  // default
        $this->assertSame(10, $rows('?per_page=10'));
        $this->assertSame(25, $rows('?per_page=100'));     // everything there is
        $this->assertSame(20, $rows('?per_page=9999'));    // not on the menu → default
    }

    /** Two tabs: the window's conversations, and how each number is doing. */
    public function test_the_page_splits_into_a_report_tab_and_a_numbers_tab(): void
    {
        $this->chatMetOn(now()->toDateTimeString(), 'Reportable Contact');
        $this->actingAs($this->admin());

        $report = $this->get('/admin/whatsapp-activity')->assertOk();
        $report->assertSee('Reportable Contact')->assertSee('Conversation report');
        $report->assertDontSee('Avg. response');

        $numbers = $this->get('/admin/whatsapp-activity?tab=numbers')->assertOk();
        $numbers->assertSee('Avg. response')->assertDontSee('Reportable Contact');

        // An unknown tab is the report, not a blank page.
        $this->get('/admin/whatsapp-activity?tab=nonsense')->assertOk()->assertSee('Reportable Contact');

        // The WhatsApp Button page is the third tab, and every tab links to the others.
        $button = $this->get('/admin/whatsapp-links')->assertOk();
        $button->assertSee('WhatsApp Button')->assertSee('Create a link');
        $button->assertSee(route('admin.whatsapp-activity', []), false);
        $report->assertSee(route('admin.whatsapp-links'), false);

        // The old page title is gone — the top bar already names the page.
        $report->assertDontSee('Oversight of every connected number');
    }

    /**
     * The Button page follows the WhatsApp Activity permission it is now a tab of.
     *
     * It used to be gated by activity.client while the menu offered it to whatsapp.activity —
     * so the people shown the link were refused when they followed it.
     */
    public function test_the_button_tab_is_gated_with_whatsapp_activity(): void
    {
        $staff = User::create([
            'name' => 'Agent', 'email' => 'wa-activity@test.local', 'password' => bcrypt('secret123'),
            'role' => 'staff', 'status' => 'active', 'permissions' => ['whatsapp.activity' => 'all'],
        ]);

        $this->actingAs($staff)->get('/admin/whatsapp-links')->assertOk();

        $other = User::create([
            'name' => 'Other', 'email' => 'wa-none@test.local', 'password' => bcrypt('secret123'),
            'role' => 'staff', 'status' => 'active', 'permissions' => ['activity.client' => 'all'],
        ]);
        $this->actingAs($other)->get('/admin/whatsapp-links')->assertForbidden();
    }

    /** The Config tab only shows to someone who may open it. */
    public function test_the_config_tab_follows_its_own_permission(): void
    {
        $reader = User::create([
            'name' => 'Reader', 'email' => 'wa-reader@test.local', 'password' => bcrypt('secret123'),
            'role' => 'staff', 'status' => 'active', 'permissions' => ['whatsapp.activity' => 'all'],
        ]);
        $this->actingAs($reader)->get('/admin/whatsapp-activity')->assertOk()
            ->assertSee('Conversation report')->assertDontSee(route('admin.whatsapp-settings'), false);

        $configurer = User::create([
            'name' => 'Configurer', 'email' => 'wa-config@test.local', 'password' => bcrypt('secret123'),
            'role' => 'staff', 'status' => 'active', 'permissions' => ['whatsapp.settings' => 'all'],
        ]);
        $this->actingAs($configurer)->get('/admin/whatsapp-settings')->assertOk()
            ->assertSee('Config')->assertDontSee(route('admin.whatsapp-links'), false);
    }
}
