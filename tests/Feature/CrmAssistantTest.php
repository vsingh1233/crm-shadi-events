<?php

namespace Tests\Feature;

use App\CrmLeadActions;
use App\Mcp\Tools\GetLead;
use App\Mcp\Tools\ScheduleFollowUp;
use App\Mcp\Tools\SearchLeads;
use App\Mcp\Tools\UpdateCoupleName;
use App\Mcp\Tools\UpdateFollowUpStatus;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CrmAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function apiUser(): User
    {
        $token = str_repeat('b', 64);
        $user = User::factory()->create(['can_create_leads_via_api' => true, 'lead_api_token_hash' => hash('sha256', $token), 'lead_api_token_expires_at' => now()->addHour()]);
        $this->withToken($token);

        return $user;
    }

    /** @return array{request_id: string, responsible_id: int, due_at: string, notes: string} */
    private function followUpInput(User $user): array
    {
        return ['request_id' => (string) Str::uuid(), 'responsible_id' => $user->id, 'due_at' => now()->addDay()->timezone('Asia/Kolkata')->format('Y-m-d\TH:iP'), 'notes' => 'Discuss venue'];
    }

    public function test_api_search_and_details_are_private_and_paginated(): void
    {
        $owner = $this->apiUser();
        $lead = Lead::factory()->create(['owner_id' => $owner->id, 'couple_name' => 'Anjali & Garima']);
        $private = Lead::factory()->create(['couple_name' => 'Anjali & Garima']);
        $collaborator = User::factory()->create();
        $lead->collaborators()->attach($collaborator);
        $this->getJson('/api/v1/leads?'.http_build_query(['search' => 'Garima and Anjali']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $lead->id);
        $this->getJson('/api/v1/leads/'.$private->id)->assertNotFound();
        $response = $this->getJson('/api/v1/leads/'.$lead->id)->assertOk()->assertJsonPath('data.couple_name', 'Anjali & Garima');
        $this->assertContains($collaborator->id, array_column($response->json('responsible_members'), 'id'));
        $this->assertNotContains($private->owner_id, array_column($response->json('responsible_members'), 'id'));
        Lead::factory()->count(21)->create(['owner_id' => $owner->id]);
        $this->getJson('/api/v1/leads')->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 22);
        $this->getJson('/api/v1/leads?page=2')->assertJsonCount(2, 'data');
    }

    public function test_couple_name_updates_record_history_and_reject_stale_changes(): void
    {
        $user = $this->apiUser();
        $lead = Lead::factory()->create(['owner_id' => $user->id, 'couple_name' => null]);
        $url = '/api/v1/leads/'.$lead->id.'/couple-name';
        $input = ['couple_name' => 'Anjali & Garima', 'expected_couple_name' => null];
        $this->patchJson($url, $input)->assertOk()->assertJsonPath('data.couple_name', 'Anjali & Garima');
        $this->patchJson($url, $input)->assertOk();
        $this->assertSame(1, $lead->changes()->where('field', 'couple_name')->count());
        $this->patchJson($url, ['couple_name' => 'Another Couple', 'expected_couple_name' => null])->assertConflict();
        $this->patchJson($url, ['couple_name' => null, 'expected_couple_name' => 'Anjali & Garima'])->assertOk()->assertJsonPath('data.couple_name', null);
        $this->patchJson($url, ['couple_name' => 'Missing previous value'])->assertUnprocessable();
    }

    public function test_schedule_retries_do_not_duplicate_and_times_use_explicit_offsets(): void
    {
        $user = $this->apiUser();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $input = $this->followUpInput($user);
        $url = '/api/v1/leads/'.$lead->id.'/follow-ups';
        $this->postJson($url, $input)->assertCreated()->assertJsonPath('data.status', 'pending');
        $followUp = FollowUp::firstOrFail();
        $this->assertSame($input['due_at'], $followUp->due_at->timezone('Asia/Kolkata')->format('Y-m-d\TH:iP'));
        $this->postJson($url, $input)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->postJson($url, array_replace($input, ['notes' => 'Changed purpose']))->assertConflict();
        $this->assertDatabaseCount('follow_ups', 1);
        $this->assertSame(1, $lead->changes()->where('field', 'follow_up')->count());
        $this->postJson($url, array_replace($input, ['request_id' => (string) Str::uuid(), 'due_at' => '2027-12-01T10:00']))->assertUnprocessable();
    }

    public function test_followup_assignment_and_closed_lead_validation(): void
    {
        $user = $this->apiUser();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $url = '/api/v1/leads/'.$lead->id.'/follow-ups';
        $input = $this->followUpInput($user);
        $outsider = User::factory()->create();
        $this->postJson($url, array_replace($input, ['responsible_id' => $outsider->id]))->assertUnprocessable();
        $lead->collaborators()->attach($outsider);
        $outsider->is_active = false;
        $outsider->save();
        $this->postJson($url, array_replace($input, ['responsible_id' => $outsider->id]))->assertUnprocessable();
        $this->postJson($url, array_replace($input, ['due_at' => now()->subDay()->format('Y-m-d\TH:iP')]))->assertUnprocessable();
        $lead->status = 'won';
        $lead->save();
        $this->postJson($url, $input)->assertUnprocessable();
        $this->assertDatabaseCount('follow_ups', 0);
    }

    public function test_completion_is_idempotent_and_rejects_wrong_leads_or_conflicting_status(): void
    {
        $user = $this->apiUser();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $other = Lead::factory()->create(['owner_id' => $user->id]);
        $input = $this->followUpInput($user);
        $this->postJson('/api/v1/leads/'.$lead->id.'/follow-ups', $input)->assertCreated();
        $followUp = FollowUp::firstOrFail();
        $this->patchJson('/api/v1/leads/'.$other->id.'/follow-ups/'.$followUp->id, ['status' => 'completed'])->assertNotFound();
        $url = '/api/v1/leads/'.$lead->id.'/follow-ups/'.$followUp->id;
        $this->patchJson($url, ['status' => 'completed'])->assertOk();
        $closedAt = $followUp->fresh()->closed_at;
        $this->patchJson($url, ['status' => 'completed'])->assertOk();
        $this->assertTrue($closedAt->equalTo($followUp->fresh()->closed_at));
        $this->patchJson($url, ['status' => 'cancelled'])->assertConflict();
        $this->patchJson($url, ['status' => 'pending'])->assertUnprocessable();
        $this->assertSame(2, $lead->changes()->where('field', 'follow_up')->count());
        $this->postJson('/api/v1/leads/'.$lead->id.'/follow-ups', $input)->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_api_rejects_unauthorized_users_and_other_teams_mutations(): void
    {
        $user = $this->apiUser();
        $private = Lead::factory()->create();
        $this->patchJson('/api/v1/leads/'.$private->id.'/couple-name', ['couple_name' => 'A & B', 'expected_couple_name' => null])->assertNotFound();
        $this->postJson('/api/v1/leads/'.$private->id.'/follow-ups', $this->followUpInput($user))->assertNotFound();
        $user->can_create_leads_via_api = false;
        $user->save();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/leads')->assertForbidden();
    }

    public function test_mcp_tools_support_the_complete_followup_workflow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['can_create_leads_via_api' => true]);
        Passport::actingAs($user, ['mcp:use'], 'mcp');
        $lead = Lead::factory()->create(['owner_id' => $user->id, 'couple_name' => 'Anjali & Garima']);
        $actions = app(CrmLeadActions::class);
        $search = app(SearchLeads::class)->handle(new Request(['search' => 'Garima & Anjali']), $actions);
        $this->assertSame($lead->id, $search->getStructuredContent()['data'][0]['id']);
        $details = app(GetLead::class)->handle(new Request(['lead_id' => $lead->id]), $actions);
        $this->assertSame($user->id, $details->getStructuredContent()['responsible_members'][0]['id']);
        $update = app(UpdateCoupleName::class)->handle(new Request(['lead_id' => $lead->id, 'couple_name' => 'Garima & Anjali', 'expected_couple_name' => 'Anjali & Garima']), $actions);
        $this->assertSame('Garima & Anjali', $update->getStructuredContent()['data']['couple_name']);
        $input = ['lead_id' => $lead->id] + $this->followUpInput($user);
        $scheduled = app(ScheduleFollowUp::class)->handle(new Request($input), $actions);
        $this->assertInstanceOf(ResponseFactory::class, $scheduled);
        $id = $scheduled->getStructuredContent()['data']['id'];
        $replay = app(ScheduleFollowUp::class)->handle(new Request($input), $actions);
        $this->assertTrue($replay->getStructuredContent()['meta']['replayed']);
        $closed = app(UpdateFollowUpStatus::class)->handle(new Request(['lead_id' => $lead->id, 'follow_up_id' => $id, 'status' => 'cancelled']), $actions);
        $this->assertSame('cancelled', $closed->getStructuredContent()['data']['status']);
        $this->assertSame('cancelled', FollowUp::findOrFail($id)->status);
    }

    public function test_mcp_scope_access_validation_and_schema_contracts(): void
    {
        $user = User::factory()->create(['can_create_leads_via_api' => true]);
        Passport::actingAs($user, [], 'mcp');
        $actions = app(CrmLeadActions::class);
        $this->assertTrue(app(SearchLeads::class)->handle(new Request, $actions)->isError());
        Passport::actingAs($user, ['mcp:use'], 'mcp');
        $private = Lead::factory()->create();
        $this->assertTrue(app(GetLead::class)->handle(new Request(['lead_id' => $private->id]), $actions)->isError());
        $this->assertTrue(app(ScheduleFollowUp::class)->handle(new Request(['lead_id' => $private->id]), $actions)->isError());
        $user->is_active = false;
        $user->save();
        $this->assertTrue(app(SearchLeads::class)->handle(new Request, $actions)->isError());
        foreach ([SearchLeads::class, GetLead::class, UpdateCoupleName::class, ScheduleFollowUp::class, UpdateFollowUpStatus::class] as $tool) {
            $schema = app($tool)->toArray();
            $this->assertNotEmpty($schema['name']);
            $this->assertSame('object', $schema['inputSchema']['type']);
        }
    }
}
