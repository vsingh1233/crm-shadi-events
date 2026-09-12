<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_screens_render_with_realistic_data(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 11)->setTime(10, 0));
        $admin = User::factory()->create(['name' => 'Priya Sharma', 'role' => 'administrator']);
        $lead = Lead::factory()->create(['name' => 'Asha & Rohan', 'owner_id' => $admin->id, 'created_by' => $admin->id, 'email' => 'asha@example.test', 'phone' => '+91 98765 43210', 'wedding_location' => 'Udaipur', 'client_location' => 'New Delhi', 'wedding_start_date' => '2027-02-12', 'wedding_end_date' => '2027-02-14', 'status' => 'proposal_sent', 'temperature' => 'hot']);
        Lead::factory()->create(['name' => 'Meera & Arjun', 'owner_id' => $admin->id, 'created_by' => $admin->id, 'wedding_location' => 'Jaipur', 'status' => 'contacted', 'temperature' => 'warm']);
        $followUp = new FollowUp;
        $followUp->lead_id = $lead->id;
        $followUp->responsible_id = $admin->id;
        $followUp->created_by = $admin->id;
        $followUp->due_at = now()->addDay();
        $followUp->notes = 'Walk through the venue shortlist and revised proposal.';
        $followUp->save();
        $lead->recordChange('created', null, 'Manual enquiry', $admin->id);
        $this->actingAs($admin);
        foreach (['dashboard' => '/dashboard', 'leads' => '/leads', 'detail' => '/leads/'.$lead->id, 'create' => '/leads/create', 'edit' => '/leads/'.$lead->id.'/edit', 'follow-ups' => '/follow-ups', 'team' => '/team', 'notifications' => '/notifications'] as $name => $url) {
            $response = $this->get($url)->assertOk();
            if (getenv('CRM_CAPTURE_PREVIEWS') === '1') {
                $directory = storage_path('app/crm-preview');
                if (! is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
                file_put_contents($directory.'/'.$name.'.html', $response->getContent());
            }
        }
    }

    public function test_pagination_keeps_filters_and_page_two_has_remaining_lead(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Lead::factory()->count(16)->create(['owner_id' => $admin->id, 'temperature' => 'hot']);
        $response = $this->actingAs($admin)->get('/leads?temperature=hot&sort=name&direction=asc');
        $response->assertViewHas('leads', fn ($leads) => $leads->count() === 15 && $leads->total() === 16);
        $this->get('/leads?temperature=hot&sort=name&direction=asc&page=2')->assertViewHas('leads', fn ($leads) => $leads->count() === 1);
    }

    public function test_owner_reassignment_handles_form_string_ids_and_preserves_creator(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $old = User::factory()->create();
        $next = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $old->id, 'created_by' => $old->id]);
        $this->actingAs($admin)->patch('/leads/'.$lead->id.'/team', ['owner_id' => (string) $next->id])->assertSessionHasNoErrors();
        $this->assertSame($next->id, $lead->fresh()->owner_id);
        $this->assertSame($old->id, $lead->fresh()->created_by);
        $this->assertFalse($old->can('view', $lead->fresh()));
        $this->assertTrue($next->can('view', $lead->fresh()));
    }
}
