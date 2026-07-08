<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attr = []): User
    {
        return User::create(array_merge([
            'name' => 'Old Name', 'email' => 'c@test.local', 'password' => 'oldpass123', 'role' => 'customer',
        ], $attr));
    }

    public function test_update_profile_saves_personal_info(): void
    {
        Sanctum::actingAs($u = $this->customer());

        $this->putJson('/api/account/profile', [
            'name' => 'New Name', 'phone' => '+880 1712345678', 'address' => '12 Road', 'city' => 'Dhaka', 'country' => 'Bangladesh',
        ])->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.phone', '+880 1712345678');

        $u->refresh();
        $this->assertSame('New Name', $u->name);
        $this->assertSame('Dhaka', $u->city);
    }

    public function test_change_password_requires_correct_current(): void
    {
        Sanctum::actingAs($u = $this->customer());

        // wrong current password → 422
        $this->putJson('/api/account/password', [
            'current_password' => 'nope', 'password' => 'brandnew123', 'password_confirmation' => 'brandnew123',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        // correct → updated
        $this->putJson('/api/account/password', [
            'current_password' => 'oldpass123', 'password' => 'brandnew123', 'password_confirmation' => 'brandnew123',
        ])->assertOk();

        $this->assertTrue(Hash::check('brandnew123', $u->fresh()->password));
    }

    public function test_upload_avatar_stores_and_sets_photo(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($u = $this->customer());

        $this->postJson('/api/account/avatar', [
            'photo' => UploadedFile::fake()->image('me.png', 300, 300),
        ])->assertOk()->assertJsonPath('data.photo', fn ($v) => is_string($v) && str_contains($v, 'me.png'));

        $u->refresh();
        $this->assertNotNull($u->photo);
        Storage::disk('public')->assertExists($u->photo);
    }

    public function test_avatar_rejects_non_image_and_oversize(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/account/avatar', ['photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
            ->assertStatus(422);
        $this->postJson('/api/account/avatar', ['photo' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertStatus(422);
    }

    public function test_email_verification_sends_and_verifies(): void
    {
        Mail::fake();
        $u = $this->customer(['email_verified_at' => null]);
        Sanctum::actingAs($u);

        $this->postJson('/api/account/email/verify')->assertOk();
        Mail::assertSent(VerifyEmailLink::class);

        // hit the signed link the email would contain
        $url = URL::temporarySignedRoute('account.email.verify', now()->addHour(), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $this->get($url)->assertRedirect();

        $this->assertNotNull($u->fresh()->email_verified_at);
    }

    public function test_email_verification_rejects_bad_hash(): void
    {
        $u = $this->customer(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('account.email.verify', now()->addHour(), ['id' => $u->id, 'hash' => 'wrong']);

        $this->get($url)->assertRedirect();
        $this->assertNull($u->fresh()->email_verified_at);
    }

    public function test_delete_account_requires_password(): void
    {
        Sanctum::actingAs($u = $this->customer());

        $this->deleteJson('/api/account', ['password' => 'wrong'])->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $u->id]);

        $this->deleteJson('/api/account', ['password' => 'oldpass123'])->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }
}
