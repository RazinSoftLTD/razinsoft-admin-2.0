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
}
