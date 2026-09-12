<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['name' => 'Test User', 'email' => 'test@example.com'])->assertSessionHasNoErrors()->assertRedirect('/profile');
        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['name' => 'Test User', 'email' => $user->email])->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_self_deletion_is_unavailable_to_preserve_crm_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertStatus(405);
        $this->assertModelExists($user);
    }

    public function test_profile_cannot_change_role_or_approval(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'role' => 'administrator', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame('team_member', $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
    }
}
