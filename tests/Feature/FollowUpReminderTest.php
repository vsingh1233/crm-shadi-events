<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class FollowUpReminderTest extends TestCase
{
    use RefreshDatabase;

    private function followUp(): FollowUp
    {
        $owner = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id, 'name' => 'Planner', 'couple_name' => 'Anjali & Garima']);
        $followUp = new FollowUp;
        $followUp->lead_id = $lead->id;
        $followUp->responsible_id = $owner->id;
        $followUp->created_by = $owner->id;
        $followUp->due_at = now();
        $followUp->notes = 'Discuss the venue shortlist';
        $followUp->status = 'pending';
        $followUp->save();

        return $followUp;
    }

    public function test_first_reminder_is_due_immediately_and_repeats_only_at_ten_ist_on_later_days(): void
    {
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(3, 0));
        $followUp = $this->followUp();
        $this->artisan('crm:send-reminders')->assertSuccessful();
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($followUp->responsible, CrmNotification::class, 1);
        $this->travelTo(now()->setTime(4, 30));
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($followUp->responsible, CrmNotification::class, 1);

        $this->travelTo(now()->addDay()->setTime(4, 29, 59));
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($followUp->responsible, CrmNotification::class, 1);
        $this->travelTo(now()->addSecond());
        $this->artisan('crm:send-reminders')->assertSuccessful();
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($followUp->responsible, CrmNotification::class, 2);
        Notification::assertSentTo($followUp->responsible, CrmNotification::class, fn ($notification) => str_contains($notification->message, 'Anjali & Garima') && str_contains($notification->message, $followUp->notes));

        $this->travelTo(now()->addDays(3)->setTime(8, 0));
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($followUp->responsible, CrmNotification::class, 3);
    }

    public function test_previous_one_time_reminders_resume_and_future_followups_are_skipped(): void
    {
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(4, 30));
        $followUp = $this->followUp();
        $followUp->reminder_queued_at = now()->subWeek();
        $followUp->due_at = now()->subWeek();
        $followUp->save();
        $future = $this->followUp();
        $future->due_at = now()->addHour();
        $future->save();
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($followUp->responsible, CrmNotification::class, 1);
        Notification::assertNotSentTo($future->responsible, CrmNotification::class);
    }

    public function test_closed_followups_closed_or_deleted_leads_and_ineligible_members_are_skipped(): void
    {
        Notification::fake();
        $this->freezeTime();
        foreach (['completed', 'cancelled', 'won', 'lost', 'deleted', 'inactive', 'removed'] as $reason) {
            $followUp = $this->followUp();
            if (in_array($reason, ['completed', 'cancelled'], true)) {
                $followUp->status = $reason;
                $followUp->save();
            } elseif (in_array($reason, ['won', 'lost'], true)) {
                $followUp->lead->status = $reason;
                $followUp->lead->save();
            } elseif ($reason === 'deleted') {
                $followUp->lead->delete();
            } elseif ($reason === 'inactive') {
                $followUp->responsible->is_active = false;
                $followUp->responsible->save();
            } else {
                $followUp->lead->owner_id = User::factory()->create()->id;
                $followUp->lead->save();
            }
        }
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_queued_reminders_recheck_completion_and_suppress_superseded_messages(): void
    {
        config(['crm.email_enabled' => true]);
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(4, 30));
        $followUp = $this->followUp();
        $this->artisan('crm:send-reminders')->assertSuccessful();
        $first = Notification::sent($followUp->responsible, CrmNotification::class)->first();
        $this->assertTrue($first->shouldSend($followUp->responsible, 'mail'));
        $this->travelTo(now()->addDay());
        $this->artisan('crm:send-reminders')->assertSuccessful();
        $second = Notification::sent($followUp->responsible, CrmNotification::class)->last();
        $this->assertFalse($first->shouldSend($followUp->responsible, 'mail'));
        $this->assertTrue($second->shouldSend($followUp->responsible, 'mail'));
        $followUp->status = 'completed';
        $followUp->save();
        $this->assertFalse($second->shouldSend($followUp->responsible, 'mail'));
        $this->travelTo(now()->addDay());
        $this->artisan('crm:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($followUp->responsible, CrmNotification::class, 2);
    }

    public function test_queued_mail_is_delivered_through_the_database_worker(): void
    {
        config(['crm.email_enabled' => true, 'mail.default' => 'array']);
        $this->freezeTime();
        $followUp = $this->followUp();
        $mailSent = false;
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$mailSent): void {
            if ($event->channel === 'mail') {
                $mailSent = true;
            }
        });
        $this->artisan('crm:send-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertFalse($mailSent);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 5])->assertSuccessful();
        $this->assertTrue($mailSent);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertNotNull($followUp->fresh()->reminder_queued_at);
    }

    public function test_failed_mail_is_retried_without_scheduling_another_reminder(): void
    {
        config(['crm.email_enabled' => true]);
        $this->freezeTime();
        $followUp = $this->followUp();
        $attempts = 0;
        $this->mock(MailChannel::class)->shouldReceive('send')->twice()->andReturnUsing(function () use (&$attempts): void {
            $attempts++;
            if ($attempts === 1) {
                throw new RuntimeException('Temporary email provider failure');
            }
        });
        $this->artisan('crm:send-reminders')->assertSuccessful();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 5])->assertSuccessful();
        $this->assertSame(1, $attempts);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->artisan('crm:send-reminders')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->travelTo(now()->addSeconds(31));
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 5])->assertSuccessful();
        $this->assertSame(2, $attempts);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull($followUp->fresh()->reminder_queued_at);
    }
}
