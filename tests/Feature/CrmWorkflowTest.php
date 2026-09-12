<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CrmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Asha Mehta', 'email' => 'asha@example.test', 'status' => 'new'], $overrides);
    }

    private function followUp(Lead $lead, User $responsible, string $status = 'pending'): FollowUp
    {
        $followUp = new FollowUp;
        $followUp->lead_id = $lead->id;
        $followUp->responsible_id = $responsible->id;
        $followUp->created_by = $responsible->id;
        $followUp->due_at = now()->subMinute();
        $followUp->notes = 'Discuss proposal';
        $followUp->status = $status;
        $followUp->save();

        return $followUp;
    }

    public function test_guest_cannot_open_or_create_leads(): void
    {
        $this->get('/leads')->assertRedirect('/login');
        $this->post('/leads', $this->payload())->assertRedirect('/login');
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_registration_does_not_accept_privileged_attributes(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Notification::fake();
        $this->post('/register', ['name' => 'New Member', 'email' => 'new@example.test', 'password' => 'LongPassword123', 'password_confirmation' => 'LongPassword123', 'role' => 'administrator', 'is_active' => true])->assertRedirect('/pending-approval');
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'role' => 'team_member', 'is_active' => false]);
        Notification::assertSentTo($admin, CrmNotification::class);
    }

    public function test_pending_accounts_are_blocked_from_existing_sessions_but_can_logout(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/pending-approval');
        $this->get('/pending-approval')->assertSee('awaiting administrator approval');
        $this->post('/leads', $this->payload())->assertRedirect('/pending-approval');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_admin_approval_allows_access_and_prevents_self_lockout(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'administrator']);
        $member = User::factory()->create(['is_active' => false]);
        $this->actingAs($admin)->patch('/team/'.$member->id, ['role' => 'team_member', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->is_active);
        Notification::assertSentTo($member, CrmNotification::class);
        $this->patch('/team/'.$admin->id, ['role' => 'team_member', 'is_active' => 0])->assertSessionHasErrors('team');
        $this->assertTrue($admin->fresh()->is_active);
        $this->actingAs($member->fresh())->get('/dashboard')->assertOk();
        $this->get('/team')->assertForbidden();
    }

    public function test_create_and_edit_keep_ownership_and_record_changes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post('/leads', $this->payload(['owner_id' => $other->id, 'created_by' => $other->id]))->assertSessionHasNoErrors();
        $lead = Lead::firstOrFail();
        $this->assertSame($owner->id, $lead->owner_id);
        $this->assertSame($owner->id, $lead->created_by);
        $this->patch('/leads/'.$lead->id, $this->payload(['temperature' => 'hot', 'wedding_location' => 'Jaipur']))->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'temperature' => 'hot', 'wedding_location' => 'Jaipur', 'owner_id' => $owner->id]);
        $this->assertDatabaseHas('lead_changes', ['lead_id' => $lead->id, 'field' => 'temperature', 'new_value' => 'hot']);
        $this->get('/leads/'.$lead->id)->assertSee('Jaipur');
        $this->get('/leads/'.$lead->id.'/edit')->assertOk();
        $this->get('/leads/create')->assertOk();
    }

    public function test_contact_dates_and_lost_reason_are_validated(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/leads', ['name' => 'Client', 'status' => 'new'])->assertSessionHasErrors(['email', 'phone']);
        $this->post('/leads', $this->payload(['wedding_start_date' => '2027-01-10', 'wedding_end_date' => '2027-01-09']))->assertSessionHasErrors('wedding_end_date');
        $this->post('/leads', $this->payload(['status' => 'lost']))->assertSessionHasErrors('lost_reason');
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_policy_matrix_and_cross_team_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $admin = User::factory()->create(['role' => 'administrator']);
        $lead = Lead::factory()->create(['owner_id' => $owner->id, 'name' => 'Private Client']);
        $lead->collaborators()->attach($member);
        $this->assertTrue($owner->can('manageTeam', $lead));
        $this->assertTrue($member->can('update', $lead));
        $this->assertFalse($member->can('manageTeam', $lead));
        $this->assertFalse($owner->can('delete', $lead));
        $this->assertTrue($admin->can('delete', $lead));
        $this->assertFalse($outsider->can('view', $lead));
        $this->actingAs($outsider)->get('/leads')->assertDontSee('Private Client');
        $this->get('/leads/'.$lead->id)->assertForbidden();
        $this->patch('/leads/'.$lead->id, $this->payload())->assertForbidden();
        $this->post('/leads/'.$lead->id.'/activities', [])->assertForbidden();
        $this->delete('/leads/'.$lead->id)->assertForbidden();
        $this->actingAs($member)->get('/leads/'.$lead->id)->assertOk();
    }

    public function test_duplicate_contact_requires_both_different_location_and_dates(): void
    {
        $owner = User::factory()->create();
        Lead::factory()->create(['email' => 'asha@example.test', 'wedding_location' => 'Jaipur', 'wedding_start_date' => '2027-01-01', 'wedding_end_date' => '2027-01-02']);
        $this->actingAs($owner);
        $this->post('/leads', $this->payload(['wedding_location' => 'Udaipur', 'wedding_start_date' => '2027-01-01', 'wedding_end_date' => '2027-01-02']))->assertSessionHasErrors('email');
        $this->post('/leads', $this->payload(['wedding_location' => 'Jaipur', 'wedding_start_date' => '2027-02-01', 'wedding_end_date' => '2027-02-02']))->assertSessionHasErrors('email');
        $this->post('/leads', $this->payload(['wedding_location' => 'Udaipur', 'wedding_start_date' => '2027-02-01', 'wedding_end_date' => '2027-02-02']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leads', 2);
    }

    public function test_unknown_duplicate_details_need_confirmation_and_phone_is_normalized(): void
    {
        $owner = User::factory()->create();
        Lead::factory()->create(['phone' => '+91 98765-43210']);
        $payload = $this->payload(['email' => null, 'phone' => '00919876543210']);
        $this->actingAs($owner)->post('/leads', $payload)->assertSessionHasErrors('confirm_duplicate');
        $this->post('/leads', $payload + ['confirm_duplicate' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leads', 2);
    }

    public function test_lost_reopen_and_soft_delete_preserve_history_and_cancel_followups(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $lead = Lead::factory()->create(['owner_id' => $admin->id, 'email' => 'asha@example.test']);
        $followUp = $this->followUp($lead, $admin);
        $this->actingAs($admin)->patch('/leads/'.$lead->id, $this->payload(['status' => 'lost', 'lost_reason' => 'Budget changed']))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $followUp->fresh()->status);
        $this->patch('/leads/'.$lead->id, $this->payload(['status' => 'contacted']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lead_changes', ['lead_id' => $lead->id, 'field' => 'lost_reason', 'old_value' => 'Budget changed']);
        $this->assertSame('cancelled', $followUp->fresh()->status);
        $this->delete('/leads/'.$lead->id)->assertRedirect('/leads');
        $this->assertSoftDeleted($lead);
        $this->get('/leads?archived=1')->assertSee('Asha Mehta');
        $this->post('/leads/'.$lead->id.'/restore')->assertRedirect(route('leads.show', $lead));
        $this->assertNotSoftDeleted($lead);
    }

    public function test_removing_collaborator_cancels_their_pending_followups(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);
        $lead->collaborators()->attach($member);
        $followUp = $this->followUp($lead, $member);
        $this->actingAs($member)->patch('/leads/'.$lead->id.'/team', ['owner_id' => $owner->id])->assertForbidden();
        $this->actingAs($owner)->patch('/leads/'.$lead->id.'/team', ['owner_id' => $owner->id])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $followUp->fresh()->status);
        $this->assertFalse($member->can('view', $lead->fresh()));
        Notification::assertNothingSent();
    }

    public function test_activity_notes_are_escaped_and_timestamp_is_stored_in_utc(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 11)->setTime(12, 0));
        $owner = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);
        $notes = '<script>alert("xss")</script>';
        $this->actingAs($owner)->post('/leads/'.$lead->id.'/activities', ['type' => 'call', 'occurred_at' => '2026-09-11T15:00', 'notes' => $notes])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'occurred_at' => '2026-09-11 09:30:00', 'notes' => $notes]);
        $this->get('/leads/'.$lead->id)->assertSee($notes)->assertDontSee($notes, false);
    }

    public function test_followups_validate_access_complete_and_retain_history(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 11)->setTime(10, 0));
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);
        $data = ['due_at' => '2026-09-12T12:00', 'notes' => 'Discuss venue', 'responsible_id' => $owner->id];
        $this->actingAs($owner)->post('/leads/'.$lead->id.'/follow-ups', array_merge($data, ['responsible_id' => $outsider->id]))->assertUnprocessable();
        $this->post('/leads/'.$lead->id.'/follow-ups', $data)->assertSessionHasNoErrors();
        $followUp = FollowUp::firstOrFail();
        $this->assertSame('2026-09-12 06:30:00', $followUp->due_at->format('Y-m-d H:i:s'));
        $this->patch('/leads/'.$lead->id.'/follow-ups/'.$followUp->id, ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->post('/leads/'.$lead->id.'/follow-ups', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('follow_ups', 2);
        $this->assertSame('completed', $followUp->fresh()->status);
        $this->get('/follow-ups')->assertSee('Discuss venue');
    }

    public function test_filters_combine_and_sort_input_cannot_change_query_structure(): void
    {
        $owner = User::factory()->create();
        Lead::factory()->create(['owner_id' => $owner->id, 'name' => 'Matching Client', 'status' => 'contacted', 'temperature' => 'hot', 'wedding_location' => 'Jaipur']);
        Lead::factory()->create(['owner_id' => $owner->id, 'name' => 'Excluded Client', 'temperature' => 'cold']);
        $this->actingAs($owner)->get('/leads?status=contacted&temperature=hot&wedding_location=Jaipur&sort=name&direction=asc')->assertSee('Matching Client')->assertDontSee('Excluded Client');
        $this->get('/leads?sort=name%3BDELETE&direction=attack')->assertOk();
        $this->assertDatabaseCount('leads', 2);
    }

    public function test_reminders_queue_once_and_skip_closed_followups(): void
    {
        $this->freezeTime();
        Notification::fake();
        $owner = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);
        $followUp = $this->followUp($lead, $owner);
        $this->followUp($lead, $owner, 'completed');
        $this->artisan('crm:send-reminders')->assertSuccessful();
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($owner, CrmNotification::class, 1);
        $this->assertNotNull($followUp->fresh()->reminder_queued_at);
    }

    public function test_queued_reminder_rechecks_status_and_recipient_access(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);
        $followUp = $this->followUp($lead, $owner);
        $notification = new CrmNotification('Due', 'A reminder', $lead->id, $followUp->id, $followUp->due_at->format('Y-m-d H:i:s'));
        $this->assertTrue($notification->shouldSend($owner, 'database'));
        $followUp->status = 'completed';
        $followUp->save();
        $this->assertFalse($notification->shouldSend($owner, 'mail'));
        $owner->is_active = false;
        $owner->save();
        $this->assertFalse($notification->shouldSend($owner, 'database'));
    }

    public function test_in_app_notifications_arrive_immediately_without_email_and_read_is_private(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $owner->notify(new CrmNotification('Approved', 'Your account is active.'));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $notification = $owner->notifications()->firstOrFail();
        $this->actingAs($outsider)->patch('/notifications/'.$notification->id)->assertNotFound();
        $this->actingAs($owner)->get('/notifications')->assertSee('Approved');
        $this->patch('/notifications/'.$notification->id)->assertSessionHasNoErrors();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
