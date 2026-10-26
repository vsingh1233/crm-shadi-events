<?php

namespace Tests\Feature;

use App\CreateLead;
use App\Mcp\Tools\CreateWhatsappLead;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CoupleNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_couple_name_can_be_created_edited_displayed_and_cleared(): void
    {
        $owner = User::factory()->create();
        $payload = ['name' => 'Wedding Planner', 'email' => 'planner@example.test', 'status' => 'new', 'couple_name' => 'Anjali & Garima'];
        $this->actingAs($owner)->post('/leads', $payload)->assertSessionHasNoErrors();
        $lead = Lead::firstOrFail();
        $this->assertSame('Anjali & Garima', $lead->couple_name);
        $this->get('/leads')->assertSee('Anjali & Garima');
        $this->get('/leads/'.$lead->id)->assertSee('Anjali & Garima');
        $this->get('/leads/'.$lead->id.'/edit')->assertSee('Anjali & Garima');
        $this->get('/leads/create')->assertSee('name="couple_name"', false);

        $payload['couple_name'] = 'Garima & Anjali';
        $this->patch('/leads/'.$lead->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame('Garima & Anjali', $lead->fresh()->couple_name);
        $this->assertDatabaseHas('lead_changes', ['lead_id' => $lead->id, 'field' => 'couple_name', 'old_value' => 'Anjali & Garima', 'new_value' => 'Garima & Anjali']);

        $payload['couple_name'] = '';
        $this->patch('/leads/'.$lead->id, $payload)->assertSessionHasNoErrors();
        $this->assertNull($lead->fresh()->couple_name);
    }

    public function test_couple_search_matches_either_order_and_respects_access_and_filters(): void
    {
        $owner = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id, 'couple_name' => 'Anjali & Garima', 'status' => 'contacted']);
        Lead::factory()->create(['couple_name' => 'Anjali & Garima']);
        Lead::factory()->create(['owner_id' => $owner->id, 'couple_name' => 'Anjali & Rohan']);
        $this->actingAs($owner);

        foreach (['Garima', 'Anjali & Garima', 'Garima & Anjali', 'garima and ANJALI'] as $search) {
            $this->get('/leads?'.http_build_query(['search' => $search]))
                ->assertOk()
                ->assertViewHas('leads', fn ($leads) => $leads->pluck('id')->all() === [$lead->id]);
        }
        $this->get('/leads?'.http_build_query(['search' => 'Garima', 'status' => 'new']))
            ->assertViewHas('leads', fn ($leads) => $leads->isEmpty());
    }

    public function test_optional_couple_name_is_validated(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = ['name' => 'Client', 'email' => 'client@example.test', 'status' => 'new'];
        foreach ([str_repeat('a', 256), ['Anjali', 'Garima']] as $invalidName) {
            $this->post('/leads', $payload + ['couple_name' => $invalidName])->assertSessionHasErrors('couple_name');
        }
        $this->assertDatabaseCount('leads', 0);
        $this->post('/leads', $payload)->assertSessionHasNoErrors();
        $this->assertNull(Lead::firstOrFail()->couple_name);
    }

    public function test_rest_api_saves_returns_and_replays_couple_name(): void
    {
        $token = str_repeat('a', 64);
        User::factory()->create(['can_create_leads_via_api' => true, 'lead_api_token_hash' => hash('sha256', $token), 'lead_api_token_expires_at' => now()->addHour()]);
        $payload = ['request_id' => (string) Str::uuid(), 'name' => 'Client', 'email' => 'api@example.test', 'couple_name' => 'Anjali & Garima'];
        $this->withToken($token)->postJson('/api/v1/leads', $payload)->assertCreated()->assertJsonPath('data.couple_name', 'Anjali & Garima');
        $this->withToken($token)->postJson('/api/v1/leads', $payload)->assertOk()->assertJsonPath('meta.replayed', true);
        $payload['couple_name'] = 'Garima & Anjali';
        $this->withToken($token)->postJson('/api/v1/leads', $payload)->assertConflict();
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_mcp_saves_returns_and_replays_couple_name(): void
    {
        $user = User::factory()->create(['can_create_leads_via_api' => true]);
        Passport::actingAs($user, ['mcp:use'], 'mcp');
        $input = ['request_id' => (string) Str::uuid(), 'name' => 'Client', 'email' => 'mcp@example.test', 'couple_name' => ' Anjali & Garima '];
        $tool = app(CreateWhatsappLead::class);
        $response = $tool->handle(new Request($input), app(CreateLead::class));
        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertSame('Anjali & Garima', $response->getStructuredContent()['couple_name']);
        $this->assertSame('Anjali & Garima', Lead::firstOrFail()->couple_name);
        $replay = $tool->handle(new Request($input), app(CreateLead::class));
        $this->assertTrue($replay->getStructuredContent()['replayed']);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_mcp_retries_without_couple_name_preserve_existing_payload_hashes(): void
    {
        $user = User::factory()->create(['can_create_leads_via_api' => true]);
        Passport::actingAs($user, ['mcp:use'], 'mcp');
        $requestId = (string) Str::uuid();
        $legacyData = ['status' => 'new', 'source' => 'whatsapp', 'name' => 'Client', 'email' => 'legacy@example.test', 'phone' => null, 'client_location' => null, 'wedding_location' => null, 'wedding_start_date' => null, 'wedding_end_date' => null, 'notes' => null];
        ksort($legacyData);
        $lead = Lead::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id, 'api_request_id' => $requestId, 'api_payload_hash' => hash('sha256', 'mcp:whatsapp:'.json_encode($legacyData, JSON_THROW_ON_ERROR))]);
        $response = app(CreateWhatsappLead::class)->handle(new Request(['request_id' => $requestId, 'name' => 'Client', 'email' => 'legacy@example.test']), app(CreateLead::class));
        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertTrue($response->getStructuredContent()['replayed']);
        $this->assertSame($lead->id, $response->getStructuredContent()['lead_id']);
        $this->assertDatabaseCount('leads', 1);
    }
}
