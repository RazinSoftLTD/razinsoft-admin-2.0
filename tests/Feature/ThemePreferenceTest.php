<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_follows_the_device(): void
    {
        $this->assertSame('system', $this->staff()->theme, 'The device already knows; asking again is a worse default.');
    }

    public function test_the_choice_is_saved_on_the_account(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->postJson(route('admin.theme'), ['theme' => 'dark'])
            ->assertOk()->assertJsonPath('theme', 'dark');

        // On the account, not in the browser — so it follows them to any machine.
        $this->assertSame('dark', $user->fresh()->theme);
    }

    public function test_only_the_three_real_choices_are_accepted(): void
    {
        // Asserted on the exception rather than the response: this app's handler renders the
        // admin layout for validation failures, which needs a view error bag a JSON test has not.
        $this->withoutExceptionHandling();
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->actingAs($this->staff())->postJson(route('admin.theme'), ['theme' => 'neon']);
    }

    public function test_every_signed_in_user_may_set_it_without_a_permission(): void
    {
        // A display preference is not something to gate — a staff member with no permissions at
        // all still has eyes.
        $plain = User::create([
            'name' => 'Junior', 'email' => 'junior@example.com', 'password' => bcrypt('secret123'),
            'role' => 'staff', 'status' => 'active', 'permissions' => [],
        ]);

        $this->actingAs($plain)->postJson(route('admin.theme'), ['theme' => 'light'])->assertOk();
        $this->assertSame('light', $plain->fresh()->theme);
    }

    public function test_a_guest_cannot_set_it(): void
    {
        // The staff middleware sends anyone signed out to the login screen.
        $this->postJson(route('admin.theme'), ['theme' => 'dark'])->assertRedirect();

        $this->assertDatabaseMissing('users', ['theme' => 'dark']);
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'Staff', 'email' => 'staff'.uniqid().'@example.com',
            'password' => bcrypt('secret123'), 'role' => 'admin', 'status' => 'active',
        ]);
    }
}
