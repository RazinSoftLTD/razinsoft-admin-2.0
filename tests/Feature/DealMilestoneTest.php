<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\DealMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealMilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_milestone_stamps_the_date_and_writes_an_activity(): void
    {
        [$admin, $deal, $milestone] = $this->deal();

        $this->actingAs($admin)
            ->post(route('admin.deals.milestones.status', [$deal, $milestone]), ['status' => 'completed'])
            ->assertRedirect();

        $milestone->refresh();

        $this->assertSame('completed', $milestone->status);
        $this->assertNotNull($milestone->completed_at);
        $this->assertNull($milestone->cancelled_at, 'Only the date for the state it is in survives.');
        $this->assertSame($admin->id, $milestone->status_by);

        $this->assertStringContainsString('completed', DealActivity::where('deal_id', $deal->id)->latest('id')->value('body'));
    }

    public function test_cancelling_records_its_own_date_not_the_completion_one(): void
    {
        [$admin, $deal, $milestone] = $this->deal();

        $this->actingAs($admin)->post(route('admin.deals.milestones.status', [$deal, $milestone]), ['status' => 'cancelled']);

        $milestone->refresh();

        $this->assertNotNull($milestone->cancelled_at);
        $this->assertNull($milestone->completed_at);
        $this->assertStringContainsString('cancelled', DealActivity::where('deal_id', $deal->id)->latest('id')->value('body'));
    }

    public function test_reopening_clears_the_dates_so_none_is_left_behind(): void
    {
        [$admin, $deal, $milestone] = $this->deal();

        $this->actingAs($admin)->post(route('admin.deals.milestones.status', [$deal, $milestone]), ['status' => 'completed']);
        $this->actingAs($admin)->post(route('admin.deals.milestones.status', [$deal, $milestone]), ['status' => 'pending']);

        $milestone->refresh();

        $this->assertSame('pending', $milestone->status);
        $this->assertNull($milestone->completed_at, 'A pending milestone must not carry a completion date.');
        $this->assertNull($milestone->status_by);
        $this->assertStringContainsString('Reopened', DealActivity::where('deal_id', $deal->id)->latest('id')->value('body'));
    }

    public function test_a_settled_milestone_is_no_longer_overdue(): void
    {
        [, , $milestone] = $this->deal(['due_date' => now()->subWeek()]);

        $this->assertTrue($milestone->isOverdue());

        $milestone->update(['status' => 'completed', 'completed_at' => now()]);

        $this->assertFalse($milestone->fresh()->isOverdue(), 'Nothing is waiting on it any more.');
    }

    public function test_a_milestone_from_another_deal_cannot_be_settled(): void
    {
        [$admin, $deal] = $this->deal();
        [, , $other] = $this->deal();

        $this->actingAs($admin)
            ->post(route('admin.deals.milestones.status', [$deal, $other]), ['status' => 'completed'])
            ->assertNotFound();
    }

    /** @return array{0: User, 1: Deal, 2: DealMilestone} */
    private function deal(array $milestone = []): array
    {
        $admin = User::firstOrCreate(['email' => 'deal-admin@example.com'], [
            'name' => 'Admin', 'password' => bcrypt('secret123'), 'role' => 'admin', 'status' => 'active',
        ]);

        $deal = Deal::create(['title' => 'Website', 'stage' => 'proposal', 'value' => 1000, 'currency' => 'USD']);

        return [$admin, $deal, $deal->milestones()->create($milestone + [
            'title' => 'Design handover', 'amount' => 500, 'position' => 1,
        ])];
    }
}
